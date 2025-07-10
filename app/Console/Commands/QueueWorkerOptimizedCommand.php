<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QueueWorkerOptimizedCommand extends Command
{
    protected $signature = 'queue:work-optimized
                          {--memory=256 : The memory limit in megabytes}
                          {--timeout=600 : The number of seconds to wait before killing the process}
                          {--sleep=3 : The number of seconds to sleep when no job is available}
                          {--tries=3 : Number of times to attempt a job before logging it failed}
                          {--max-jobs=100 : The number of jobs to process before stopping}
                          {--max-time=3600 : The maximum number of seconds the worker should run}';

    protected $description = 'Start queue worker with optimized settings for sync operations';

    public function handle()
    {
        $memory = $this->option('memory');
        $timeout = $this->option('timeout');
        $sleep = $this->option('sleep');
        $tries = $this->option('tries');
        $maxJobs = $this->option('max-jobs');
        $maxTime = $this->option('max-time');

        $this->info("🚀 Starting optimized queue worker...");
        $this->info("Memory limit: {$memory}MB");
        $this->info("Timeout: {$timeout}s");
        $this->info("Max jobs: {$maxJobs}");
        $this->info("Max time: {$maxTime}s");
        $this->newLine();

        // Set PHP memory limit
        ini_set('memory_limit', $memory . 'M');
        ini_set('max_execution_time', $timeout + 60); // Add buffer

        // Call the optimized queue worker
        $this->call('queue:work', [
            '--memory' => $memory,
            '--timeout' => $timeout,
            '--sleep' => $sleep,
            '--tries' => $tries,
            '--max-jobs' => $maxJobs,
            '--max-time' => $maxTime,
            '--verbose' => true,
        ]);

        return self::SUCCESS;
    }
}
