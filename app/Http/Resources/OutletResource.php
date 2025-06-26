<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OutletResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'owner_name' => $this->owner_name,
            'owner_phone' => $this->owner_phone,
            'address' => $this->address,
            'location' => $this->location,
            'district' => $this->district,
            'status' => $this->status,
            'level' => $this->level,
            'radius' => $this->radius,
            'badan_usaha_id' => $this->badan_usaha_id,
            'division_id' => $this->division_id,
            'region_id' => $this->region_id,
            'cluster_id' => $this->cluster_id,
            'badan_usaha' => BadanUsahaResource::make($this->whenLoaded('badanUsaha')),
            'division' => DivisionResource::make($this->whenLoaded('division')),
            'region' => RegionResource::make($this->whenLoaded('region')),
            'cluster' => ClusterResource::make($this->whenLoaded('cluster')),
            'photos' => [
                'shop_sign' => $this->photo_shop_sign,
                'front' => $this->photo_front,
                'left' => $this->photo_left,
                'right' => $this->photo_right,
                'id_card' => $this->photo_id_card,
            ],
            'video' => $this->video,
        ];
    }
}
