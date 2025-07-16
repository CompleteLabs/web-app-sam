<?php

namespace App\Jobs;

use App\Jobs\PostVisitToExternalJob;
use App\Models\Visit;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFileUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tempFiles;
    protected $modelClass;
    protected $modelId;
    protected $userId;

    /**
     * Job timeout in seconds (5 minutes)
     */
    public $timeout = 300;

    /**
     * Number of times the job may be attempted
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(array $tempFiles, string $modelClass, int $modelId, int $userId)
    {
        $this->tempFiles = $tempFiles;
        $this->modelClass = $modelClass;
        $this->modelId = $modelId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing file upload job started', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
            'user_id' => $this->userId,
            'file_count' => count($this->tempFiles)
        ]);

        try {
            $processedFiles = [];

            // Process each temp file
            foreach ($this->tempFiles as $fieldName => $tempFileInfo) {
                $success = FileUploadService::moveFromTempToPermanent(
                    $tempFileInfo['temp_path'],
                    $tempFileInfo['final_name']
                );

                if ($success) {
                    $processedFiles[$fieldName] = $tempFileInfo['final_name'];

                    // Get final file size for logging
                    $finalPath = storage_path('app/' . config('business.upload.path') . '/' . $tempFileInfo['final_name']);
                    $finalSizeKB = file_exists($finalPath) ? round(filesize($finalPath) / 1024, 2) : 0;
                    $originalSizeKB = isset($tempFileInfo['size']) ? round($tempFileInfo['size'] / 1024, 2) : 0;

                    Log::info('File processed successfully', [
                        'field' => $fieldName,
                        'final_name' => $tempFileInfo['final_name'],
                        'original_size_kb' => $originalSizeKB,
                        'final_size_kb' => $finalSizeKB,
                        'compression_applied' => $finalSizeKB < $originalSizeKB && $originalSizeKB > 0,
                        'mime_type' => $tempFileInfo['mime_type'] ?? 'unknown'
                    ]);
                } else {
                    Log::error('Failed to process file', [
                        'field' => $fieldName,
                        'temp_path' => $tempFileInfo['temp_path'],
                        'final_name' => $tempFileInfo['final_name']
                    ]);
                    // Continue processing other files even if one fails
                }
            }

            // Update the model with processed file names
            if (!empty($processedFiles)) {
                $this->updateModel($processedFiles);
            }

            Log::info('File upload job completed successfully', [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'processed_files' => count($processedFiles)
            ]);

            // Auto-trigger external sync for Visit models if enabled
            $this->triggerExternalSyncIfNeeded();

        } catch (\Exception $e) {
            Log::error('Error in file upload job: ' . $e->getMessage(), [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    /**
     * Update the model with processed file names
     */
    private function updateModel(array $processedFiles): void
    {
        try {
            $model = $this->modelClass::find($this->modelId);

            if (!$model) {
                Log::error('Model not found for file upload update', [
                    'model_class' => $this->modelClass,
                    'model_id' => $this->modelId
                ]);
                return;
            }

            // Update model with file names
            $model->update($processedFiles);

            Log::info('Model updated with processed files', [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'fields_updated' => array_keys($processedFiles)
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating model with processed files: ' . $e->getMessage(), [
                'model_class' => $this->modelClass,
                'model_id' => $this->modelId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Trigger external sync job for Visit models if enabled
     */
    private function triggerExternalSyncIfNeeded(): void
    {
        // Only trigger for Visit models
        if ($this->modelClass !== Visit::class) {
            return;
        }

        // Only trigger if external sync is enabled and auto-sync is enabled
        if (!config('sync.post_api_enabled', false) || !config('sync.auto_sync_after_file_upload', true)) {
            Log::info('External sync is disabled or auto-sync is disabled, skipping auto-sync for Visit', [
                'visit_id' => $this->modelId,
                'post_api_enabled' => config('sync.post_api_enabled', false),
                'auto_sync_enabled' => config('sync.auto_sync_after_file_upload', true)
            ]);
            return;
        }

        // Dispatch the external sync job
        try {
            $delaySeconds = config('sync.auto_sync_delay_seconds', 10);
            PostVisitToExternalJob::forSingleVisit($this->modelId, $this->userId)
                ->delay(now()->addSeconds($delaySeconds))
                ->dispatch();

            Log::info('External sync job dispatched for Visit after file upload', [
                'visit_id' => $this->modelId,
                'user_id' => $this->userId,
                'delay_seconds' => $delaySeconds
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch external sync job for Visit', [
                'visit_id' => $this->modelId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('File upload job failed permanently', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Clean up temp files on final failure
        foreach ($this->tempFiles as $tempFileInfo) {
            if (file_exists($tempFileInfo['temp_path'])) {
                unlink($tempFileInfo['temp_path']);
            }
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }
}
