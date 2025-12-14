<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Auth\JWTGuard;
use App\Services\TokenService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::extend('jwt', function ($app, $name, array $config) {
            return new JWTGuard(
                Auth::createUserProvider($config['provider']),
                $app['request'],
                $app->make(TokenService::class)
            );
        });
    }
}
