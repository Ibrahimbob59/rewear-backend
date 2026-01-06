<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifiedDriver
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            Log::warning('Unauthorized access attempt to driver route', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please login first.',
            ], 401);
        }

        $user = auth()->user();

        // Check if user has driver role
        if (!$user->hasRole('driver')) {
            Log::warning('Non-driver user attempted to access driver route', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Driver access required.',
            ], 403);
        }

        // Check if driver is verified
        if (!$user->driver_verified) {
            Log::warning('Unverified driver attempted to access verified driver route', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your driver account is not verified yet. Please wait for approval.',
            ], 403);
        }

        return $next($request);
    }
}
