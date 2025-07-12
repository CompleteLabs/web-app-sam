<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     *
     * Monitor request performance and log slow requests to prevent
     * "loading forever" issues and server overload.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $response = $next($request);

        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = memory_get_usage() - $startMemory;

        // Log performance metrics based on severity
        $this->logPerformanceMetrics($request, $duration, $memoryUsed);

        return $response;
    }

    /**
     * Log performance metrics based on severity levels
     */
    private function logPerformanceMetrics(Request $request, float $duration, int $memoryUsed): void
    {
        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'duration_ms' => round($duration, 2),
            'memory_mb' => round($memoryUsed / 1024 / 1024, 2),
            'user_id' => Auth::check() ? Auth::id() : null,
            'ip' => $request->ip(),
            'timestamp' => now()->toISOString(),
        ];

        // CRITICAL: > 5 seconds (likely to cause timeout)
        if ($duration > 5000) {
            Log::critical('CRITICAL: Request timeout risk', array_merge($context, [
                'alert' => 'IMMEDIATE_ACTION_REQUIRED',
                'suggestion' => 'Check for database locks, infinite loops, or resource exhaustion'
            ]));
        }
        // ERROR: > 3 seconds (very slow, user will notice)
        elseif ($duration > 3000) {
            Log::error('ERROR: Very slow request', array_merge($context, [
                'alert' => 'HIGH_PRIORITY',
                'suggestion' => 'Optimize database queries or move to background processing'
            ]));
        }
        // WARNING: > 1 second (slow, affects UX)
        elseif ($duration > 1000) {
            Log::warning('WARNING: Slow request detected', array_merge($context, [
                'alert' => 'MEDIUM_PRIORITY',
                'suggestion' => 'Consider query optimization or caching'
            ]));
        }
        // INFO: > 500ms (monitor for trends)
        elseif ($duration > 500) {
            Log::info('INFO: Request performance monitor', $context);
        }

        // Memory alerts
        if ($memoryUsed > 50 * 1024 * 1024) { // > 50MB
            Log::warning('High memory usage detected', array_merge($context, [
                'memory_alert' => 'HIGH_MEMORY_USAGE',
                'suggestion' => 'Check for memory leaks or large dataset processing'
            ]));
        }
    }
}
