<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'visit_date' => $this->visit_date->format('Y-m-d'),
            'type' => $this->type,
            'checkin_time' => $this->checkin_time,
            'checkout_time' => $this->checkout_time,
            'checkin_location' => $this->checkin_location,
            'checkout_location' => $this->checkout_location,
            'duration' => $this->duration,
            // 'duration_formatted' => $this->getFormattedDuration(),
            'transaction' => $this->transaction,
            'report' => $this->report,
            // 'status' => $this->getVisitStatus(),
            'photos' => [
                'checkin' => $this->getPhotoUrl('checkin_photo'),
                'checkout' => $this->getPhotoUrl('checkout_photo'),
            ],
            'outlet' => OutletResource::make($this->whenLoaded('outlet')),
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }

    /**
     * Get formatted duration
     */
    private function getFormattedDuration(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        $hours = intdiv($this->duration, 60);
        $minutes = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%d jam %d menit', $hours, $minutes);
        }

        return sprintf('%d menit', $minutes);
    }

    /**
     * Get visit status
     */
    private function getVisitStatus(): string
    {
        if (! $this->checkin_time) {
            return 'not_started';
        } elseif ($this->checkin_time && ! $this->checkout_time) {
            return 'in_progress';
        } else {
            return 'completed';
        }
    }

    /**
     * Get photo URL for given field
     */
    private function getPhotoUrl(?string $field): ?string
    {
        if (! $field || ! $this->$field) {
            return null;
        }

        return Storage::url('visits/'.$this->$field);
    }
}
