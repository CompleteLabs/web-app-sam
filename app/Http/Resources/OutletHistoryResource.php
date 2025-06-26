<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletHistoryResource extends JsonResource
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
            'outlet_id' => $this->outlet_id,
            'from_level' => $this->from_level,
            'to_level' => $this->to_level,
            'approval_status' => $this->approval_status,
            'approval_notes' => $this->approval_notes,
            'requested_at' => $this->requested_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'requested_by' => UserResource::make($this->whenLoaded('requestedBy')),
            'approved_by' => UserResource::make($this->whenLoaded('approvedBy')),
            'outlet' => OutletResource::make($this->whenLoaded('outlet')),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
