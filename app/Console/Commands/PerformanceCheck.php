<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PerformanceCheck extends Command
{
    protected $signature = 'app:performance-check {--detailed : Show detailed analysis}';
    protected $description = 'Quick performance and health check to prevent server issues';

    public function handle()
    {
        $this->info('🚀 Laravel Performance Health Check');
        $this->info('📅 ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        $issues = [];

        // Database Performance
        $this->checkDatabase($issues);

        // Queue Status
        $this->checkQueue($issues);

        // Memory Usage
        $this->checkMemory($issues);

        // Cache Performance
        $this->checkCache($issues);

        // Octane Status
        $this->checkOctane($issues);

        // Summary
        $this->newLine();

        // Detailed dataset analysis
        if ($this->option('detailed')) {
            $this->showDetailedAnalysis();
        }

        if (empty($issues)) {
            $this->info('✅ All systems healthy! Performance looks good.');

            // Show proactive recommendations for large datasets
            $outletCount = DB::table('outlets')->count();
            $userCount = DB::table('users')->count();

            if ($outletCount > 10000 || $userCount > 500) {
                $this->newLine();
                $this->info('💡 Proactive Recommendations for Large Dataset:');

                if ($outletCount > 10000) {
                    $this->line("   • Consider pagination limits <50 for outlet lists ({$outletCount} outlets)");
                    $this->line("   • Implement region-based filtering to reduce query scope");
                    $this->line("   • Cache frequently accessed outlet data");
                }

                if ($userCount > 500) {
                    $this->line("   • Monitor concurrent user sessions");
                    $this->line("   • Consider implementing user-based query limits");
                }

                $this->line('   • Schedule regular database maintenance');
                $this->line('   • Monitor peak hours for performance patterns');
            }
        } else {
            $this->error('⚠️  Issues detected that may affect performance:');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
            $this->newLine();
            $this->info('💡 Recommendations:');
            $this->line('   • Monitor these issues in Telescope dashboard');
            $this->line('   • Check health endpoint: /health-check');
            $this->line('   • Restart Octane if memory issues: php artisan octane:reload');
        }
    }

    private function checkDatabase(&$issues)
    {
        $this->info('📊 Database Performance:');

        try {
            $start = microtime(true);
            $outletCount = DB::table('outlets')->count();
            $userCount = DB::table('users')->count();
            $dbTime = round((microtime(true) - $start) * 1000, 2);

            $this->line("   Outlets: {$outletCount}");
            $this->line("   Users: {$userCount}");
            $this->line("   Query time: {$dbTime}ms " .
                ($dbTime > 500 ? '⚠️  SLOW' : '✅ OK'));

            if ($dbTime > 500) {
                $issues[] = "Database queries are slow ({$dbTime}ms). Check indexes and optimize queries.";
            }

            // Check for failed jobs
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 10) {
                $this->line("   Failed jobs: {$failedJobs} ⚠️  HIGH");
                $issues[] = "High number of failed jobs ({$failedJobs}). Check job reliability.";
            } else {
                $this->line("   Failed jobs: {$failedJobs} ✅ OK");
            }

        } catch (\Exception $e) {
            $this->line("   Status: ❌ FAILED - " . $e->getMessage());
            $issues[] = "Database connection failed: " . $e->getMessage();
        }

        $this->newLine();
    }

    private function checkQueue(&$issues)
    {
        $this->info('📋 Queue Status:');

        try {
            $pendingJobs = DB::table('jobs')->count();
            $this->line("   Pending jobs: {$pendingJobs} " .
                ($pendingJobs > 500 ? '⚠️  HIGH' : '✅ OK'));

            if ($pendingJobs > 500) {
                $issues[] = "High queue backlog ({$pendingJobs} jobs). Consider increasing workers.";
            }

            // Check for stuck jobs (older than 1 hour)
            $stuckJobs = DB::table('jobs')
                ->where('created_at', '<', now()->subHour())
                ->count();

            if ($stuckJobs > 0) {
                $this->line("   Stuck jobs: {$stuckJobs} ⚠️  ATTENTION");
                $issues[] = "Found {$stuckJobs} stuck jobs older than 1 hour.";
            } else {
                $this->line("   Stuck jobs: 0 ✅ OK");
            }

        } catch (\Exception $e) {
            $this->line("   Status: ❌ FAILED - " . $e->getMessage());
            $issues[] = "Queue check failed: " . $e->getMessage();
        }

        $this->newLine();
    }

    private function checkMemory(&$issues)
    {
        $this->info('💾 Memory Usage:');

        $currentMemory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $memoryLimit = ini_get('memory_limit');

        $this->line("   Current: {$currentMemory}MB");
        $this->line("   Peak: {$peakMemory}MB");
        $this->line("   Limit: {$memoryLimit}");

        if ($currentMemory > 200) {
            $this->line("   Status: ⚠️  HIGH USAGE");
            $issues[] = "High memory usage ({$currentMemory}MB). Consider restarting workers.";
        } else {
            $this->line("   Status: ✅ OK");
        }

        $this->newLine();
    }

    private function checkCache(&$issues)
    {
        $this->info('🗃️  Cache Performance:');

        try {
            $start = microtime(true);
            $testKey = 'performance_check_' . time();
            Cache::put($testKey, 'test_value', 60);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            $cacheTime = round((microtime(true) - $start) * 1000, 2);

            $this->line("   Cache test: {$cacheTime}ms " .
                ($cacheTime > 100 ? '⚠️  SLOW' : '✅ OK'));

            if ($cacheTime > 100) {
                $issues[] = "Cache operations are slow ({$cacheTime}ms). Check cache driver.";
            }

        } catch (\Exception $e) {
            $this->line("   Status: ❌ FAILED - " . $e->getMessage());
            $issues[] = "Cache test failed: " . $e->getMessage();
        }

        $this->newLine();
    }

    private function checkOctane(&$issues)
    {
        $this->info('⚡ Octane Status:');

        if (extension_loaded('swoole') || extension_loaded('roadrunner')) {
            $this->line("   Octane: ✅ Available");

            $maxExecutionTime = config('octane.max_execution_time', 30);
            $this->line("   Max execution time: {$maxExecutionTime}s");

            if ($maxExecutionTime > 60) {
                $issues[] = "Max execution time is high ({$maxExecutionTime}s). Consider lowering to prevent timeouts.";
            }

        } else {
            $this->line("   Octane: ⚠️  Not running or not available");
            $issues[] = "Octane is not running. Start with: php artisan octane:start";
        }

        $this->newLine();
    }

    private function showDetailedAnalysis()
    {
        $this->info('🔍 Detailed Performance Analysis:');
        $this->newLine();

        try {
            // Database table sizes and query patterns
            $tableStats = [
                'outlets' => DB::table('outlets')->count(),
                'users' => DB::table('users')->count(),
                'visits' => DB::table('visits')->count(),
                'regions' => DB::table('regions')->count(),
                'roles' => DB::table('roles')->count(),
                'jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];

            $this->line('📊 Database Table Statistics:');
            foreach ($tableStats as $table => $count) {
                $status = $this->getTableSizeStatus($table, $count);
                $this->line("   {$table}: {$count} {$status}");
            }
            $this->newLine();

            // Query performance by table
            $this->line('⚡ Individual Table Performance:');
            foreach (['outlets', 'users', 'visits'] as $table) {
                $start = microtime(true);
                DB::table($table)->limit(1)->get();
                $time = round((microtime(true) - $start) * 1000, 2);
                $status = $time > 50 ? '⚠️' : '✅';
                $this->line("   {$table} query: {$time}ms {$status}");
            }
            $this->newLine();

            // Connection and configuration info
            $this->line('🔧 System Configuration:');
            $this->line('   Database: ' . config('database.default'));
            $this->line('   Cache: ' . config('cache.default'));
            $this->line('   Queue: ' . config('queue.default'));
            $this->line('   Octane: ' . config('octane.server'));

        } catch (\Exception $e) {
            $this->line('   ❌ Error in detailed analysis: ' . $e->getMessage());
        }
    }

    private function getTableSizeStatus($table, $count)
    {
        $thresholds = [
            'outlets' => ['high' => 50000, 'medium' => 10000],
            'users' => ['high' => 2000, 'medium' => 500],
            'visits' => ['high' => 100000, 'medium' => 20000],
            'jobs' => ['high' => 1000, 'medium' => 100],
            'failed_jobs' => ['high' => 50, 'medium' => 10],
        ];

        if (!isset($thresholds[$table])) {
            return $count > 1000 ? '📊' : '✅';
        }

        $threshold = $thresholds[$table];

        if ($count > $threshold['high']) {
            return '🔴 HIGH';
        } elseif ($count > $threshold['medium']) {
            return '🟡 MEDIUM';
        } else {
            return '✅ OK';
        }
    }
}
