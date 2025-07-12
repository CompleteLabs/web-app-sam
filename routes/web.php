<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return redirect('admin');
});

// Add login route that redirects to Filament login
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// Health Check Endpoint for Performance Monitoring - SUPER ADMIN Only
Route::get('/health-check', function () {
    // 🔒 AUTHENTICATION: Require login with SUPER ADMIN role
    if (!Auth::check()) {
        return response()->json([
            'status' => 'unauthorized',
            'message' => 'Authentication required'
        ], 401);
    }

    if (!Auth::user()->role || Auth::user()->role->name !== 'SUPER ADMIN') {
        return response()->json([
            'status' => 'forbidden',
            'message' => 'Access restricted to SUPER ADMIN only'
        ], 403);
    }

    $startTime = microtime(true);

    try {
        $checks = [
            'timestamp' => now()->toISOString(),
            'database' => checkDatabase(),
            'queue' => checkQueue(),
            'memory' => checkMemory(),
            'disk' => checkDisk(),
            'response_time_ms' => 0 // Will be calculated below
        ];

        $checks['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        // Determine overall status
        $healthyServices = collect($checks)
            ->except(['timestamp', 'response_time_ms'])
            ->filter(fn($status) => $status === 'healthy')
            ->count();

        $totalServices = collect($checks)->except(['timestamp', 'response_time_ms'])->count();
        $overallStatus = $healthyServices === $totalServices ? 'healthy' : 'unhealthy';

        return response()->json([
            'status' => $overallStatus,
            'services_healthy' => "{$healthyServices}/{$totalServices}",
            'checks' => $checks,
            'recommendations' => getRecommendations($checks),
            'checked_by' => Auth::user()->name // Show who performed the check
        ], $overallStatus === 'healthy' ? 200 : 503);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Health check failed',
            'error' => $e->getMessage()
        ], 500);
    }
})->middleware(['web', 'auth']); // Ensure web auth middleware

// Simple public ping endpoint for external monitoring (Pingdom, StatusCake, etc)
Route::get('/ping', function () {
    try {
        // Minimal check - just database connectivity
        DB::select('SELECT 1');
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString()
        ], 200);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'timestamp' => now()->toISOString()
        ], 503);
    }
});

if (!function_exists('checkDatabase')) {
    function checkDatabase()
    {
        try {
            $queryStart = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1 as health_check');
            $duration = (microtime(true) - $queryStart) * 1000;

            if ($duration > 1000) return 'critical'; // > 1 second
            if ($duration > 500) return 'slow';      // > 500ms
            return 'healthy';
        } catch (Exception $e) {
            return 'failed';
        }
    }
}

if (!function_exists('checkQueue')) {
    function checkQueue()
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            if ($pendingJobs > 2000 || $failedJobs > 50) return 'critical';
            if ($pendingJobs > 1000 || $failedJobs > 20) return 'warning';
            return 'healthy';
        } catch (Exception $e) {
            return 'failed';
        }
    }
}

if (!function_exists('checkMemory')) {
    function checkMemory()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = convertToBytes(ini_get('memory_limit'));

        if ($memoryLimit > 0) {
            $usagePercent = ($memoryUsage / $memoryLimit) * 100;
            if ($usagePercent > 90) return 'critical';
            if ($usagePercent > 80) return 'warning';
        }

        // Also check absolute memory usage
        $memoryMB = $memoryUsage / 1024 / 1024;
        if ($memoryMB > 512) return 'warning';

        return 'healthy';
    }
}

if (!function_exists('checkDisk')) {
    function checkDisk()
    {
        try {
            $rootPath = '/';
            if (PHP_OS_FAMILY === 'Windows') {
                $rootPath = 'C:';
            }

            $freeBytes = disk_free_space($rootPath);
            $totalBytes = disk_total_space($rootPath);

            if ($freeBytes && $totalBytes) {
                $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
                if ($usagePercent > 95) return 'critical';
                if ($usagePercent > 85) return 'warning';
            }

            return 'healthy';
        } catch (Exception $e) {
            return 'unknown';
        }
    }
}

if (!function_exists('getRecommendations')) {
    function getRecommendations($checks)
    {
        $recommendations = [];

        if ($checks['database'] !== 'healthy') {
            $recommendations[] = 'Database performance issue detected. Check slow queries in Telescope.';
        }

        if ($checks['queue'] !== 'healthy') {
            $recommendations[] = 'Queue backlog detected. Consider increasing workers or optimizing jobs.';
        }

        if ($checks['memory'] !== 'healthy') {
            $recommendations[] = 'High memory usage. Consider restarting Octane workers: php artisan octane:reload';
        }

        if ($checks['disk'] !== 'healthy') {
            $recommendations[] = 'Low disk space. Clean up logs and temporary files.';
        }

        if ($checks['response_time_ms'] > 500) {
            $recommendations[] = 'Health check response time is slow. Server may be under load.';
        }

        return empty($recommendations) ? ['All systems operating normally'] : $recommendations;
    }
}

if (!function_exists('convertToBytes')) {
    function convertToBytes($val)
    {
        if (empty($val)) return 0;

        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
