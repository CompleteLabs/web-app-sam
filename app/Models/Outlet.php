<?php

namespace App\Models;

use Apriansyahrs\CustomFields\Models\Concerns\UsesCustomFields;
use Apriansyahrs\CustomFields\Models\Contracts\HasCustomFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Outlet extends Model implements HasCustomFields
{
    use HasFactory, SoftDeletes, UsesCustomFields, LogsActivity;

    protected $fillable = [
        'code',
        'name',
        'address',
        'owner_name',
        'owner_phone',
        'badan_usaha_id',
        'division_id',
        'region_id',
        'cluster_id',
        'district',
        'photo_shop_sign',
        'photo_front',
        'photo_left',
        'photo_right',
        'photo_id_card',
        'video',
        'limit',
        'radius',
        'location',
        'status',
        'level',
    ];

    protected $casts = [
        'status' => 'string',
        'level' => 'string',
        'limit' => 'integer',
        'radius' => 'integer',
    ];

    public const LIST_COLUMNS = [
        'id',
        'code',
        'name',
        'district',
        'status',
        'radius',
        'location',
        'badan_usaha_id',
        'division_id',
        'region_id',
        'cluster_id',
        'level'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly([
            'code', 'name', 'address', 'owner_name', 'owner_phone',
            'badan_usaha_id', 'division_id', 'region_id', 'cluster_id',
            'district', 'status', 'level', 'limit', 'radius', 'location'
        ]);
    }

    public function outletHistories()
    {
        return $this->hasMany(\App\Models\OutletHistory::class, 'outlet_id');
    }
}
