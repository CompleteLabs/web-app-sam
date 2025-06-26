<?php

namespace App\Policies;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutletPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_outlet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Outlet $outlet): bool
    {
        return $user->hasPermission('view_outlet');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_outlet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Outlet $outlet): bool
    {
        return $user->hasPermission('update_outlet');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_outlet');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Outlet $outlet): bool
    {
        return $user->hasPermission('delete_outlet');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('restore_any_outlet');
    }

    public function restore(User $user, Outlet $outlet): bool
    {
        return $user->hasPermission('restore_outlet');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('force_delete_any_outlet');
    }

    public function forceDelete(User $user, Outlet $outlet): bool
    {
        return $user->hasPermission('force_delete_outlet');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('export_outlet');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('import_outlet');
    }
}
