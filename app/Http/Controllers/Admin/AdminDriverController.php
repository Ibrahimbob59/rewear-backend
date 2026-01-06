<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DriverApplicationResource;
use App\Http\Resources\UserResource;
use App\Models\DriverApplication;
use App\Models\User;
use App\Services\DriverApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminDriverController extends Controller
{
    protected DriverApplicationService $applicationService;

    public function __construct(DriverApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
        $this->middleware('role:admin');
    }

    /**
     * @OA\Get(
     *     path="/api/admin/driver-applications",
     *     tags={"Admin - Drivers"},
     *     summary="Get all driver applications",
     *     description="Get paginated list of driver applications with filters",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by application status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","under_review","approved","rejected"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Driver applications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Admin access required")
     * )
     */
    public function applications(Request $request): JsonResponse
    {
        try {
            $query = DriverApplication::with(['user']);

            // Apply status filter
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('city', 'ILIKE', "%{$search}%");
                });
            }

            $applications = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Driver applications retrieved successfully',
                'data' => DriverApplicationResource::collection($applications),
                'meta' => [
                    'current_page' => $applications->currentPage(),
                    'total' => $applications->total(),
                    'per_page' => $applications->perPage(),
                    'last_page' => $applications->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve driver applications', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve applications',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/driver-applications/{id}",
     *     tags={"Admin - Drivers"},
     *     summary="Get driver application details",
     *     description="Get detailed information about a specific driver application",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Application ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function applicationDetails(int $id): JsonResponse
    {
        try {
            $application = $this->applicationService->getApplicationDetails($id);

            return response()->json([
                'success' => true,
                'message' => 'Application details retrieved successfully',
                'data' => new DriverApplicationResource($application),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve application details', [
                'application_id' => $id,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Application not found',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/driver-applications/{id}/approve",
     *     tags={"Admin - Drivers"},
     *     summary="Approve driver application",
     *     description="Approve a pending driver application",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Application ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Driver application approved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Application cannot be approved"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function approveApplication(int $id): JsonResponse
    {
        try {
            $application = $this->applicationService->approveApplication($id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Driver application approved successfully. The user is now a verified driver.',
                'data' => new DriverApplicationResource($application),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to approve driver application', [
                'application_id' => $id,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/admin/driver-applications/{id}/reject",
     *     tags={"Admin - Drivers"},
     *     summary="Reject driver application",
     *     description="Reject a pending driver application with reason",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Application ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(property="reason", type="string", example="Incomplete documentation", description="Reason for rejection")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Application rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Driver application rejected"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Application cannot be rejected"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function rejectApplication(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $application = $this->applicationService->rejectApplication(
                $id,
                auth()->id(),
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Driver application rejected. The applicant has been notified.',
                'data' => new DriverApplicationResource($application),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reject driver application', [
                'application_id' => $id,
                'admin_id' => auth()->id(),
                'reason' => $request->input('reason'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/drivers",
     *     tags={"Admin - Drivers"},
     *     summary="Get all verified drivers",
     *     description="Get list of all approved and verified drivers",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Drivers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function drivers(Request $request): JsonResponse
    {
        try {
            $query = User::role('driver')
                ->with(['driverApplication'])
                ->where('driver_verified', true);

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ILIKE', "%{$search}%")
                        ->orWhere('email', 'ILIKE', "%{$search}%")
                        ->orWhere('phone', 'ILIKE', "%{$search}%");
                });
            }

            $drivers = $query->orderBy('driver_verified_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'message' => 'Drivers retrieved successfully',
                'data' => UserResource::collection($drivers),
                'meta' => [
                    'current_page' => $drivers->currentPage(),
                    'total' => $drivers->total(),
                    'per_page' => $drivers->perPage(),
                    'last_page' => $drivers->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve drivers', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve drivers',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/driver-applications/stats",
     *     tags={"Admin - Drivers"},
     *     summary="Get driver application statistics",
     *     description="Get statistics about driver applications",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_applications", type="integer", example=45),
     *                 @OA\Property(property="pending_applications", type="integer", example=8),
     *                 @OA\Property(property="approved_applications", type="integer", example=32),
     *                 @OA\Property(property="approval_rate", type="number", example=80.5)
     *             )
     *         )
     *     )
     * )
     */
    public function applicationStats(): JsonResponse
    {
        try {
            $stats = $this->applicationService->getApplicationStats();

            return response()->json([
                'success' => true,
                'message' => 'Application statistics retrieved successfully',
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve application statistics', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

