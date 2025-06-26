<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Permission;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected array $permissionsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract permissions - now it's a simple array
        $permissions = $data['permissions'] ?? [];

        // Store permissions separately to sync after creation
        $this->permissionsToSync = $permissions;

        // Remove permissions from data as it's not a model attribute
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Sync permissions after role is created
        if (isset($this->permissionsToSync) && ! empty($this->permissionsToSync)) {
            // Flatten nested arrays if any
            $flatPermissions = collect($this->permissionsToSync)->flatten()->unique()->values()->all();
            $permissionIds = Permission::whereIn('name', $flatPermissions)->pluck('id');
            $this->record->permissions()->sync($permissionIds);
        }
    }
}
