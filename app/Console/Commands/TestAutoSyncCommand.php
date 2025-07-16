<?php

namespace App\Console\Commands;

use App\Jobs\ProcessFileUploadJob;
use App\Models\Visit;
use Illuminate\Console\Command;

class TestAutoSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visit:test-auto-sync {visit_id? : Visit ID to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test auto-sync functionality after file upload';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $visitId = $this->argument('visit_id');

        if (!$visitId) {
            $visits = Visit::latest()->limit(5)->get();

            if ($visits->isEmpty()) {
                $this->error('No visits found.');
                return 1;
            }

            $this->info('Recent visits:');
            $this->table(
                ['ID', 'Date', 'User', 'Outlet', 'Sync Status'],
                $visits->map(function ($visit) {
                    return [
                        $visit->id,
                        $visit->visit_date->format('Y-m-d'),
                        $visit->user->name ?? 'N/A',
                        $visit->outlet->name ?? 'N/A',
                        $visit->external_sync_status ?? 'Not Synced'
                    ];
                })
            );

            $visitId = $this->ask('Enter visit ID to test');
        }

        $visit = Visit::find($visitId);
        if (!$visit) {
            $this->error("Visit with ID {$visitId} not found.");
            return 1;
        }

        // Check configuration
        $this->info('Configuration:');
        $this->line('- Post API Enabled: ' . (config('sync.post_api_enabled', false) ? 'Yes' : 'No'));
        $this->line('- Auto Sync After Upload: ' . (config('sync.auto_sync_after_file_upload', true) ? 'Yes' : 'No'));
        $this->line('- Post API Base URL: ' . (config('sync.post_api_base_url') ?: 'Not set'));

        if (!config('sync.post_api_enabled', false)) {
            $this->warn('⚠️  External post API is disabled. Enable it with EXTERNAL_POST_API_ENABLED=true');
        }

        // Simulate file upload completion
        $this->info("\n🧪 Testing auto-sync trigger...");

        // Create fake temp files structure (simulating what would happen in real scenario)
        $tempFiles = [
            'checkin_photo' => [
                'temp_file' => 'temp_test_file.jpg',
                'temp_path' => '/tmp/test_path',
                'final_name' => 'test_visit_' . $visitId . '.jpg',
                'field_name' => 'checkin_photo'
            ]
        ];

        // Create the job (this would normally be done by the API controller)
        $job = new ProcessFileUploadJob($tempFiles, Visit::class, $visit->id, $visit->user_id);

        try {
            // Instead of dispatching, we'll test the auto-sync trigger method directly
            $reflection = new \ReflectionClass($job);
            $method = $reflection->getMethod('triggerExternalSyncIfNeeded');
            $method->setAccessible(true);

            $this->info('Triggering auto-sync...');
            $method->invoke($job);

            $this->info('✅ Auto-sync trigger executed successfully!');
            $this->info('Check the queue jobs and logs for the actual sync process.');

        } catch (\Exception $e) {
            $this->error('❌ Error testing auto-sync: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
