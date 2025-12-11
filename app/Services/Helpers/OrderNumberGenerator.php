<?php

namespace App\Services\Helpers;

use App\Models\Order;
use Illuminate\Support\Str;

class OrderNumberGenerator
{
    /**
     * Generate a unique order number
     * Format: RW-YYYYMMDD-XXXXX
     * Example: RW-20251207-A1B2C
     * 
     * @return string
     */
    public static function generate(): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $orderNumber = self::generateNumber();
            $exists = Order::where('order_number', $orderNumber)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            // Fallback: add timestamp
            $orderNumber .= '-' . time();
        }

        return $orderNumber;
    }

    /**
     * Generate the order number string
     * 
     * @return string
     */
    protected static function generateNumber(): string
    {
        $prefix = 'RW'; // ReWear
        $date = now()->format('Ymd'); // YYYYMMDD
        $random = strtoupper(Str::random(5)); // 5 random alphanumeric characters

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Parse order number to extract components
     * 
     * @param string $orderNumber
     * @return array|null
     */
    public static function parse(string $orderNumber): ?array
    {
        // Pattern: RW-YYYYMMDD-XXXXX
        $pattern = '/^(RW)-(\d{8})-([A-Z0-9]{5})$/';
        
        if (preg_match($pattern, $orderNumber, $matches)) {
            return [
                'prefix' => $matches[1],
                'date' => $matches[2],
                'random' => $matches[3],
            ];
        }

        return null;
    }

    /**
     * Validate order number format
     * 
     * @param string $orderNumber
     * @return bool
     */
    public static function validate(string $orderNumber): bool
    {
        return self::parse($orderNumber) !== null;
    }

    /**
     * Get order date from order number
     * 
     * @param string $orderNumber
     * @return \Carbon\Carbon|null
     */
    public static function getOrderDate(string $orderNumber): ?\Carbon\Carbon
    {
        $parsed = self::parse($orderNumber);
        
        if (!$parsed) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Ymd', $parsed['date']);
        } catch (\Exception $e) {
            return null;
        }
    }
}