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
use App\Models\UserScope;
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
    protected int $batchSize;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;
    public $maxExceptions = 3;

    public function __construct(string $entityType, $userId = null, int $batchSize = 100)
    {
        $this->entityType = $entityType;
        $this->userId = $userId;
        $this->batchSize = $batchSize;
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
            $totalData = count($data);

            Log::info("Processing {$totalData} {$this->entityType} records in batches of {$this->batchSize}");

            // Process data in batches
            $batches = array_chunk($data, $this->batchSize);
            $batchNumber = 1;
            $totalBatches = count($batches);            foreach ($batches as $batch) {
                $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
                Log::info("Processing batch {$batchNumber}/{$totalBatches} for {$this->entityType} (Memory: {$memoryUsage}MB)");

                DB::beginTransaction();

                try {
                    foreach ($batch as $itemData) {
                        try {
                            $this->syncEntity($this->entityType, $itemData);
                            $syncedCount++;
                        } catch (\Exception $e) {
                            $errorCount++;
                            Log::error("Error syncing {$this->entityType} ID " . ($itemData['id'] ?? 'unknown') . ': ' . $e->getMessage());
                        }
                    }

                    DB::commit();

                    // Optional: Add small delay between batches to reduce load
                    if ($batchNumber < $totalBatches) {
                        usleep(250000); // 0.25 second delay (increased from 0.1)
                    }

                } catch (\Exception $e) {
                    DB::rollback();
                    Log::error("Error processing batch {$batchNumber} for {$this->entityType}: " . $e->getMessage());

                    // Count all items in failed batch as errors
                    $errorCount += count($batch);
                }

                $batchNumber++;

                // Clear memory more frequently for large datasets
                if ($batchNumber % 5 === 0) {
                    gc_collect_cycles();
                    $memoryAfterGC = memory_get_usage(true) / 1024 / 1024; // MB
                    Log::info("Memory after GC: {$memoryAfterGC}MB");
                }

                // Memory usage check - if too high, force garbage collection
                $currentMemory = memory_get_usage(true) / 1024 / 1024;
                if ($currentMemory > 200) { // 200MB threshold
                    Log::warning("High memory usage detected: {$currentMemory}MB - forcing cleanup");
                    gc_collect_cycles();
                    unset($batch); // Explicitly unset batch data
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            // Send notification if user is specified
            if ($this->userId) {
                $this->sendNotification($syncedCount, $errorCount, true);
            }

            Log::info("{$this->entityType} sync completed: {$syncedCount} synced, {$errorCount} errors");

        } catch (\Exception $e) {
            // Make sure to rollback any pending transaction
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
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
                $user = User::updateOrCreate(
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

                // Handle user scope after user creation/update
                $this->handleUserScope($user, $data);
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

    /**
     * Handle user scope creation from API data
     */
    private function handleUserScope(User $user, array $data): void
    {
        // Get user's role to check scope requirements
        $role = $user->role;
        if (!$role) {
            Log::warning("User {$user->id} has no role, skipping scope creation");
            return;
        }

        // Get scope required fields from role
        $scopeFields = [];
        if ($role->scope_required_fields) {
            $scopeFields = is_array($role->scope_required_fields)
                ? $role->scope_required_fields
                : json_decode($role->scope_required_fields, true);
        }

        if (empty($scopeFields)) {
            Log::info("Role {$role->name} has no scope requirements, skipping scope creation for user {$user->id}");
            return;
        }

        // Build scope data based on API response
        $scopeData = [];

        // Handle BadanUsaha
        if (in_array('badan_usaha_id', $scopeFields)) {
            $badanUsahaId = $this->getScopeIdFromApiData($data, 'badanusaha', BadanUsaha::class);
            if ($badanUsahaId) {
                $scopeData['badan_usaha_id'] = [$badanUsahaId];
            }
        }

        // Handle Division
        if (in_array('division_id', $scopeFields)) {
            $divisionId = $this->getScopeIdFromApiData($data, 'divisi', Division::class, [
                'badan_usaha_id' => $scopeData['badan_usaha_id'][0] ?? null
            ]);
            if ($divisionId) {
                $scopeData['division_id'] = [$divisionId];
            }
        }

        // Handle Region
        if (in_array('region_id', $scopeFields)) {
            $regionId = $this->getScopeIdFromApiData($data, 'region', Region::class, [
                'badan_usaha_id' => $scopeData['badan_usaha_id'][0] ?? null,
                'division_id' => $scopeData['division_id'][0] ?? null
            ]);
            if ($regionId) {
                $scopeData['region_id'] = [$regionId];
            }
        }

        // Handle Cluster (special handling for cluster_id and cluster_id2)
        if (in_array('cluster_id', $scopeFields)) {
            $clusterIds = $this->getClusterIdsFromApiData($data, [
                'badan_usaha_id' => $scopeData['badan_usaha_id'][0] ?? null,
                'division_id' => $scopeData['division_id'][0] ?? null,
                'region_id' => $scopeData['region_id'][0] ?? null
            ]);
            if (!empty($clusterIds)) {
                $scopeData['cluster_id'] = $clusterIds;
            }
        }

        // Only create UserScope if we have scope data
        if (!empty($scopeData)) {
            // Delete existing user scopes for this user
            UserScope::where('user_id', $user->id)->delete();

            // Create new user scope
            $scopeData['user_id'] = $user->id;
            UserScope::create($scopeData);

            Log::info("Created user scope for user {$user->id}", $scopeData);
        } else {
            Log::warning("No valid scope data found for user {$user->id}");
        }
    }

    /**
     * Get scope ID from API data based on name lookup
     */
    private function getScopeIdFromApiData(array $data, string $apiKey, string $modelClass, array $filters = []): ?int
    {
        // Check if API data has the required key with name
        if (!isset($data[$apiKey]['name']) || empty($data[$apiKey]['name'])) {
            return null;
        }

        $name = $data[$apiKey]['name'];
        $query = $modelClass::where('name', $name);

        // Apply hierarchical filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        $entity = $query->first();
        return $entity ? $entity->id : null;
    }

    /**
     * Get cluster IDs from API data (handling cluster_id and cluster_id2)
     */
    private function getClusterIdsFromApiData(array $data, array $filters = []): array
    {
        $clusterIds = [];

        // Handle cluster_id
        if (isset($data['cluster_id']) && !empty($data['cluster_id'])) {
            $clusterId = $this->validateClusterId($data['cluster_id'], $filters);
            if ($clusterId) {
                $clusterIds[] = $clusterId;
            }
        }

        // Handle cluster_id2 (only add if different from cluster_id)
        if (isset($data['cluster_id2']) && !empty($data['cluster_id2'])) {
            $clusterId2 = $this->validateClusterId($data['cluster_id2'], $filters);
            if ($clusterId2 && !in_array($clusterId2, $clusterIds)) {
                $clusterIds[] = $clusterId2;
            }
        }

        return array_unique($clusterIds);
    }

    /**
     * Validate cluster ID against hierarchical filters
     */
    private function validateClusterId(int $clusterId, array $filters = []): ?int
    {
        $query = Cluster::where('id', $clusterId);

        // Apply hierarchical filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        $cluster = $query->first();
        return $cluster ? $cluster->id : null;
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
