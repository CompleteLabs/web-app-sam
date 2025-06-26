<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scope_required_fields' => $this->scope_required_fields,
            'permissions' => $this->when($this->relationLoaded('permissions'), function () {
                return $this->formatPermissions();
            }),
        ];
    }

    /**
     * Format role permissions safely
     */
    private function formatPermissions()
    {
        try {
            // Gunakan permissions dari relationship yang sudah di-load
            if ($this->relationLoaded('permissions')) {
                return $this->permissions->map(function ($permission) {
                    return [
                        // 'id' => $permission->id,
                        'name' => $permission->name,
                    ];
                });
            }

            // Return empty collection jika relationship belum di-load
            return collect([]);
        } catch (\Exception $e) {
            \Log::warning('Error formatting permissions in RoleResource', [
                'role_id' => $this->id ?? null,
                'error' => $e->getMessage(),
            ]);

            // Return empty collection jika ada error
            return collect([]);
        }
    }
}
