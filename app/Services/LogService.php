<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\AdminLog;

class LogService
{
    /**
     * Log admin actions to database and file
     *
     * @param int $adminId
     * @param string $action
     * @param string $description
     * @param array $metadata
     * @return void
     */
    public function logAdminAction(int $adminId, string $action, string $description, array $metadata = []): void
    {
        // Log to database
        try {
            AdminLog::create([
                'admin_id' => $adminId,
                'action' => $action,
                'description' => $description,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => json_encode($metadata),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log admin action to database', [
                'admin_id' => $adminId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }

        // Log to file
        Log::channel('admin')->info($description, [
            'admin_id' => $adminId,
            'action' => $action,
            'metadata' => $metadata,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log authentication events
     *
     * @param string $event
     * @param array $data
     * @return void
     */
    public function logAuthEvent(string $event, array $data = []): void
    {
        Log::channel('auth')->info($event, array_merge($data, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]));
    }

    /**
     * Log security events
     *
     * @param string $event
     * @param array $data
     * @param string $level (info, warning, error, critical)
     * @return void
     */
    public function logSecurityEvent(string $event, array $data = [], string $level = 'warning'): void
    {
        Log::channel('security')->{$level}($event, array_merge($data, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
        ]));
    }

    /**
     * Log API requests
     *
     * @param string $method
     * @param string $endpoint
     * @param int $statusCode
     * @param float $duration
     * @param array $metadata
     * @return void
     */
    public function logApiRequest(
        string $method,
        string $endpoint,
        int $statusCode,
        float $duration,
        array $metadata = []
    ): void {
        Log::channel('api')->info('API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log errors with context
     *
     * @param \Exception $exception
     * @param array $context
     * @return void
     */
    public function logError(\Exception $exception, array $context = []): void
    {
        Log::error($exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'ip' => request()->ip(),
            'url' => request()->fullUrl(),
        ]);
    }
}
