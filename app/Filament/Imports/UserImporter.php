<?php

namespace App\Filament\Imports;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Hash;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    // Store scope data temporarily
    protected array $scopeData = [];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('username')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('password')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('role_name')
                ->label('Role Name')
                ->rules(['nullable', 'string']),
            ImportColumn::make('badan_usaha_names')
                ->label('Badan Usaha Names (semicolon separated)')
                ->rules(['nullable', 'string']),
            ImportColumn::make('division_names')
                ->label('Division Names (semicolon separated)')
                ->rules(['nullable', 'string']),
            ImportColumn::make('region_names')
                ->label('Region Names (semicolon separated)')
                ->rules(['nullable', 'string']),
            ImportColumn::make('cluster_names')
                ->label('Cluster Names (semicolon separated)')
                ->rules(['nullable', 'string']),
        ];
    }

    public function beforeFill(): void
    {
        // Hash password
        if (isset($this->data['password'])) {
            $this->data['password'] = Hash::make($this->data['password']);
        }

        // Set role_id from role_name if provided
        if (isset($this->data['role_name']) && ! empty($this->data['role_name'])) {
            $role = Role::where('name', $this->data['role_name'])->first();
            if ($role) {
                $this->data['role_id'] = $role->id;
            }
            unset($this->data['role_name']);
        }

        // Store scope data and remove from main data
        $this->scopeData = [];

        if (isset($this->data['badan_usaha_names'])) {
            $this->scopeData['badan_usaha_names'] = $this->data['badan_usaha_names'];
            unset($this->data['badan_usaha_names']);
        }

        if (isset($this->data['division_names'])) {
            $this->scopeData['division_names'] = $this->data['division_names'];
            unset($this->data['division_names']);
        }

        if (isset($this->data['region_names'])) {
            $this->scopeData['region_names'] = $this->data['region_names'];
            unset($this->data['region_names']);
        }

        if (isset($this->data['cluster_names'])) {
            $this->scopeData['cluster_names'] = $this->data['cluster_names'];
            unset($this->data['cluster_names']);
        }
    }

    public function resolveRecord(): ?User
    {
        // return User::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new User;
    }

    public function afterSave(): void
    {
        // Pastikan role_id sudah benar setelah save
        if (isset($this->data['role_id']) && $this->getRecord()->role_id !== $this->data['role_id']) {
            $this->getRecord()->role_id = $this->data['role_id'];
            $this->getRecord()->save();
        }

        // Create UserScope if any scope data is provided
        $scopeData = [];

        // Process Badan Usaha IDs
        $badanUsahaIds = $this->processScopeIds('badan_usaha_names', BadanUsaha::class);
        if (! empty($badanUsahaIds)) {
            $scopeData['badan_usaha_id'] = $badanUsahaIds;
        }

        // Process Division IDs
        $divisionIds = $this->processScopeIds('division_names', Division::class);
        if (! empty($divisionIds)) {
            $scopeData['division_id'] = $divisionIds;
        }

        // Process Region IDs
        $regionIds = $this->processScopeIds('region_names', Region::class);
        if (! empty($regionIds)) {
            $scopeData['region_id'] = $regionIds;
        }

        // Process Cluster IDs
        $clusterIds = $this->processScopeIds('cluster_names', Cluster::class);
        if (! empty($clusterIds)) {
            $scopeData['cluster_id'] = $clusterIds;
        }

        // Hierarchical validation
        $isValid = true;
        $errorMessages = [];

        // Validate Division -> BadanUsaha
        if (! empty($divisionIds) && ! empty($badanUsahaIds)) {
            $divisions = Division::whereIn('id', $divisionIds)->get();
            foreach ($divisions as $division) {
                if (! in_array($division->badan_usaha_id, $badanUsahaIds)) {
                    $isValid = false;
                    $errorMessages[] = "Division '{$division->name}' does not belong to selected Badan Usaha.";
                }
            }
        }

        // Validate Region -> Division & BadanUsaha
        if (! empty($regionIds) && ! empty($divisionIds)) {
            $regions = Region::whereIn('id', $regionIds)->get();
            foreach ($regions as $region) {
                if (! in_array($region->division_id, $divisionIds)) {
                    $isValid = false;
                    $errorMessages[] = "Region '{$region->name}' does not belong to selected Division.";
                }
                if (! empty($badanUsahaIds) && ! in_array($region->badan_usaha_id, $badanUsahaIds)) {
                    $isValid = false;
                    $errorMessages[] = "Region '{$region->name}' does not belong to selected Badan Usaha.";
                }
            }
        }

        // Validate Cluster -> Region, Division, BadanUsaha
        if (! empty($clusterIds)) {
            $clusters = Cluster::whereIn('id', $clusterIds)->get();
            foreach ($clusters as $cluster) {
                if (! empty($regionIds) && ! in_array($cluster->region_id, $regionIds)) {
                    $isValid = false;
                    $errorMessages[] = "Cluster '{$cluster->name}' does not belong to selected Region.";
                }
                if (! empty($divisionIds) && ! in_array($cluster->division_id, $divisionIds)) {
                    $isValid = false;
                    $errorMessages[] = "Cluster '{$cluster->name}' does not belong to selected Division.";
                }
                if (! empty($badanUsahaIds) && ! in_array($cluster->badan_usaha_id, $badanUsahaIds)) {
                    $isValid = false;
                    $errorMessages[] = "Cluster '{$cluster->name}' does not belong to selected Badan Usaha.";
                }
            }
        }

        // Only create UserScope if we have scope data and hierarchy is valid
        if (! empty($scopeData) && $isValid) {
            $scopeData['user_id'] = $this->getRecord()->id;
            UserScope::create($scopeData);
        } elseif (! $isValid) {
            // Optionally: log or handle $errorMessages for reporting
            // For now, just skip UserScope creation if invalid
        }
    }

    /**
     * Process scope IDs from name columns, respecting hierarchy for division, region, and cluster
     */
    private function processScopeIds(string $nameColumn, string $modelClass): array
    {
        $ids = [];
        if (! isset($this->scopeData[$nameColumn]) || empty($this->scopeData[$nameColumn])) {
            return $ids;
        }
        $names = array_map('trim', explode(';', $this->scopeData[$nameColumn]));
        $names = array_filter($names);
        if (empty($names)) {
            return $ids;
        }

        // For Division, Region, Cluster: filter by parent scope
        if ($modelClass === Division::class) {
            // Only include divisions that belong to selected badanusaha
            $badanUsahaIds = $this->processScopeIds('badan_usaha_names', BadanUsaha::class);
            $query = Division::whereIn('name', $names);
            if (! empty($badanUsahaIds)) {
                $query->whereIn('badan_usaha_id', $badanUsahaIds);
            }
            $ids = $query->pluck('id')->toArray();
        } elseif ($modelClass === Region::class) {
            // Only include regions that belong to selected division (and badanusaha)
            $divisionIds = $this->processScopeIds('division_names', Division::class);
            $badanUsahaIds = $this->processScopeIds('badan_usaha_names', BadanUsaha::class);
            $query = Region::whereIn('name', $names);
            if (! empty($divisionIds)) {
                $query->whereIn('division_id', $divisionIds);
            }
            if (! empty($badanUsahaIds)) {
                $query->whereIn('badan_usaha_id', $badanUsahaIds);
            }
            $ids = $query->pluck('id')->toArray();
        } elseif ($modelClass === Cluster::class) {
            // Only include clusters that belong to selected region, division, badanusaha
            $regionIds = $this->processScopeIds('region_names', Region::class);
            $divisionIds = $this->processScopeIds('division_names', Division::class);
            $badanUsahaIds = $this->processScopeIds('badan_usaha_names', BadanUsaha::class);
            $query = Cluster::whereIn('name', $names);
            if (! empty($regionIds)) {
                $query->whereIn('region_id', $regionIds);
            }
            if (! empty($divisionIds)) {
                $query->whereIn('division_id', $divisionIds);
            }
            if (! empty($badanUsahaIds)) {
                $query->whereIn('badan_usaha_id', $badanUsahaIds);
            }
            $ids = $query->pluck('id')->toArray();
        } else {
            // For BadanUsaha, just by name
            $ids = $modelClass::whereIn('name', $names)->pluck('id')->toArray();
        }

        return array_unique($ids);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your user import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
