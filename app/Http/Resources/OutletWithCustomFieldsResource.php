<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletWithCustomFieldsResource extends JsonResource
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
            'address' => $this->address,
            'owner_name' => $this->owner_name,
            'owner_phone' => $this->owner_phone,
            'district' => $this->district,
            'location' => $this->location,
            'photo_shop_sign' => $this->photo_shop_sign,
            'photo_front' => $this->photo_front,
            'photo_left' => $this->photo_left,
            'photo_right' => $this->photo_right,
            'photo_id_card' => $this->photo_id_card,
            'video' => $this->video,
            'limit' => $this->limit,
            'radius' => $this->radius,
            'status' => $this->status,
            'level' => $this->level,

            // Badan Usaha relationship
            'badan_usaha' => [
                'id' => $this->badanUsaha->id,
                'name' => $this->badanUsaha->name,
            ],

            // Division relationship
            'division' => [
                'id' => $this->division->id,
                'name' => $this->division->name,
            ],

            // Region relationship
            'region' => [
                'id' => $this->region->id,
                'name' => $this->region->name,
            ],

            // Cluster relationship
            'cluster' => [
                'id' => $this->cluster->id,
                'name' => $this->cluster->name,
            ],

            // Custom Fields values
            'custom_fields' => $this->when(
                $this->relationLoaded('customFieldValues'),
                function () {
                    return $this->customFieldValues->mapWithKeys(function ($value) {
                        return [
                            $value->customField->code => [
                                'field_id' => $value->custom_field_id,
                                'field_code' => $value->customField->code,
                                'field_name' => $value->customField->name,
                                'field_type' => $value->customField->type,
                                'value' => $value->getValue(),
                                'raw_value' => [
                                    'string_value' => $value->string_value,
                                    'text_value' => $value->text_value,
                                    'boolean_value' => $value->boolean_value,
                                    'integer_value' => $value->integer_value,
                                    'float_value' => $value->float_value,
                                    'date_value' => $value->date_value,
                                    'datetime_value' => $value->datetime_value,
                                    'json_value' => $value->json_value,
                                ],
                            ]
                        ];
                    });
                }
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
