<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Region extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'regions';

    protected $fillable = [
        'id',
        'badan_usaha_id',
        'division_id',
        'name',
        'created_at',
        'updated_at',
    ];

    public const LIST_COLUMNS = [
        'id',
        'name',
    ];

    public function badanUsaha()
    {
        return $this->belongsTo(BadanUsaha::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['badan_usaha_id', 'division_id', 'name']);
    }
}
