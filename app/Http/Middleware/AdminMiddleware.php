<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        // Check if user has admin role
        if (!$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator access required',
                'data' => [
                    'required_role' => 'admin',
                    'user_roles' => $user->getRoleNames()->toArray(),
                ],
            ], 403);
        }

        return $next($request);
    }
}
