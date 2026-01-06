<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriverApplication\SubmitApplicationRequest;
use App\Http\Resources\DriverApplicationResource;
use App\Services\DriverApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverApplicationController extends Controller
{
    protected DriverApplicationService $applicationService;

    public function __construct(DriverApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
    }

    /**
     * @OA\Post(
     *     path="/api/driver-applications",
     *     tags={"Driver Applications"},
     *     summary="Submit driver application",
     *     description="Submit an application to become a driver",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"full_name","phone","address","city","vehicle_type"},
     *                 @OA\Property(property="full_name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="+96170123456"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="address", type="string", example="123 Main Street"),
     *                 @OA\Property(property="city", type="string", example="Beirut"),
     *                 @OA\Property(property="vehicle_type", type="string", enum={"car","motorcycle","bicycle"}),
     *                 @OA\Property(property="id_document", type="string", format="binary", description="ID document image"),
     *                 @OA\Property(property="driving_license", type="string", format="binary", description="Driving license image"),
     *                 @OA\Property(property="vehicle_registration", type="string", format="binary", description="Vehicle registration (optional for bicycles)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Application submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Driver application submitted successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error or user already has application"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(SubmitApplicationRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $data = $request->validated();

            // Check if user can apply
            $canApply = $this->applicationService->canUserApply($user);
            if (!$canApply['can_apply']) {
                return response()->json([
                    'success' => false,
                    'message' => $canApply['reason'],
                ], 400);
            }

            // Get uploaded files
            $documents = [
                'id_document' => $request->file('id_document'),
                'driving_license' => $request->file('driving_license'),
                'vehicle_registration' => $request->file('vehicle_registration'),
            ];

            $application = $this->applicationService->submitApplication($user, $data, $documents);

            return response()->json([
                'success' => true,
                'message' => 'Driver application submitted successfully. It will be reviewed by our team.',
                'data' => new DriverApplicationResource($application),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to submit driver application', [
                'user_id' => auth()->id(),
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
     *     path="/api/driver-applications/my-application",
     *     tags={"Driver Applications"},
     *     summary="Get user's driver application",
     *     description="Get the current user's driver application status",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Application retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="No application found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function myApplication(): JsonResponse
    {
        try {
            $user = auth()->user();
            $application = $this->applicationService->getUserApplication($user->id);

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'No driver application found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Application retrieved successfully',
                'data' => new DriverApplicationResource($application),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve driver application', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve application',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/driver-applications/eligibility",
     *     tags={"Driver Applications"},
     *     summary="Check driver application eligibility",
     *     description="Check if the current user can apply to become a driver",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility checked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="can_apply", type="boolean", example=true),
     *                 @OA\Property(property="reason", type="string", example="You can apply to become a driver")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function checkEligibility(): JsonResponse
    {
        try {
            $user = auth()->user();
            $eligibility = $this->applicationService->canUserApply($user);

            return response()->json([
                'success' => true,
                'message' => 'Eligibility checked successfully',
                'data' => $eligibility,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check driver eligibility', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check eligibility',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
