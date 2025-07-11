<?php

namespace App\Console\Commands;

use App\Jobs\SyncDataDispatcherJob;
use Illuminate\Console\Command;

class SyncDataCommand extends Command
{
    protected $signature = 'sync:data
                          {entity : The entity type to sync (users, outlets, visits, planvisits, roles, badanusahas, divisions, regions, clusters, all)}
                          {--batch-size=100 : Number of records per batch}
                          {--use-batch : Use batch processing (recommended for large datasets)}
                          {--user-id= : User ID to send notifications to}
                          {--delay=5 : Delay in seconds between entity syncs when using "all"}
                          {--month= : Month for visits sync (1-12), defaults to current month}
                          {--year= : Year for visits sync, defaults to current year}';

    protected $description = 'Sync data from external API with optional batch processing. For visits, supports month/year parameters.';

    public function handle()
    {
        $entityType = $this->argument('entity');
        $batchSize = (int) $this->option('batch-size');
        $useBatch = $this->option('use-batch');
        $userId = $this->option('user-id');
        $delay = (int) $this->option('delay');
        $month = $this->option('month') ? (int) $this->option('month') : null;
        $year = $this->option('year') ? (int) $this->option('year') : null;

        $validEntities = [
            'users', 'outlets', 'visits', 'planvisits', 'roles',
            'badanusahas', 'divisions', 'regions', 'clusters', 'all'
        ];

        if (!in_array($entityType, $validEntities)) {
            $this->error("Invalid entity type. Valid options: " . implode(', ', $validEntities));
            return self::FAILURE;
        }

        if ($batchSize < 1 || $batchSize > 1000) {
            $this->error("Batch size must be between 1 and 1000");
            return self::FAILURE;
        }

        if ($delay < 0 || $delay > 60) {
            $this->error("Delay must be between 0 and 60 seconds");
            return self::FAILURE;
        }

        // Validate month and year for visits
        if (($month !== null || $year !== null) && $entityType !== 'visits' && $entityType !== 'all') {
            $this->warn("Month and year parameters are only applicable for 'visits' entity type");
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            $this->error("Month must be between 1 and 12");
            return self::FAILURE;
        }

        if ($year !== null && ($year < 2020 || $year > 2030)) {
            $this->error("Year must be between 2020 and 2030");
            return self::FAILURE;
        }

        // Handle "all" entity type
        if ($entityType === 'all') {
            return $this->syncAllEntities($batchSize, $useBatch, $userId, $delay, $month, $year);
        }

        // Handle single entity
        return $this->syncSingleEntity($entityType, $batchSize, $useBatch, $userId, true, $month, $year);
    }

    private function syncAllEntities(int $batchSize, bool $useBatch, $userId, int $delay, ?int $month = null, ?int $year = null): int
    {
        // Sync order based on dependencies
        $syncOrder = [
            'roles',
            'badanusahas',
            'divisions',
            'regions',
            'clusters',
            'outlets',
            'users',
            'planvisits',
            'visits'
        ];

        $this->info("Starting sync for ALL entities in dependency order...");
        $this->info("Sync order: " . implode(' → ', $syncOrder));
        $this->info("Batch size: {$batchSize}, Use batch: " . ($useBatch ? 'Yes' : 'No') . ", Delay: {$delay}s");

        if ($userId) {
            $this->sendAllSyncStartNotification($userId, $syncOrder);
        }

        $results = [];
        $totalStartTime = microtime(true);

        foreach ($syncOrder as $index => $entity) {
            $this->newLine();
            $entityIndex = $index + 1;
            $totalEntities = count($syncOrder);
            $this->info("📋 [{$entityIndex}/{$totalEntities}] Syncing {$entity}...");

            $startTime = microtime(true);

            try {
                $success = $this->syncSingleEntity($entity, $batchSize, $useBatch, null, false, $month, $year);
                $duration = round(microtime(true) - $startTime, 2);

                if ($success === self::SUCCESS) {
                    $this->info("✅ {$entity} sync completed in {$duration}s");
                    $results[$entity] = ['status' => 'success', 'duration' => $duration];
                } else {
                    $this->error("❌ {$entity} sync failed after {$duration}s");
                    $results[$entity] = ['status' => 'failed', 'duration' => $duration];
                }

            } catch (\Exception $e) {
                $duration = round(microtime(true) - $startTime, 2);
                $this->error("❌ {$entity} sync failed: " . $e->getMessage());
                $results[$entity] = ['status' => 'error', 'duration' => $duration, 'error' => $e->getMessage()];
            }

            // Add delay between syncs (except for the last one)
            if ($index < count($syncOrder) - 1 && $delay > 0) {
                $this->info("⏳ Waiting {$delay} seconds before next sync...");
                sleep($delay);
            }
        }

        $totalDuration = round(microtime(true) - $totalStartTime, 2);

        // Show summary
        $this->showSyncSummary($results, $totalDuration);

        // Send final notification
        if ($userId) {
            $this->sendAllSyncCompleteNotification($userId, $results, $totalDuration);
        }

        // Return success if all syncs succeeded
        $allSuccess = collect($results)->every(fn($result) => $result['status'] === 'success');
        return $allSuccess ? self::SUCCESS : self::FAILURE;
    }

