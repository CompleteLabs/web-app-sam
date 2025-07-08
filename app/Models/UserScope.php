<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserScope extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'badan_usaha_id',
        'division_id',
        'region_id',
        'cluster_id',
    ];

    protected $casts = [
        'badan_usaha_id' => 'json',
        'division_id' => 'json',
        'region_id' => 'json',
        'cluster_id' => 'json',
    ];

    public const LIST_COLUMNS = [
        'id',
        'user_id',
        'badan_usaha_id',
        'division_id',
        'region_id',
        'cluster_id',
    ];

    /**
     * Set badan_usaha_id attribute - handle both single values and arrays
     */
    public function setBadanUsahaIdAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['badan_usaha_id'] = null;
        } elseif (is_array($value)) {
            $this->attributes['badan_usaha_id'] = json_encode(array_map('intval', $value));
        } else {
            $this->attributes['badan_usaha_id'] = json_encode([intval($value)]);
        }
    }

    /**
     * Set division_id attribute - handle both single values and arrays
     */
    public function setDivisionIdAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['division_id'] = null;
        } elseif (is_array($value)) {
            $this->attributes['division_id'] = json_encode(array_map('intval', $value));
        } else {
            $this->attributes['division_id'] = json_encode([intval($value)]);
        }
    }

    /**
     * Set region_id attribute - handle both single values and arrays
     */
    public function setRegionIdAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['region_id'] = null;
        } elseif (is_array($value)) {
            $this->attributes['region_id'] = json_encode(array_map('intval', $value));
        } else {
            $this->attributes['region_id'] = json_encode([intval($value)]);
        }
    }

    /**
     * Set cluster_id attribute - handle both single values and arrays
     */
    public function setClusterIdAttribute($value)
    {
        if (is_null($value)) {
            $this->attributes['cluster_id'] = null;
        } elseif (is_array($value)) {
            $this->attributes['cluster_id'] = json_encode(array_map('intval', $value));
        } else {
            $this->attributes['cluster_id'] = json_encode([intval($value)]);
        }
    }

    // Custom accessors for multi-id fields
    public function getBadanUsahaListAttribute()
    {
        $ids = $this->badan_usaha_id;
        if (empty($ids)) {
            return collect();
        }

        return BadanUsaha::whereIn('id', is_array($ids) ? $ids : [$ids])->get();
    }

    public function getDivisionListAttribute()
    {
        $ids = $this->division_id;
        if (empty($ids)) {
            return collect();
        }

        return Division::whereIn('id', is_array($ids) ? $ids : [$ids])->get();
    }

    public function getRegionListAttribute()
    {
        $ids = $this->region_id;
        if (empty($ids)) {
            return collect();
        }

        return Region::whereIn('id', is_array($ids) ? $ids : [$ids])->get();
    }

    public function getClusterListAttribute()
    {
        $ids = $this->cluster_id;
        if (empty($ids)) {
            return collect();
        }

        return Cluster::whereIn('id', is_array($ids) ? $ids : [$ids])->get();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
