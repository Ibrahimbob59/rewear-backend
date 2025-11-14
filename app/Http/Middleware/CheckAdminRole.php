<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckAdminRole
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
            Log::warning('Unauthorized access attempt to admin route', [
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

        // Check if user has admin role
        if (!$user->hasRole('admin')) {
            Log::warning('Non-admin user attempted to access admin route', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        // Log admin access
        Log::info('Admin accessed route', [
            'admin_id' => $user->id,
            'admin_email' => $user->email,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        return $next($request);
    }
}
