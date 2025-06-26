<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Models\Permission;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $permissionsToSync = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing permissions as a simple array
        $existingPermissions = $this->record->permissions->pluck('name')->toArray();
        $data['permissions'] = $existingPermissions;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract permissions - now it's a simple array
        $permissions = $data['permissions'] ?? [];

        // Store permissions separately to sync after save
        $this->permissionsToSync = $permissions;

        // Remove permissions from data as it's not a model attribute
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync permissions after role is saved
        if (isset($this->permissionsToSync)) {
            // Flatten nested arrays if any
            $flatPermissions = collect($this->permissionsToSync)->flatten()->unique()->values()->all();
            $permissionIds = Permission::whereIn('name', $flatPermissions)->pluck('id');
            $this->record->permissions()->sync($permissionIds);
        }
    }
}
