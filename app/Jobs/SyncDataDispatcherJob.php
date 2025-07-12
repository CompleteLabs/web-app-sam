<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncDataDispatcherJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $entityType;
    protected $userId;
    protected int $batchSize;
    protected ?int $month;
    protected ?int $year;

    public $timeout = 600; // 10 minutes for initial data fetch

    public function __construct(
        string $entityType,
        $userId = null,
        int $batchSize = 100,
        ?int $month = null,
        ?int $year = null
    ) {
        $this->entityType = $entityType;
        $this->userId = $userId;
        $this->batchSize = $batchSize;
        $this->month = $month;
        $this->year = $year;
    }

    public function handle(): void
    {
        try {
            Log::info("Starting sync dispatcher for {$this->entityType} with batch size {$this->batchSize}");

            // Fetch data from API
            $data = $this->fetchDataFromApi();

            $totalRecords = count($data);
            Log::info("Fetched {$totalRecords} {$this->entityType} records");

            if ($totalRecords === 0) {
                Log::info("No {$this->entityType} data to sync");
                return;
            }

            // Split data into batches
            $batches = array_chunk($data, $this->batchSize);
            $totalBatches = count($batches);

            Log::info("Creating batch of {$totalBatches} jobs for {$this->entityType}");

            // Create batch jobs
            $jobs = [];
            foreach ($batches as $batchData) {
                $jobs[] = new SyncDataBatchJob(
                    $this->entityType,
                    $batchData,
                    $this->userId
                );
            }

            // Dispatch as Laravel Batch (Filament-style pattern)
            $batch = Bus::batch($jobs)
                ->name("Sync {$this->entityType}")
                ->allowFailures()
                ->dispatch();

            // Chain completion job - this is the Filament pattern
            Log::info("Dispatching completion job for batch {$batch->id}");
            SyncDataCompletionJob::dispatch($batch->id, $this->entityType, $this->userId, $totalRecords)
                ->delay(now()->addSeconds(1)); // Reduced delay for faster completion

            // Log batch information
            Log::info("Successfully dispatched batch with ID: {$batch->id} for {$this->entityType}");

        } catch (\Exception $e) {
            Log::error("Sync dispatcher failed for {$this->entityType}: " . $e->getMessage());

            if ($this->userId) {
                \Filament\Notifications\Notification::make()
                    ->title("Sinkronisasi " . ucfirst($this->entityType) . " Gagal")
                    ->body("Gagal memulai sinkronisasi: " . $e->getMessage())
                    ->danger()
                    ->sendToDatabase(\App\Models\User::find($this->userId));
            }

            throw $e;
        }
    }

    private function fetchDataFromApi(): array
    {
        $apiUrls = config('sync.api_urls', []);

        if (!isset($apiUrls[$this->entityType])) {
            throw new \Exception("Unknown entity type: {$this->entityType}");
        }

        $url = $apiUrls[$this->entityType];

        // Add query parameters for visits API
        if ($this->entityType === 'visits') {
            $params = [];
            if ($this->month !== null) {
                $params['month'] = $this->month;
            }
            if ($this->year !== null) {
                $params['year'] = $this->year;
            }

            if (!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        }

        // Get data from API
        $monthYear = '';
        if ($this->entityType === 'visits' && ($this->month || $this->year)) {
            $monthYear = ' for ' . ($this->month ? "month {$this->month}" : 'current month') .
                        ($this->year ? " year {$this->year}" : ' current year');
        }

        Log::info("Fetching {$this->entityType} data from API{$monthYear}...");
        $response = Http::timeout(config('sync.timeout', 300))->get($url);

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

        return $data;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncDataDispatcherJob failed for {$this->entityType}: " . $exception->getMessage());

        if ($this->userId) {
            \Filament\Notifications\Notification::make()
                ->title("Dispatcher Sinkronisasi Gagal")
                ->body("Gagal menjalankan sinkronisasi {$this->entityType}: " . $exception->getMessage())
                ->danger()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
