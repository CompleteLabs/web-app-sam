<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $casts = [
        'scope_required_fields' => 'array',
        'scope_multiple_fields' => 'array',
    ];

    protected $fillable = [
        'id',
        'name',
        'parent_id',
        'can_access_web',
        'can_access_mobile',
        'scope_required_fields',
        'scope_multiple_fields',
        'created_at',
        'updated_at',
    ];

    public const LIST_COLUMNS = [
        'id',
        'name',
        'scope_required_fields',
        'scope_multiple_fields',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permissions');
    }

    public function parent()
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /**
     * Ambil semua descendant (anak, cucu, dst) secara rekursif
     */
    public function allDescendants()
    {
        $descendants = collect();
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->allDescendants());
        }

        return $descendants;
    }
}
