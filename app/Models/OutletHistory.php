<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'from_level',
        'to_level',
        'requested_by',
        'approved_by',
        'approval_status',
        'approval_notes',
        'requested_at',
        'approved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';
    const STATUS_AUTO_APPROVED = 'AUTO_APPROVED';

    const OUTLET_LEVEL_LEAD = 'LEAD';
    const OUTLET_LEVEL_NOO = 'NOO';
    const OUTLET_LEVEL_MEMBER = 'MEMBER';

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
