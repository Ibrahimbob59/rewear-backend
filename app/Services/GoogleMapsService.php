<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GoogleMapsService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key');

        if (!$this->apiKey) {
            throw new \Exception('Google Maps API key not configured');
        }
    }

    /**
     * Calculate driving distance between two coordinates
     *
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @return array ['distance_km' => float, 'duration_minutes' => int, 'delivery_fee' => float]
     * @throws \Exception
     */
    public function calculateDistance(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        // Create cache key for this route
        $cacheKey = "route_" . md5("{$fromLat},{$fromLng}_{$toLat},{$toLng}");

        // Check cache first (valid for 24 hours)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Validate coordinates
            $this->validateCoordinates($fromLat, $fromLng, $toLat, $toLng);

            // Prepare request payload
            $payload = [
                'origin' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $fromLat,
                            'longitude' => $fromLng
                        ]
                    ]
                ],
                'destination' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $toLat,
                            'longitude' => $toLng
                        ]
                    ]
                ],
                'travelMode' => 'DRIVE',
                'routingPreference' => 'TRAFFIC_AWARE',
                'computeAlternativeRoutes' => false,
                'routeModifiers' => [
                    'avoidTolls' => false,
                    'avoidHighways' => false,
                    'avoidFerries' => false
                ],
                'languageCode' => 'en-US',
                'units' => 'METRIC'
            ];

            // Make API request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline'
            ])->post($this->baseUrl, $payload);

            if (!$response->successful()) {
                throw new \Exception('Google Maps API request failed: ' . $response->body());
            }

            $data = $response->json();

            // Extract route information
            if (empty($data['routes'])) {
                throw new \Exception('No routes found between the specified locations');
            }

            $route = $data['routes'][0];
            $distanceMeters = $route['distanceMeters'] ?? 0;
            $duration = $route['duration'] ?? '0s';

            // Convert to kilometers
            $distanceKm = round($distanceMeters / 1000, 2);

            // Parse duration (format: "1234s")
            $durationSeconds = (int)str_replace('s', '', $duration);
            $durationMinutes = round($durationSeconds / 60);

            // Calculate delivery fee: (distance_km ÷ 4) × $1
            $deliveryFee = round($distanceKm / 4, 2);

            // Minimum delivery fee: $1
            $deliveryFee = max($deliveryFee, 1.00);

            $result = [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'delivery_fee' => $deliveryFee,
                'encoded_polyline' => $route['polyline']['encodedPolyline'] ?? null,
                'distance_meters' => $distanceMeters,
                'duration_seconds' => $durationSeconds
            ];

            // Cache result for 24 hours
            Cache::put($cacheKey, $result, now()->addHours(24));

            Log::info('Google Maps route calculated', [
                'from' => "{$fromLat},{$fromLng}",
                'to' => "{$toLat},{$toLng}",
                'distance_km' => $distanceKm,
                'delivery_fee' => $deliveryFee
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Google Maps API error', [
                'from' => "{$fromLat},{$fromLng}",
                'to' => "{$toLat},{$toLng}",
                'error' => $e->getMessage()
            ]);

            // Fallback to Haversine calculation
            return $this->fallbackDistanceCalculation($fromLat, $fromLng, $toLat, $toLng);
        }
    }

    /**
     * Calculate delivery fee from distance
     *
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @return float
     */
    public function calculateDeliveryFee(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $routeData = $this->calculateDistance($fromLat, $fromLng, $toLat, $toLng);
        return $routeData['delivery_fee'];
    }

    /**
     * Validate coordinates
     *
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @throws \Exception
     */
    protected function validateCoordinates(float $fromLat, float $fromLng, float $toLat, float $toLng): void
    {
        if ($fromLat < -90 || $fromLat > 90 || $toLat < -90 || $toLat > 90) {
            throw new \Exception('Invalid latitude values. Must be between -90 and 90.');
        }

        if ($fromLng < -180 || $fromLng > 180 || $toLng < -180 || $toLng > 180) {
            throw new \Exception('Invalid longitude values. Must be between -180 and 180.');
        }

        // Check if coordinates are the same (no delivery needed)
        if (abs($fromLat - $toLat) < 0.001 && abs($fromLng - $toLng) < 0.001) {
            throw new \Exception('Pickup and delivery locations cannot be the same.');
        }
    }

    /**
     * Fallback distance calculation using Haversine formula
     *
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @return array
     */
    protected function fallbackDistanceCalculation(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        Log::warning('Using fallback Haversine distance calculation');

        $earthRadius = 6371; // km

        $dLat = deg2rad($toLat - $fromLat);
        $dLng = deg2rad($toLng - $fromLng);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distanceKm = round($earthRadius * $c, 2);

        // Estimate duration (assuming 40 km/h average speed in city)
        $durationMinutes = round(($distanceKm / 40) * 60);

        // Calculate delivery fee
        $deliveryFee = round($distanceKm / 4, 2);
        $deliveryFee = max($deliveryFee, 1.00);

        return [
            'distance_km' => $distanceKm,
            'duration_minutes' => $durationMinutes,
            'delivery_fee' => $deliveryFee,
            'encoded_polyline' => null,
            'fallback_calculation' => true
        ];
    }

    /**
     * Get estimated delivery time
     *
     * @param float $distanceKm
     * @return int Minutes
     */
    public function getEstimatedDeliveryTime(float $distanceKm): int
    {
        // Base time: 15 minutes pickup + delivery
        // Travel time: distance / 30 km/h average speed
        // Buffer: +20% for traffic

        $travelMinutes = ($distanceKm / 30) * 60;
        $totalMinutes = 15 + $travelMinutes;
        $withBuffer = $totalMinutes * 1.2;

        return (int)round($withBuffer);
    }
}