    private function syncSingleEntity(
        string $entityType,
        int $batchSize,
        bool $useBatch,
        $userId,
        bool $showMessages = true,
        ?int $month = null,
        ?int $year = null
    ): int {
        if ($showMessages) {
            $monthYearMsg = '';
            if ($entityType === 'visits' && ($month || $year)) {
                $monthYearMsg = " for " . ($month ? "month {$month}" : 'current month') .
                               ($year ? " year {$year}" : ' current year');
            }
            $this->info("Starting {$entityType} sync{$monthYearMsg}...");
        }

        try {
            if ($useBatch) {
                if ($showMessages) {
                    $this->info("Using batch processing with batch size: {$batchSize}");
                }
                SyncDataDispatcherJob::dispatch($entityType, $userId, $batchSize, $month, $year);
            } else {
                if ($showMessages) {
                    $this->info("Using single job processing");
                }
                SyncDataDispatcherJob::dispatch($entityType, $userId, $batchSize, $month, $year);
            }

            if ($showMessages) {
                $this->info("Sync job has been queued. Check the queue worker for progress.");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            if ($showMessages) {
                $this->error("Failed to dispatch sync job: " . $e->getMessage());
            }
            return self::FAILURE;
        }
    }

    private function showSyncSummary(array $results, float $totalDuration): void
    {
        $this->newLine(2);
        $this->info("📊 SYNC SUMMARY");
        $this->info("═══════════════════════════════════════");

        $successCount = 0;
        $failedCount = 0;

        foreach ($results as $entity => $result) {
            $status = $result['status'];
            $duration = $result['duration'];

            $icon = match($status) {
                'success' => '✅',
                'failed' => '❌',
                'error' => '💥',
                default => '❓'
            };

            $this->line("{$icon} {$entity}: {$status} ({$duration}s)");

            if ($status === 'success') {
                $successCount++;
            } else {
                $failedCount++;
            }

            if (isset($result['error'])) {
                $this->line("    Error: " . $result['error']);
            }
        }

        $this->info("═══════════════════════════════════════");
        $this->info("📈 Total Duration: {$totalDuration}s");
        $this->info("✅ Success: {$successCount}");
        $this->info("❌ Failed: {$failedCount}");
        $this->newLine();
    }

    private function sendAllSyncStartNotification($userId, array $syncOrder): void
    {
        try {
            \Filament\Notifications\Notification::make()
                ->title("🚀 Full Sync Started")
                ->body("Starting sync for all entities: " . implode(', ', $syncOrder))
                ->info()
                ->sendToDatabase(\App\Models\User::find($userId));
        } catch (\Exception $e) {
            $this->warn("Failed to send start notification: " . $e->getMessage());
        }
    }

    private function sendAllSyncCompleteNotification($userId, array $results, float $totalDuration): void
    {
        try {
            $successCount = collect($results)->filter(fn($r) => $r['status'] === 'success')->count();
            $totalCount = count($results);

            $isSuccess = $successCount === $totalCount;

            \Filament\Notifications\Notification::make()
                ->title($isSuccess ? "🎉 Full Sync Completed" : "⚠️ Full Sync Completed with Issues")
                ->body("Completed {$successCount}/{$totalCount} syncs successfully in " . round($totalDuration, 1) . "s")
                ->color($isSuccess ? 'success' : 'warning')
                ->sendToDatabase(\App\Models\User::find($userId));
        } catch (\Exception $e) {
            $this->warn("Failed to send completion notification: " . $e->getMessage());
        }
    }
}
