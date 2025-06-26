<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanVisitResource extends JsonResource
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
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'visit_date' => $this->visit_date,
            'outlet' => OutletResource::make($this->whenLoaded('outlet')),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
