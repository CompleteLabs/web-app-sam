<?php

namespace App\Jobs;

use App\Models\Visit;
use App\Models\User;
use App\Services\ExternalVisitSyncService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PostVisitToExternalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $visitIds,
        private int $userId
    ) {
        // Ensure visitIds is always an array
        if (!is_array($this->visitIds)) {
            $this->visitIds = [$this->visitIds];
        }
    }

    /**
     * Create job for single visit
     */
    public static function forSingleVisit(int $visitId, int $userId): self
    {
        return new self([$visitId], $userId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting batch post visits to external system', [
            'visit_ids' => $this->visitIds,
            'user_id' => $this->userId
        ]);

        $syncService = new ExternalVisitSyncService();
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($this->visitIds as $visitId) {
            try {
                $visit = Visit::find($visitId);

                if (!$visit) {
                    $failCount++;
                    $errors[] = "Visit ID {$visitId} not found";
                    continue;
                }

                $result = $syncService->postVisit($visit);

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = "Visit ID {$visitId}: {$result['message']}";
                }
            } catch (\Exception $e) {
                $failCount++;
                $errors[] = "Visit ID {$visitId}: {$e->getMessage()}";
                Log::error('Failed to post visit in batch job', [
                    'visit_id' => $visitId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send notification to user
        $user = User::find($this->userId);
        if ($user) {
            $message = "Batch post completed: {$successCount} success, {$failCount} failed";

            if ($failCount > 0) {
                $message .= ". Errors: " . implode('; ', array_slice($errors, 0, 3));
            }

            Notification::make()
                ->title($failCount == 0 ? 'Batch Post Berhasil!' : 'Batch Post Selesai')
                ->body($message)
                ->status($failCount == 0 ? 'success' : 'warning')
                ->sendToDatabase($user);
        }

        Log::info('Batch post visits completed', [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'user_id' => $this->userId
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch post visits job failed', [
            'visit_ids' => $this->visitIds,
            'user_id' => $this->userId,
            'error' => $exception->getMessage()
        ]);

        // Send failure notification to user
        $user = User::find($this->userId);
        if ($user) {
            Notification::make()
                ->title('Batch Post Gagal!')
                ->body('Batch post visits ke sistem eksternal gagal: ' . $exception->getMessage())
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
