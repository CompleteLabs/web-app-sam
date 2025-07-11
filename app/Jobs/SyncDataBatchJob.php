<?php

namespace App\Jobs;

use App\Models\User;
use App\Traits\SyncEntityTrait;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SyncDataBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use SyncEntityTrait;

    protected string $entityType;
    protected array $batchData;
    protected int $batchNumber;
    protected int $totalBatches;
    protected $userId;
    protected string $syncId;

    public $timeout = 600; // 10 minutes per batch
    public $tries = 3;
    public $maxExceptions = 3;

    public function __construct(
        string $entityType,
        array $batchData,
        int $batchNumber,
        int $totalBatches,
        string $syncId,
        $userId = null
    ) {
        $this->entityType = $entityType;
        $this->batchData = $batchData;
        $this->batchNumber = $batchNumber;
        $this->totalBatches = $totalBatches;
        $this->syncId = $syncId;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        try {
            Log::info("Processing batch {$this->batchNumber}/{$this->totalBatches} for {$this->entityType} (Sync ID: {$this->syncId})");

            $syncedCount = 0;
            $errorCount = 0;

            // Disable foreign key checks for this connection
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            DB::beginTransaction();

            foreach ($this->batchData as $itemData) {
                try {
                    $this->syncEntity($this->entityType, $itemData);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Error syncing {$this->entityType} ID " . ($itemData['id'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }

            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Update batch progress in cache
            $this->updateBatchProgress($syncedCount, $errorCount);

            Log::info("Batch {$this->batchNumber}/{$this->totalBatches} completed for {$this->entityType}: {$syncedCount} synced, {$errorCount} errors");

            // If this is the last batch, send final notification
            if ($this->batchNumber === $this->totalBatches) {
                $this->sendFinalNotification();
            }

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            Log::error("Batch {$this->batchNumber} sync failed for {$this->entityType}: " . $e->getMessage());

            // Update batch progress with errors
            $this->updateBatchProgress(0, count($this->batchData));

            // If this is the last batch or critical error, send notification
            if ($this->batchNumber === $this->totalBatches || $this->attempts() >= $this->tries) {
                $this->sendFinalNotification();
            }

            throw $e; // Re-throw to trigger job retry if needed
        }
    }

    private function updateBatchProgress(int $syncedCount, int $errorCount): void
    {
        $cacheKey = "sync_progress_{$this->syncId}";
        $progress = Cache::get($cacheKey, [
            'completed_batches' => 0,
            'total_synced' => 0,
            'total_errors' => 0,
            'entity_type' => $this->entityType,
            'total_batches' => $this->totalBatches,
            'user_id' => $this->userId,
        ]);

        $progress['completed_batches']++;
        $progress['total_synced'] += $syncedCount;
        $progress['total_errors'] += $errorCount;

        Cache::put($cacheKey, $progress, now()->addHours(1));
    }

    private function sendFinalNotification(): void
    {
        if (!$this->userId) {
            return;
        }

        $cacheKey = "sync_progress_{$this->syncId}";
        $progress = Cache::get($cacheKey);

        if (!$progress) {
            return;
        }

        $success = $progress['completed_batches'] === $this->totalBatches;

        if ($success) {
            Notification::make()
                ->title("Sync " . ucfirst($this->entityType) . " Completed")
                ->body("Successfully synced {$progress['total_synced']} " . $this->entityType .
                      ($progress['total_errors'] > 0 ? " with {$progress['total_errors']} errors" : '') .
                      " in {$progress['completed_batches']} batches")
                ->success()
                ->sendToDatabase(User::find($this->userId));
        } else {
            Notification::make()
                ->title("Sync " . ucfirst($this->entityType) . " Partially Failed")
                ->body("Completed {$progress['completed_batches']}/{$this->totalBatches} batches. " .
                      "Synced: {$progress['total_synced']}, Errors: {$progress['total_errors']}")
                ->warning()
                ->sendToDatabase(User::find($this->userId));
        }

        // Clean up cache
        Cache::forget($cacheKey);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncDataBatchJob failed for batch {$this->batchNumber}: " . $exception->getMessage());

        if ($this->userId) {
            Notification::make()
                ->title("Sync Batch Failed")
                ->body("Batch {$this->batchNumber} for {$this->entityType} failed: " . $exception->getMessage())
                ->danger()
                ->sendToDatabase(User::find($this->userId));
        }
    }
}
