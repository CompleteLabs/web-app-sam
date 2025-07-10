<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Outlet;
use App\Models\Visit;
use App\Models\PlanVisit;
use App\Models\Role;
use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Region;
use App\Models\Cluster;
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

    private function syncEntity(string $entityType, array $data): void
    {
        switch ($entityType) {
            case 'users':
                User::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['nama_lengkap'] ?? null,
                        'username' => $data['username'] ?? null,
                        'password' => $data['password'] ?? null,
                        'role_id' => $data['role_id'] ?? null,
                        'tm_id' => $data['tm_id'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'outlets':
                Outlet::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'code' => $data['kode_outlet'] ?? null,
                        'name' => $data['nama_outlet'] ?? null,
                        'address' => $data['alamat_outlet'] ?? null,
                        'district' => $data['distric'] ?? null,
                        'status' => $data['status_outlet'] ?? null,
                        'location' => $data['latlong'] ?? null,
                        'badanusaha_id' => $data['badanusaha_id'] ?? null,
                        'divisi_id' => $data['divisi_id'] ?? null,
                        'region_id' => $data['region_id'] ?? null,
                        'cluster_id' => $data['cluster_id'] ?? null,
                        'limit' => $data['limit'] ?? null,
                        'radius' => $data['radius'] ?? null,
                        'level' => isset($data['is_member']) ? ($data['is_member'] == 1 ? 'MEMBER' : 'LEAD') : null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'visits':
                Visit::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'visit_date' => isset($data['tanggal_visit']) ? \Carbon\Carbon::parse($data['tanggal_visit'])->format('Y-m-d') : null,
                        'user_id' => $data['user_id'] ?? null,
                        'outlet_id' => $data['outlet_id'] ?? null,
                        'type' => $data['tipe_visit'] ?? null,
                        'checkin_photo' => $data['picture_visit_in'] ?? null,
                        'checkout_photo' => $data['picture_visit_out'] ?? null,
                        'checkin_location' => $data['latlong_in'] ?? null,
                        'checkout_location' => $data['latlong_out'] ?? null,
                        'checkin_time' => isset($data['check_in_time']) ? \Carbon\Carbon::parse($data['check_in_time'])->format('H:i:s') : null,
                        'checkout_time' => isset($data['check_out_time']) ? \Carbon\Carbon::parse($data['check_out_time'])->format('H:i:s') : null,
                        'duration' => $data['durasi_visit'] ?? null,
                        'transaction' => $data['transaksi'] ?? null,
                        'report' => $data['laporan_visit'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'planvisits':
                PlanVisit::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'visit_date' => isset($data['tanggal_visit']) ? \Carbon\Carbon::parse($data['tanggal_visit'])->format('Y-m-d') : null,
                        'user_id' => $data['user_id'] ?? null,
                        'outlet_id' => $data['outlet_id'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'roles':
                Role::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['name'] ?? null,
                        'can_access_web' => $data['can_access_web'] ?? 0,
                        'can_access_mobile' => $data['can_access_mobile'] ?? 0,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'badanusahas':
                BadanUsaha::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['name'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'divisions':
                Division::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'badan_usaha_id' => $data['badanusaha_id'] ?? null,
                        'name' => $data['name'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'regions':
                Region::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'badan_usaha_id' => $data['badanusaha_id'] ?? null,
                        'division_id' => $data['divisi_id'] ?? null,
                        'name' => $data['name'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'clusters':
                Cluster::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'badan_usaha_id' => $data['badanusaha_id'] ?? null,
                        'division_id' => $data['divisi_id'] ?? null,
                        'region_id' => $data['region_id'] ?? null,
                        'name' => $data['name'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            default:
                throw new \Exception("Unknown entity type: {$entityType}");
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
