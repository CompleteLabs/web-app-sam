<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Services\ExternalVisitSyncService;
use Illuminate\Console\Command;

class PostVisitToExternalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visit:post-external {id? : Visit ID to post}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post visit data to external system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!config('sync.post_api_enabled', false)) {
            $this->error('External post API is disabled. Please enable it in config.');
            return 1;
        }

        $visitId = $this->argument('id');

        if ($visitId) {
            $visit = Visit::find($visitId);
            if (!$visit) {
                $this->error("Visit with ID {$visitId} not found.");
                return 1;
            }

            $this->postSingleVisit($visit);
        } else {
            $this->postMultipleVisits();
        }

        return 0;
    }

    /**
     * Post a single visit
     */
    private function postSingleVisit(Visit $visit)
    {
        $this->info("Posting visit ID: {$visit->id}");

        $syncService = new ExternalVisitSyncService();

        try {
            $result = $syncService->postVisit($visit);

            if ($result['success']) {
                $this->info("✅ Success: {$result['message']}");
                $this->line("Status: {$result['status']}");
            } else {
                $this->error("❌ Failed: {$result['message']}");
                if (isset($result['error'])) {
                    $this->error("Error: {$result['error']}");
                }
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception: {$e->getMessage()}");
        }
    }

    /**
     * Post multiple visits with selection
     */
    private function postMultipleVisits()
    {
        $visits = Visit::with(['user', 'outlet'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($visits->isEmpty()) {
            $this->info('No visits found.');
            return;
        }

        $this->info('Recent visits:');
        $this->table(
            ['ID', 'Date', 'User', 'Outlet', 'Type'],
            $visits->map(function ($visit) {
                return [
                    $visit->id,
                    $visit->visit_date->format('Y-m-d'),
                    $visit->user->name ?? 'N/A',
                    $visit->outlet->name ?? 'N/A',
                    $visit->type
                ];
            })
        );

        $selectedIds = $this->ask('Enter visit IDs to post (comma separated) or "all" for all:');

        if (strtolower(trim($selectedIds)) === 'all') {
            $selectedVisits = $visits;
        } else {
            $ids = array_map('trim', explode(',', $selectedIds));
            $selectedVisits = $visits->whereIn('id', $ids);
        }

        if ($selectedVisits->isEmpty()) {
            $this->info('No visits selected.');
            return;
        }

        $this->info("Posting {$selectedVisits->count()} visits...");

        $syncService = new ExternalVisitSyncService();
        $successCount = 0;
        $failCount = 0;

        foreach ($selectedVisits as $visit) {
            try {
                $result = $syncService->postVisit($visit);

                if ($result['success']) {
                    $successCount++;
                    $this->info("✅ Visit {$visit->id}: {$result['message']}");
                } else {
                    $failCount++;
                    $this->error("❌ Visit {$visit->id}: {$result['message']}");
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error("❌ Visit {$visit->id}: {$e->getMessage()}");
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("Success: {$successCount}");
        $this->info("Failed: {$failCount}");
    }
}
