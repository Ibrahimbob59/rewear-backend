<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Google Distance Matrix Service
 * 
 * Calculates distance and duration between two locations
 * Uses Google Distance Matrix API
 * 
 * Setup Required:
 * 1. Add to .env:
 *    GOOGLE_MAPS_API_KEY=your-api-key
 * 
 * 2. Enable in Google Cloud Console:
 *    - Distance Matrix API
 *    - Restrict API key to your domains/IP addresses
 * 
 * Official Docs: https://developers.google.com/maps/documentation/distance-matrix
 */
class DistanceCalculatorService
{
    private string $apiKey;
    private string $baseUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');

        if (empty($this->apiKey)) {
            throw new Exception('Google Maps API key not configured');
        }
    }

    /**
     * Calculate distance and delivery fee between two locations
     * 
     * @param float $originLat Origin latitude
     * @param float $originLng Origin longitude
     * @param float $destLat Destination latitude
     * @param float $destLng Destination longitude
     * @return array Contains: distance_km, distance_text, duration_text, delivery_fee, driver_earnings, platform_commission
     * @throws Exception
     */
    public function calculateDeliveryFee(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        // Get distance from Google API
        $distance = $this->getDistance($originLat, $originLng, $destLat, $destLng);

        if (!$distance) {
            throw new Exception('Unable to calculate distance');
        }

        // Calculate delivery fee using ReWear formula: (distance_km ÷ 4) × $1
        $deliveryFee = $this->calculateFee($distance['distance_km']);

        return [
            'distance_km' => $distance['distance_km'],
            'distance_text' => $distance['distance_text'],
            'duration_text' => $distance['duration_text'],
            'delivery_fee' => $deliveryFee,
            'driver_earnings' => round($deliveryFee * 0.75, 2), // Driver gets 75%
            'platform_commission' => round($deliveryFee * 0.25, 2), // Platform gets 25%
        ];
    }

    /**
     * Get distance between two points using Google Distance Matrix API
     * 
     * @param float $originLat
     * @param float $originLng
     * @param float $destLat
     * @param float $destLng
     * @return array|null
     * @throws Exception
     */
    public function getDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?array {
        // Create cache key for this route
        $cacheKey = "distance_{$originLat}_{$originLng}_{$destLat}_{$destLng}";

        // Check cache (24 hours)
        return Cache::remember($cacheKey, 86400, function () use ($originLat, $originLng, $destLat, $destLng) {
            try {
                // Build request
                $response = Http::get($this->baseUrl, [
                    'origins' => "{$originLat},{$originLng}",
                    'destinations' => "{$destLat},{$destLng}",
                    'mode' => 'driving', // driving, walking, bicycling, transit
                    'units' => 'metric', // metric or imperial
                    'key' => $this->apiKey,
                ]);

                if (!$response->successful()) {
                    throw new Exception('Google API request failed');
                }

                $data = $response->json();

                // Check API status
                if ($data['status'] !== 'OK') {
                    throw new Exception('Google API error: ' . $data['status']);
                }

                // Extract distance data
                $element = $data['rows'][0]['elements'][0];

                if ($element['status'] !== 'OK') {
                    throw new Exception('Route not found');
                }

                return [
                    'distance_meters' => $element['distance']['value'],
                    'distance_km' => round($element['distance']['value'] / 1000, 2),
                    'distance_text' => $element['distance']['text'],
                    'duration_seconds' => $element['duration']['value'],
                    'duration_text' => $element['duration']['text'],
                ];

            } catch (Exception $e) {
                report($e);
                
                // Fallback to Haversine formula if Google API fails
                return $this->calculateHaversineDistance($originLat, $originLng, $destLat, $destLng);
            }
        });
    }

    /**
     * Calculate delivery fee using ReWear formula
     * 
     * Formula: (distance_km ÷ 4) × $1
     * 
     * Examples:
     * - 4 km = $1.00
     * - 8 km = $2.00
     * - 10 km = $2.50
     * - 20 km = $5.00
     * 
     * Minimum fee: $1.00
     * 
     * @param float $distanceKm
     * @return float
     */
    private function calculateFee(float $distanceKm): float
    {
        $fee = ($distanceKm / 4) * 1;
        
        // Minimum delivery fee is $1
        return round(max($fee, 1.00), 2);
    }

    /**
     * Fallback: Calculate distance using Haversine formula
     * 
     * Used when Google API is unavailable
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return array
     */
    private function calculateHaversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): array {
        $earthRadius = 6371; // Earth's radius in kilometers

        // Convert to radians
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        // Haversine formula
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlng / 2) * sin($dlng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceKm = round($earthRadius * $c, 2);

        // Estimate duration (assume 40 km/h average speed)
        $durationMinutes = round(($distanceKm / 40) * 60);

        return [
            'distance_meters' => $distanceKm * 1000,
            'distance_km' => $distanceKm,
            'distance_text' => "{$distanceKm} km",
            'duration_seconds' => $durationMinutes * 60,
            'duration_text' => "{$durationMinutes} mins",
            'fallback_used' => true, // Indicates Haversine was used
        ];
    }

    /**
     * Batch calculate distances for multiple destinations
     * 
     * Useful for "items nearby" feature
     * 
     * @param float $originLat
     * @param float $originLng
     * @param array $destinations Array of ['lat' => float, 'lng' => float, 'id' => int]
     * @return array Array of distances indexed by destination ID
     */
    public function batchCalculateDistances(float $originLat, float $originLng, array $destinations): array
    {
        $results = [];

        foreach ($destinations as $dest) {
            try {
                $distance = $this->getDistance(
                    $originLat,
                    $originLng,
                    $dest['lat'],
                    $dest['lng']
                );

                $results[$dest['id']] = $distance;
            } catch (Exception $e) {
                // Skip if distance calculation fails
                continue;
            }
        }

        return $results;
    }

    /**
     * Check if a location is within delivery radius
     * 
     * @param float $originLat
     * @param float $originLng
     * @param float $destLat
     * @param float $destLng
     * @param float $maxRadiusKm Maximum delivery radius in km (default: 50 km)
     * @return bool
     */
    public function isWithinDeliveryRadius(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng,
        float $maxRadiusKm = 50
    ): bool {
        try {
            $distance = $this->getDistance($originLat, $originLng, $destLat, $destLng);
            return $distance && $distance['distance_km'] <= $maxRadiusKm;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get estimated delivery time window
     * 
     * @param float $distanceKm
     * @return array Start and end time estimates
     */
    public function getDeliveryTimeWindow(float $distanceKm): array
    {
        // Base delivery time + distance factor
        // Assumes: 30 min prep time + travel time (40 km/h) + 15 min buffer
        $travelTimeMinutes = ($distanceKm / 40) * 60;
        $totalMinutes = 30 + $travelTimeMinutes + 15;

        return [
            'min_minutes' => round($totalMinutes * 0.8), // -20% for best case
            'max_minutes' => round($totalMinutes * 1.3), // +30% for worst case
            'min_time' => now()->addMinutes(round($totalMinutes * 0.8))->format('H:i'),
            'max_time' => now()->addMinutes(round($totalMinutes * 1.3))->format('H:i'),
        ];
    }
}