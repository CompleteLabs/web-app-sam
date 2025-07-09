<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Visit extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'id',
        'visit_date',
        'user_id',
        'outlet_id',
        'type',
        'checkin_location',
        'checkout_location',
        'checkin_time',
        'checkout_time',
        'checkin_photo',
        'checkout_photo',
        'duration',
        'transaction',
        'report',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'visit_date' => 'datetime',
        'checkin_time' => 'datetime',
        'checkout_time' => 'datetime',
        'transaction' => 'string',
        'duration' => 'integer',
    ];

    // Kolom yang ditampilkan pada list
    public const LIST_COLUMNS = [
        'id',
        'visit_date',
        'user_id',
        'outlet_id',
        'type',
        'checkin_time',
        'checkout_time',
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

    public function scopeWithTransaction($query)
    {
        return $query->where('transaction', 'YES');
    }

    public function scopeWithoutTransaction($query)
    {
        return $query->where('transaction', 'NO');
    }

    public function scopeCheckedIn($query)
    {
        return $query->whereNotNull('checkin_time');
    }

    public function scopeCheckedOut($query)
    {
        return $query->whereNotNull('checkout_time');
    }

    // Accessors
    public function getDurationFormattedAttribute()
    {
        if (! $this->duration) {
            return null;
        }

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    // Mutators
    public function calculateDuration()
    {
        if ($this->checkin_time && $this->checkout_time) {
            $this->duration = $this->checkin_time->diffInMinutes($this->checkout_time);
            $this->save();
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'visit_date', 'user_id', 'outlet_id', 'type',
                'checkin_location', 'checkout_location',
                'checkin_time', 'checkout_time',
                'transaction', 'report', 'duration'
            ]);
    }
}
