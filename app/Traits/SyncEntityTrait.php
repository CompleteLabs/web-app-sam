<?php

namespace App\Traits;

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
use Illuminate\Support\Facades\Log;

trait SyncEntityTrait
{
    /**
     * Sync entity based on type and data
     */
    protected function syncEntity(string $entityType, array $data): void
    {
        switch ($entityType) {
            case 'users':
                $this->syncUser($data);
                break;
            case 'outlets':
                $this->syncOutlet($data);
                break;
            case 'visits':
                $this->syncVisit($data);
                break;
            case 'planvisits':
                $this->syncPlanVisit($data);
                break;
            case 'roles':
                $this->syncRole($data);
                break;
            case 'badanusahas':
                $this->syncBadanUsaha($data);
                break;
            case 'divisions':
                $this->syncDivision($data);
                break;
            case 'regions':
                $this->syncRegion($data);
                break;
            case 'clusters':
                $this->syncCluster($data);
                break;
            default:
                throw new \Exception("Unknown entity type: {$entityType}");
        }
    }

    /**
     * Sync user entity
     */
    private function syncUser(array $data): void
    {
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
    }

    /**
     * Sync outlet entity
     */
    private function syncOutlet(array $data): void
    {
        Outlet::updateOrCreate(
            ['id' => $data['id']],
            [
                'code' => $data['kode_outlet'] ?? null,
                'name' => $data['nama_outlet'] ?? null,
                'address' => $data['alamat_outlet'] ?? null,
                'district' => $data['distric'] ?? null,
                'status' => $data['status_outlet'] ?? null,
                'location' => $data['latlong'] ?? null,
                'badan_usaha_id' => $data['badanusaha_id'] ?? null,
                'division_id' => $data['divisi_id'] ?? null,
                'region_id' => $data['region_id'] ?? null,
                'cluster_id' => $data['cluster_id'] ?? null,
                'limit' => $data['limit'] ?? null,
                'radius' => $data['radius'] ?? 100,
                'level' => isset($data['is_member']) ? ($data['is_member'] == 1 ? 'MEMBER' : 'LEAD') : null,
                'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
            ]
        );
    }

    /**
     * Sync visit entity
     */
    private function syncVisit(array $data): void
    {
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
    }

    /**
     * Sync plan visit entity
     */
    private function syncPlanVisit(array $data): void
    {
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
    }

    /**
     * Sync role entity
     */
    private function syncRole(array $data): void
    {
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
    }

    /**
     * Sync badan usaha entity
     */
    private function syncBadanUsaha(array $data): void
    {
        BadanUsaha::updateOrCreate(
            ['id' => $data['id']],
            [
                'name' => $data['name'] ?? null,
                'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
            ]
        );
    }

    /**
     * Sync division entity
     */
    private function syncDivision(array $data): void
    {
        Division::updateOrCreate(
            ['id' => $data['id']],
            [
                'badan_usaha_id' => $data['badanusaha_id'] ?? null,
                'name' => $data['name'] ?? null,
                'created_at' => isset($data['created_at']) ? \Carbon\Carbon::parse($data['created_at']) : now(),
                'updated_at' => isset($data['updated_at']) ? \Carbon\Carbon::parse($data['updated_at']) : now(),
            ]
        );
    }

    /**
     * Sync region entity
     */
    private function syncRegion(array $data): void
    {
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
    }

    /**
     * Sync cluster entity
     */
    private function syncCluster(array $data): void
    {
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
}
