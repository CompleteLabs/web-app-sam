<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_user');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('view_user');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('update_user');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_user');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('delete_user');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('restore_any_user');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission('restore_user');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('force_delete_any_user');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermission('force_delete_user');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('export_user');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('import_user');
    }
}
