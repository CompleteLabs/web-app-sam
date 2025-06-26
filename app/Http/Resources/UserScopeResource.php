<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserScopeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'badan_usaha_id' => $this->badan_usaha_id,
            'division_id' => $this->division_id,
            'region_id' => $this->region_id,
            'cluster_id' => $this->cluster_id,
            'badan_usaha' => BadanUsahaResource::make($this->whenLoaded('badanUsaha')),
            'division' => DivisionResource::make($this->whenLoaded('division')),
            'region' => RegionResource::make($this->whenLoaded('region')),
            'cluster' => ClusterResource::make($this->whenLoaded('cluster')),
        ];
    }
}
