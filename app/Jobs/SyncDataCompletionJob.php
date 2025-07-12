<?php

namespace App\Jobs;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class SyncDataCompletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $batchId;
    protected string $entityType;
    protected $userId;
    protected int $totalRecords;

    public $timeout = 30;

    public function __construct(string $batchId, string $entityType, $userId = null, int $totalRecords = 0)
    {
        $this->batchId = $batchId;
        $this->entityType = $entityType;
        $this->userId = $userId;
        $this->totalRecords = $totalRecords;
    }

    public function handle(): void
    {
        Log::info("Starting completion job for batch {$this->batchId} - {$this->entityType}");

        try {
            $batch = Bus::findBatch($this->batchId);

            if (!$batch) {
                Log::warning("Batch {$this->batchId} not found for {$this->entityType}");
                return;
            }

            // If batch is still running, reschedule this job (Filament polling pattern)
            if (!$batch->finished()) {
                Log::info("Batch {$this->batchId} for {$this->entityType} still running - Progress: {$batch->processedJobs()}/{$batch->totalJobs} - Rescheduling");

                // Reschedule to check again later
                static::dispatch($this->batchId, $this->entityType, $this->userId, $this->totalRecords)
                    ->delay(now()->addSeconds(3));
                return;
            }

            Log::info("Processing sync completion for {$this->entityType} - Batch: {$this->batchId}");

            // Send completion notification
            $this->sendCompletionNotification($batch);

        } catch (\Exception $e) {
            Log::error("Error in sync completion for {$this->entityType}: " . $e->getMessage());
        }
    }

    private function sendCompletionNotification($batch): void
    {
        if (!$this->userId) {
            return;
        }

        $user = User::find($this->userId);
        if (!$user) {
            return;
        }        if ($batch->finished() && !$batch->hasFailures()) {
            // All jobs completed successfully
            Notification::make()
                ->title("Sinkronisasi " . ucfirst($this->entityType) . " Selesai")
                ->body("Berhasil menyinkronkan {$this->totalRecords} data {$this->entityType} dalam {$batch->totalJobs} batch")
                ->success()
                ->sendToDatabase($user);

            Log::info("Batch sync completed successfully for {$this->entityType} - {$this->totalRecords} records");

        } elseif ($batch->hasFailures()) {
            // Some jobs failed
            $failedJobs = $batch->failedJobs;
            $successfulJobs = $batch->totalJobs - $failedJobs;
            $estimatedSyncedRecords = round(($successfulJobs / $batch->totalJobs) * $this->totalRecords);

            Notification::make()
                ->title("Sinkronisasi " . ucfirst($this->entityType) . " Selesai dengan Error")
                ->body("Sinkronisasi selesai dengan beberapa kegagalan untuk {$this->entityType} - Perkiraan tersinkron: ~{$estimatedSyncedRecords}/{$this->totalRecords} data, {$failedJobs} dari {$batch->totalJobs} batch gagal")
                ->warning()
                ->sendToDatabase($user);

            Log::warning("Batch sync completed with {$failedJobs} failures for {$this->entityType} - Estimated synced: ~{$estimatedSyncedRecords}/{$this->totalRecords} records");

        } elseif ($batch->cancelled()) {
            // Batch was cancelled
            Notification::make()
                ->title("Sinkronisasi " . ucfirst($this->entityType) . " Dibatalkan")
                ->body("Sinkronisasi batch dibatalkan untuk {$this->entityType}")
                ->danger()
                ->sendToDatabase($user);

            Log::info("Batch sync was cancelled for {$this->entityType}");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncDataCompletionJob failed for {$this->entityType}: " . $exception->getMessage());
    }
}
