<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ReWear Application Settings
    |--------------------------------------------------------------------------
    */

    'app_name' => env('APP_NAME', 'ReWear'),

    /*
    |--------------------------------------------------------------------------
    | Email Verification Settings
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'code_length' => env('VERIFICATION_CODE_LENGTH', 6),
        'code_expiry' => env('VERIFICATION_CODE_EXPIRY', 5), // minutes
        'max_attempts' => env('MAX_VERIFICATION_ATTEMPTS', 5),
        'time_window' => env('VERIFICATION_TIME_WINDOW', 15), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'registration_per_email' => env('REGISTRATION_RATE_LIMIT', 5),
        'registration_per_ip' => env('REGISTRATION_RATE_LIMIT', 5),
        'login_attempts' => env('LOGIN_ATTEMPT_LIMIT', 5),
        'lockout_time' => env('LOGIN_LOCKOUT_TIME', 15), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Token Settings
    |--------------------------------------------------------------------------
    */

    'jwt' => [
        'ttl' => env('JWT_TTL', 60), // minutes (1 hour)
        'refresh_ttl' => env('JWT_REFRESH_TTL', 43200), // minutes (30 days)
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Settings
    |--------------------------------------------------------------------------
    */

    'delivery' => [
        'fee_per_unit' => 1, // $1 per unit
        'distance_divisor' => 4, // km / 4 = units
        'driver_commission' => 0.75, // 75%
        'platform_commission' => 0.25, // 25%
    ],

];
