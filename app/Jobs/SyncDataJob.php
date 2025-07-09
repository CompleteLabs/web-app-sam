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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $entityType;
    protected $userId;

    public function __construct(string $entityType, $userId = null)
    {
        $this->entityType = $entityType;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        try {
            Log::info("Starting sync for {$this->entityType}");

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

            // Set unlimited execution time
            set_time_limit(0);
            ignore_user_abort(true);

            // Get data from API
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

            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            $syncedCount = 0;
            $errorCount = 0;

            DB::beginTransaction();

            foreach ($data as $itemData) {
                try {
                    $this->syncEntity($this->entityType, $itemData);
                    $syncedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Error syncing {$this->entityType} ID " . ($itemData['id'] ?? 'unknown') . ': ' . $e->getMessage());
                }
            }

            DB::commit();

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Send notification if user is specified
            if ($this->userId) {
                $this->sendNotification($syncedCount, $errorCount, true);
            }

            Log::info("{$this->entityType} sync completed: {$syncedCount} synced, {$errorCount} errors");

        } catch (\Exception $e) {
            DB::rollback();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            Log::error("{$this->entityType} sync failed: " . $e->getMessage());

            if ($this->userId) {
                $this->sendNotification(0, 0, false, $e->getMessage());
            }
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

            case 'badanusaha':
                BadanUsaha::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['nama_badan_usaha'] ?? null,
                        'type' => $data['tipe_badan_usaha'] ?? null,
                        'status' => $data['status_badan_usaha'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'divisions':
                Division::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['nama_divisi'] ?? null,
                        'status' => $data['status_divisi'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'regions':
                Region::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['nama_region'] ?? null,
                        'status' => $data['status_region'] ?? null,
                        'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                        'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
                    ]
                );
                break;

            case 'clusters':
                Cluster::updateOrCreate(
                    ['id' => $data['id']],
                    [
                        'name' => $data['nama_cluster'] ?? null,
                        'status' => $data['status_cluster'] ?? null,
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

    private function sendNotification(int $syncedCount, int $errorCount, bool $success, string $errorMessage = ''): void
    {
        if ($success) {
            Notification::make()
                ->title("Sync " . ucfirst($this->entityType) . " Completed")
                ->body("Successfully synced {$syncedCount} " . $this->entityType . ($errorCount > 0 ? " with {$errorCount} errors" : ''))
                ->success()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        } else {
            Notification::make()
                ->title("Sync " . ucfirst($this->entityType) . " Failed")
                ->body('Error: ' . $errorMessage)
                ->danger()
                ->sendToDatabase(\App\Models\User::find($this->userId));
        }
    }
}
