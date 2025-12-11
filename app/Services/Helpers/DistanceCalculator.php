<?php

namespace App\Services\Helpers;

class DistanceCalculator
{
    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in kilometers
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in kilometers
     */
    public static function calculate(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Earth's radius in kilometers
        $earthRadius = 6371;

        // Convert degrees to radians
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // Haversine formula
        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return round($distance, 2);
    }

    /**
     * Calculate distance between two coordinates and return in miles
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in miles
     */
    public static function calculateInMiles(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $km = self::calculate($lat1, $lon1, $lat2, $lon2);
        return round($km * 0.621371, 2); // Convert km to miles
    }

    /**
     * Check if point is within radius of another point
     * 
     * @param float $centerLat Center latitude
     * @param float $centerLon Center longitude
     * @param float $pointLat Point latitude
     * @param float $pointLon Point longitude
     * @param float $radiusKm Radius in kilometers
     * @return bool
     */
    public static function isWithinRadius(float $centerLat, float $centerLon, float $pointLat, float $pointLon, float $radiusKm): bool
    {
        $distance = self::calculate($centerLat, $centerLon, $pointLat, $pointLon);
        return $distance <= $radiusKm;
    }

    /**
     * Get formatted distance string
     * 
     * @param float $distanceKm
     * @return string
     */
    public static function formatDistance(float $distanceKm): string
    {
        if ($distanceKm < 1) {
            $meters = round($distanceKm * 1000);
            return "{$meters}m";
        }

        return round($distanceKm, 1) . "km";
    }

    /**
     * Calculate distance between user and item seller
     * 
     * @param \App\Models\User $user
     * @param \App\Models\Item $item
     * @return float|null Distance in km or null if coordinates missing
     */
    public static function calculateUserToItem($user, $item): ?float
    {
        if (!$user->hasLocation() || !$item->seller->hasLocation()) {
            return null;
        }

        return self::calculate(
            $user->location_lat,
            $user->location_lng,
            $item->seller->location_lat,
            $item->seller->location_lng
        );
    }

    /**
     * Calculate distance between seller and delivery address
     * 
     * @param \App\Models\User $seller
     * @param \App\Models\Address $address
     * @return float|null Distance in km or null if coordinates missing
     */
    public static function calculateSellerToAddress($seller, $address): ?float
    {
        if (!$seller->hasLocation() || !$address->hasCoordinates()) {
            return null;
        }

        return self::calculate(
            $seller->location_lat,
            $seller->location_lng,
            $address->latitude,
            $address->longitude
        );
    }
}