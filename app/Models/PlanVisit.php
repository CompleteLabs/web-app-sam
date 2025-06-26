<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'outlet_id',
        'visit_date',
    ];

    protected $casts = [
        'visit_date' => 'datetime',
    ];

    public const LIST_COLUMNS = [
        'id',
        'visit_date',
        'user_id',
        'outlet_id',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('visit_date', today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('visit_date', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where('visit_date', '<', now());
    }
}
