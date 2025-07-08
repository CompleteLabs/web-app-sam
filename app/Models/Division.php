<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Division extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'divisions';

    protected $fillable = [
        'badan_usaha_id',
        'name',
    ];

    public const LIST_COLUMNS = [
        'id',
        'name',
    ];

    public function badanUsaha()
    {
        return $this->belongsTo(BadanUsaha::class);
    }

    public function regions()
    {
        return $this->hasMany(Region::class);
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
        ->logOnly(['badan_usaha_id', 'name']);
    }
}
