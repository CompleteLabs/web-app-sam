<?php

namespace App\Models;

use App\Concerns\HasTableViewsModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Cluster extends Model
{
    use HasFactory, HasTableViewsModel, LogsActivity;

    protected $table = 'clusters';

    protected $fillable = [
        'badan_usaha_id',
        'division_id',
        'region_id',
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

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
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
        ->logOnly(['badan_usaha_id', 'division_id', 'region_id', 'name']);
    }
}
