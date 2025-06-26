<?php

namespace App\Helpers;

use App\Models\Role;

class ScopeHelper
{
    public static function canBeMultiple($field, $roleId)
    {
        if (! $roleId) {
            return false;
        }
        $role = Role::find($roleId);
        if (! $role || ! $role->scope_multiple_fields) {
            return false;
        }
        $fields = is_array($role->scope_multiple_fields)
            ? $role->scope_multiple_fields
            : json_decode($role->scope_multiple_fields, true);

        return in_array($field, $fields ?? []);
    }

    public static function isRequired($field, $roleId)
    {
        if (! $roleId) {
            return false;
        }
        $role = Role::find($roleId);
        if (! $role || ! $role->scope_required_fields) {
            return false;
        }
        $fields = is_array($role->scope_required_fields)
            ? $role->scope_required_fields
            : json_decode($role->scope_required_fields, true);

        return in_array($field, $fields ?? []);
    }

    public static function isVisible($field, $roleId)
    {
        // Contoh: visible jika required atau multiple
        return self::isRequired($field, $roleId) || self::canBeMultiple($field, $roleId);
    }
}
