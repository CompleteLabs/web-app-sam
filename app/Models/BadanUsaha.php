<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadanUsaha extends Model
{
    use HasFactory;

    protected $table = 'badan_usahas';

    protected $fillable = [
        'name',
    ];

    public const LIST_COLUMNS = [
        'id',
        'name',
    ];

    // Relationships
    public function divisions()
    {
        return $this->hasMany(Division::class);
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
}
