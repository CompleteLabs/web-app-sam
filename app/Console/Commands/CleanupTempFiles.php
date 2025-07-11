<?php

namespace App\Console\Commands;

use App\Services\FileUploadService;
use Illuminate\Console\Command;

class CleanupTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'files:cleanup-temp {--hours=24 : Hours old to cleanup}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up temporary upload files older than specified hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');

        $this->info("Cleaning up temp files older than {$hours} hours...");

        $deletedCount = FileUploadService::cleanupTempFiles($hours);

        $this->info("Cleaned up {$deletedCount} temp files.");

        return 0;
    }
}
