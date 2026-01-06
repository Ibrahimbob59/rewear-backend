<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleMapsController extends Controller
{
    protected GoogleMapsService $googleMapsService;

    public function __construct(GoogleMapsService $googleMapsService)
    {
        $this->googleMapsService = $googleMapsService;
    }

    /**
     * @OA\Post(
     *     path="/api/maps/calculate-delivery-fee",
     *     tags={"Maps"},
     *     summary="Calculate delivery fee",
     *     description="Calculate delivery fee based on pickup and delivery coordinates",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pickup_latitude","pickup_longitude","delivery_latitude","delivery_longitude"},
     *             @OA\Property(property="pickup_latitude", type="number", format="float", example=33.8886, description="Pickup location latitude"),
     *             @OA\Property(property="pickup_longitude", type="number", format="float", example=35.4955, description="Pickup location longitude"),
     *             @OA\Property(property="delivery_latitude", type="number", format="float", example=33.8938, description="Delivery location latitude"),
     *             @OA\Property(property="delivery_longitude", type="number", format="float", example=35.5018, description="Delivery location longitude")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery fee calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery fee calculated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="distance_km", type="number", example=2.45),
     *                 @OA\Property(property="duration_minutes", type="integer", example=12),
     *                 @OA\Property(property="delivery_fee", type="number", example=2.50),
     *                 @OA\Property(property="driver_earning", type="number", example=1.88),
     *                 @OA\Property(property="platform_fee", type="number", example=0.62),
     *                 @OA\Property(property="estimated_delivery_time", type="integer", example=45)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid coordinates or calculation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function calculateDeliveryFee(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'pickup_latitude' => 'required|numeric|between:-90,90',
                'pickup_longitude' => 'required|numeric|between:-180,180',
                'delivery_latitude' => 'required|numeric|between:-90,90',
                'delivery_longitude' => 'required|numeric|between:-180,180',
            ]);

            $pickupLat = $request->input('pickup_latitude');
            $pickupLng = $request->input('pickup_longitude');
            $deliveryLat = $request->input('delivery_latitude');
            $deliveryLng = $request->input('delivery_longitude');

            // Calculate route using Google Maps service
            $routeData = $this->googleMapsService->calculateDistance(
                $pickupLat,
                $pickupLng,
                $deliveryLat,
                $deliveryLng
            );

            // Calculate driver and platform earnings
            $driverEarning = round($routeData['delivery_fee'] * 0.75, 2);
            $platformFee = round($routeData['delivery_fee'] * 0.25, 2);

            // Get estimated delivery time
            $estimatedDeliveryTime = $this->googleMapsService->getEstimatedDeliveryTime(
                $routeData['distance_km']
            );

            return response()->json([
                'success' => true,
                'message' => 'Delivery fee calculated successfully',
                'data' => [
                    'distance_km' => $routeData['distance_km'],
                    'duration_minutes' => $routeData['duration_minutes'],
                    'delivery_fee' => $routeData['delivery_fee'],
                    'driver_earning' => $driverEarning,
                    'platform_fee' => $platformFee,
                    'estimated_delivery_time' => $estimatedDeliveryTime,
                    'route_info' => [
                        'encoded_polyline' => $routeData['encoded_polyline'] ?? null,
                        'fallback_calculation' => $routeData['fallback_calculation'] ?? false,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate delivery fee', [
                'user_id' => auth()->id(),
                'pickup_coordinates' => "{$request->input('pickup_latitude')},{$request->input('pickup_longitude')}",
                'delivery_coordinates' => "{$request->input('delivery_latitude')},{$request->input('delivery_longitude')}",
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
     *     path="/api/maps/validate-coordinates",
     *     tags={"Maps"},
     *     summary="Validate coordinates",
     *     description="Validate if coordinates are valid and accessible",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"latitude","longitude"},
     *             @OA\Property(property="latitude", type="number", format="float", example=33.8886),
     *             @OA\Property(property="longitude", type="number", format="float", example=35.4955)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Coordinates validated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coordinates are valid"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="is_valid", type="boolean", example=true),
     *                 @OA\Property(property="latitude", type="number", example=33.8886),
     *                 @OA\Property(property="longitude", type="number", example=35.4955),
     *                 @OA\Property(property="location_info", type="string", example="Beirut, Lebanon")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid coordinates"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function validateCoordinates(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');

            // Basic validation
            $isValid = true;
            $message = 'Coordinates are valid';

            // Check if coordinates are not in the ocean or invalid areas
            // This is a simplified check - in production you might use more sophisticated validation
            if ($latitude == 0 && $longitude == 0) {
                $isValid = false;
                $message = 'Invalid coordinates (null island)';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'is_valid' => $isValid,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_info' => $isValid ? 'Valid coordinates' : 'Invalid location',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to validate coordinates', [
                'user_id' => auth()->id(),
                'coordinates' => "{$request->input('latitude')},{$request->input('longitude')}",
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid coordinates provided',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/maps/service-areas",
     *     tags={"Maps"},
     *     summary="Get service areas",
     *     description="Get list of supported service areas for delivery",
     *     @OA\Response(
     *         response=200,
     *         description="Service areas retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="supported_areas", type="array", @OA\Items(type="object",
     *                     @OA\Property(property="name", type="string", example="Beirut"),
     *                     @OA\Property(property="country", type="string", example="Lebanon"),
     *                     @OA\Property(property="center_latitude", type="number", example=33.8886),
     *                     @OA\Property(property="center_longitude", type="number", example=35.4955),
     *                     @OA\Property(property="radius_km", type="integer", example=25)
     *                 )),
     *                 @OA\Property(property="min_delivery_fee", type="number", example=1.00),
     *                 @OA\Property(property="max_delivery_distance_km", type="integer", example=50)
     *             )
     *         )
     *     )
     * )
     */
    public function serviceAreas(): JsonResponse
    {
        try {
            // Define supported service areas (this could be in config or database)
            $supportedAreas = [
                [
                    'name' => 'Beirut',
                    'country' => 'Lebanon',
                    'center_latitude' => 33.8886,
                    'center_longitude' => 35.4955,
                    'radius_km' => 25,
                ],
                [
                    'name' => 'Tripoli',
                    'country' => 'Lebanon',
                    'center_latitude' => 34.4361,
                    'center_longitude' => 35.8497,
                    'radius_km' => 20,
                ],
                [
                    'name' => 'Sidon',
                    'country' => 'Lebanon',
                    'center_latitude' => 33.5543,
                    'center_longitude' => 35.3781,
                    'radius_km' => 15,
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Service areas retrieved successfully',
                'data' => [
                    'supported_areas' => $supportedAreas,
                    'min_delivery_fee' => 1.00,
                    'max_delivery_distance_km' => 50,
                    'delivery_fee_formula' => 'Distance (km) ÷ 4 × $1.00 (minimum $1.00)',
                    'driver_commission' => '75%',
                    'platform_commission' => '25%',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve service areas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service areas',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
