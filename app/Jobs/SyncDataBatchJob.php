<?php

namespace App\Jobs;

use App\Models\User;
use App\Traits\SyncEntityTrait;
use Filament\Notifications\Notification;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncDataBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;
    use SyncEntityTrait;

    protected string $entityType;
    protected array $batchData;
    protected $userId;

    public $timeout = 600; // 10 minutes per batch
    public $tries = 3;
    public $maxExceptions = 3;

    public function __construct(
        string $entityType,
        array $batchData,
        $userId = null
    ) {
        $this->entityType = $entityType;
        $this->batchData = $batchData;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        // Skip processing if batch is cancelled
        if ($this->batch()->cancelled()) {
            return;
        }

        try {
            $batchId = $this->batch()->id;
            $progress = $this->batch()->progress();
            $totalJobs = $this->batch()->totalJobs;
            $processedJobs = $this->batch()->processedJobs();

            Log::info("Processing batch job for {$this->entityType} (Batch ID: {$batchId}, Progress: {$processedJobs}/{$totalJobs})");

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

            Log::info("Batch job completed for {$this->entityType}: {$syncedCount} synced, {$errorCount} errors");

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            Log::error("Batch job sync failed for {$this->entityType}: " . $e->getMessage());

            throw $e; // Re-throw to trigger job retry if needed
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncDataBatchJob failed for {$this->entityType}: " . $exception->getMessage());

        if ($this->userId) {
            Notification::make()
                ->title("Batch Sinkronisasi Gagal")
                ->body("Job batch untuk {$this->entityType} gagal: " . $exception->getMessage())
                ->danger()
                ->sendToDatabase(User::find($this->userId));
        }
    }
}
