<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncDataDispatcherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $entityType;
    protected $userId;
    protected int $batchSize;

    public $timeout = 600; // 10 minutes for initial data fetch

    public function __construct(string $entityType, $userId = null, int $batchSize = 100)
    {
        $this->entityType = $entityType;
        $this->userId = $userId;
        $this->batchSize = $batchSize;
    }

    public function handle(): void
    {
        try {
            Log::info("Starting sync dispatcher for {$this->entityType} with batch size {$this->batchSize}");

            $apiUrls = [
                'users' => 'https://grosir.mediaselularindonesia.com/api/sync/user',
                'outlets' => 'https://grosir.mediaselularindonesia.com/api/sync/outlet',
                'visits' => 'https://grosir.mediaselularindonesia.com/api/sync/visit',
                'planvisits' => 'https://grosir.mediaselularindonesia.com/api/sync/planvisit',
                'roles' => 'https://grosir.mediaselularindonesia.com/api/sync/role',
                'badanusahas' => 'https://grosir.mediaselularindonesia.com/api/sync/badanusaha',
                'divisions' => 'https://grosir.mediaselularindonesia.com/api/sync/division',
                'regions' => 'https://grosir.mediaselularindonesia.com/api/sync/region',
                'clusters' => 'https://grosir.mediaselularindonesia.com/api/sync/cluster',
            ];

            if (!isset($apiUrls[$this->entityType])) {
                throw new \Exception("Unknown entity type: {$this->entityType}");
            }

            // Get data from API
            Log::info("Fetching {$this->entityType} data from API...");
            $response = Http::timeout(300)->get($apiUrls[$this->entityType]);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch {$this->entityType} from API. Status: " . $response->status());
            }

            $responseData = $response->json();

            // Handle different response structures
            $data = [];
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $data = $responseData['data'];
            } elseif (is_array($responseData)) {
                $data = $responseData;
            } else {
                throw new \Exception('Invalid API response format');
            }

            $totalRecords = count($data);
            Log::info("Fetched {$totalRecords} {$this->entityType} records");

            if ($totalRecords === 0) {
                Log::info("No {$this->entityType} data to sync");
                return;
            }

            // Generate unique sync ID for tracking
            $syncId = Str::uuid()->toString();

            // Split data into batches
            $batches = array_chunk($data, $this->batchSize);
            $totalBatches = count($batches);

            Log::info("Dispatching {$totalBatches} batch jobs for {$this->entityType} (Sync ID: {$syncId})");

            // Dispatch batch jobs
            foreach ($batches as $index => $batchData) {
                $batchNumber = $index + 1;

                SyncDataBatchJob::dispatch(
                    $this->entityType,
                    $batchData,
                    $batchNumber,
                    $totalBatches,
                    $syncId,
                    $this->userId
                )->delay(now()->addSeconds($index * 2)); // Small delay between batch dispatches
            }

            Log::info("Successfully dispatched {$totalBatches} batch jobs for {$this->entityType}");

        } catch (\Exception $e) {
            Log::error("Sync dispatcher failed for {$this->entityType}: " . $e->getMessage());

            if ($this->userId) {
                \Filament\Notifications\Notification::make()
                    ->title("Sync " . ucfirst($this->entityType) . " Failed")
                    ->body("Failed to start sync: " . $e->getMessage())
                    ->danger()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncDataDispatcherJob failed for {$this->entityType}: " . $exception->getMessage());

        if ($this->userId) {
            \Filament\Notifications\Notification::make()
                ->title("Sync Dispatcher Failed")
                ->body("Failed to dispatch {$this->entityType} sync: " . $exception->getMessage())
                ->danger()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
