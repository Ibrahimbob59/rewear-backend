<?php

namespace App\Services;

use App\Models\DriverApplication;
use App\Models\User;
use App\Services\FirebaseStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class DriverApplicationService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Submit driver application
     *
     * @param User $user
     * @param array $data
     * @param array $documents
     * @return DriverApplication
     * @throws \Exception
     */
    public function submitApplication(User $user, array $data, array $documents = []): DriverApplication
    {
        DB::beginTransaction();

        try {
            // Check if user already has a pending or approved application
            $existingApplication = DriverApplication::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'under_review', 'approved'])
                ->first();

            if ($existingApplication) {
                throw new \Exception('You already have an active driver application');
            }

            // Upload documents to Firebase Storage
            $documentUrls = $this->uploadDocuments($documents, $user->id);

            // Create application
            $application = DriverApplication::create([
                'user_id' => $user->id,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? $user->email,
                'address' => $data['address'],
                'city' => $data['city'],
                'vehicle_type' => $data['vehicle_type'],
                'id_document_url' => $documentUrls['id_document'] ?? null,
                'driving_license_url' => $documentUrls['driving_license'] ?? null,
                'vehicle_registration_url' => $documentUrls['vehicle_registration'] ?? null,
                'status' => 'pending',
            ]);

            DB::commit();

            Log::info('Driver application submitted with Firebase documents', [
                'user_id' => $user->id,
                'application_id' => $application->id,
                'vehicle_type' => $data['vehicle_type'],
                'documents_uploaded' => array_keys($documentUrls),
            ]);

            return $application;

        } catch (\Exception $e) {
            DB::rollBack();

            // Try to clean up any uploaded documents
            try {
                $firebaseStorage = app(FirebaseStorageService::class);
                $firebaseStorage->deleteDriverDocuments($user->id);
            } catch (\Exception $cleanupError) {
                Log::warning('Failed to cleanup documents after failed application', [
                    'user_id' => $user->id,
                    'cleanup_error' => $cleanupError->getMessage(),
                ]);
            }

            Log::error('Failed to submit driver application', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Approve driver application
     *
     * @param int $applicationId
     * @param int $reviewerId
     * @return DriverApplication
     * @throws \Exception
     */
    public function approveApplication(int $applicationId, int $reviewerId): DriverApplication
    {
        DB::beginTransaction();

        try {
            $application = DriverApplication::where('id', $applicationId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Update application status
            $application->update([
                'status' => 'approved',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            // Assign driver role to user
            $user = $application->user;
            $driverRole = Role::where('name', 'driver')->first();

            if ($driverRole && !$user->hasRole('driver')) {
                $user->assignRole('driver');
            }

            // Update user as verified driver
            $user->update([
                'is_driver' => true,
                'driver_verified' => true,
                'driver_verified_at' => now(),
            ]);

            // Send notification
            $this->notificationService->driverApproved($application);

            DB::commit();

            Log::info('Driver application approved', [
                'application_id' => $applicationId,
                'user_id' => $application->user_id,
                'reviewer_id' => $reviewerId,
            ]);

            return $application->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve driver application', [
                'application_id' => $applicationId,
                'reviewer_id' => $reviewerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject driver application
     *
     * @param int $applicationId
     * @param int $reviewerId
     * @param string $reason
     * @return DriverApplication
     * @throws \Exception
     */
    public function rejectApplication(int $applicationId, int $reviewerId, string $reason): DriverApplication
    {
        DB::beginTransaction();

        try {
            $application = DriverApplication::where('id', $applicationId)
                ->where('status', 'pending')
                ->firstOrFail();

            // Update application status
            $application->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            // Delete uploaded documents since application is rejected
            $this->deleteApplicationDocuments($application);

            // Send notification
            $this->notificationService->driverRejected($application);

            DB::commit();

            Log::info('Driver application rejected and documents deleted', [
                'application_id' => $applicationId,
                'user_id' => $application->user_id,
                'reviewer_id' => $reviewerId,
                'reason' => $reason,
            ]);

            return $application->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject driver application', [
                'application_id' => $applicationId,
                'reviewer_id' => $reviewerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get pending applications for admin review
     *
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getPendingApplications(int $perPage = 15)
    {
        return DriverApplication::with(['user'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get application by ID with details
     *
     * @param int $applicationId
     * @return DriverApplication
     */
    public function getApplicationDetails(int $applicationId): DriverApplication
    {
        return DriverApplication::with(['user', 'reviewedBy'])
            ->findOrFail($applicationId);
    }

    /**
     * Get user's driver application
     *
     * @param int $userId
     * @return DriverApplication|null
     */
    public function getUserApplication(int $userId): ?DriverApplication
    {
        return DriverApplication::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if user can apply as driver
     *
     * @param User $user
     * @return array ['can_apply' => bool, 'reason' => string]
     */
    public function canUserApply(User $user): array
    {
        // Check if already approved
        if ($user->driver_verified) {
            return [
                'can_apply' => false,
                'reason' => 'You are already an approved driver'
            ];
        }

        // Check if has pending application
        $pendingApplication = DriverApplication::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'under_review'])
            ->first();

        if ($pendingApplication) {
            return [
                'can_apply' => false,
                'reason' => 'You already have a pending application under review'
            ];
        }

        // Check if recently rejected (wait 30 days)
        $recentRejection = DriverApplication::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->where('reviewed_at', '>', now()->subDays(30))
            ->first();

        if ($recentRejection) {
            return [
                'can_apply' => false,
                'reason' => 'Please wait 30 days after rejection before reapplying'
            ];
        }

        return [
            'can_apply' => true,
            'reason' => 'You can apply to become a driver'
        ];
    }

    /**
     * Upload application documents to Firebase Storage
     *
     * @param array $documents
     * @param int $userId
     * @return array
     */
    protected function uploadDocuments(array $documents, int $userId): array
    {
        // Use Firebase Storage instead of local storage
        $firebaseStorage = app(FirebaseStorageService::class);

        return $firebaseStorage->uploadDriverDocuments($documents, $userId);
    }

    /**
     * Delete driver application documents from storage
     *
     * @param DriverApplication $application
     * @return void
     */
    protected function deleteApplicationDocuments(DriverApplication $application): void
    {
        try {
            $firebaseStorage = app(FirebaseStorageService::class);
            $firebaseStorage->deleteDriverDocuments($application->user_id);

            Log::info('Driver application documents deleted from Firebase', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete driver application documents from Firebase', [
                'application_id' => $application->id,
                'user_id' => $application->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get application statistics
     *
     * @return array
     */
    public function getApplicationStats(): array
    {
        $pending = DriverApplication::where('status', 'pending')->count();
        $approved = DriverApplication::where('status', 'approved')->count();
        $rejected = DriverApplication::where('status', 'rejected')->count();
        $total = DriverApplication::count();

        return [
            'total_applications' => $total,
            'pending_applications' => $pending,
            'approved_applications' => $approved,
            'rejected_applications' => $rejected,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Set application under review
     *
     * @param int $applicationId
     * @param int $reviewerId
     * @return DriverApplication
     */
    public function setUnderReview(int $applicationId, int $reviewerId): DriverApplication
    {
        $application = DriverApplication::findOrFail($applicationId);

        $application->update([
            'status' => 'under_review',
            'reviewed_by' => $reviewerId,
        ]);

        Log::info('Driver application set under review', [
            'application_id' => $applicationId,
            'reviewer_id' => $reviewerId,
        ]);

        return $application;
    }
}
