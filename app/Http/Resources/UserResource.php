<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role_id' => $this->role_id,
            'tm_id' => $this->tm_id,
            'notif_id' => $this->when($this->shouldShowSensitiveData($request), $this->notif_id),
            'role' => RoleResource::make($this->whenLoaded('role')),
            'user_scopes' => UserScopeResource::collection($this->whenLoaded('userScopes')),
            'tm' => UserResource::make($this->whenLoaded('tm')),
        ];
    }

    /**
     * Determine if sensitive data should be shown
     */
    private function shouldShowSensitiveData(Request $request): bool
    {
        // Show sensitive data only if it's the authenticated user's own data
        return auth()->check() && auth()->id() === $this->id;
    }
}
