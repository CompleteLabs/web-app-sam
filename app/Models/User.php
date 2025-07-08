<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'photo',
        'password',
        'role_id',
        'tm_id',
        'notif_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public const LIST_COLUMNS = [
        'id',
        'username',
        'name',
        'email',
        'phone',
        'photo',
        'role_id',
        'tm_id',
        'notif_id',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->can_access_web == 1;
    }

    /**
     * Get the photo URL with automatic fallback to generated avatar
     */
    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }

        $name = urlencode($this->name ?? 'User');
        return "https://ui-avatars.com/api/?name={$name}&color=7F9CF5&background=EBF4FF&size=128";
    }

    // Relationships
    public function planVisits()
    {
        return $this->hasMany(PlanVisit::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function tm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tm_id');
    }

    // Add relationship for subordinates (users who report to this user)
    public function subordinates()
    {
        return $this->hasMany(User::class, 'tm_id');
    }

    // Add relationship for outlet histories where user requested changes
    public function requestedOutletHistories()
    {
        return $this->hasMany(OutletHistory::class, 'requested_by');
    }

    // Add relationship for outlet histories where user approved changes
    public function approvedOutletHistories()
    {
        return $this->hasMany(OutletHistory::class, 'approved_by');
    }

    public function userScopes()
    {
        return $this->hasMany(UserScope::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    // Add relationship to organizational entities (through user scopes)
    public function badanUsahas()
    {
        return $this->hasManyThrough(
            BadanUsaha::class,
            UserScope::class,
            'user_id',
            'id',
            'id',
            'badan_usaha_id'
        );
    }

    /**
     * Get user's organizational scope using the UserScope relation
     */
    public function getOrganizationalScope()
    {
        $scope = $this->userScopes->first();
        if (!$scope) {
            return null;
        }

        return [
            'badan_usaha' => $scope->badan_usaha_list,
            'division' => $scope->division_list,
            'region' => $scope->region_list,
            'cluster' => $scope->cluster_list,
        ];
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->role
            && $this->role->permissions()
                ->where('name', $permissionName)
                ->exists();
    }

    public function getAllPermissions(): array
    {
        if (! $this->role) {
            return [];
        }
        // Pastikan relasi permissions sudah di-load
        $permissions = $this->role->permissions;
        if (! $permissions) {
            $permissions = $this->role->permissions()->get();
        }

        return $permissions->pluck('name')->toArray();
    }

    /**
     * Get the options for the activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
        ->logOnly([
            'name', 'username', 'email', 'phone', 'photo',
            'role_id', 'tm_id', 'notif_id'
        ]);
    }
}
