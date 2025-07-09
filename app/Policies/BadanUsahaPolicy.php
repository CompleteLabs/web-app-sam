<?php

namespace App\Policies;

use App\Models\BadanUsaha;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BadanUsahaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_badan::usaha');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BadanUsaha $badanUsaha): bool
    {
        return $user->hasPermission('view_badan::usaha');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_badan::usaha');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BadanUsaha $badanUsaha): bool
    {
        return $user->hasPermission('update_badan::usaha');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_badan::usaha');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BadanUsaha $badanUsaha): bool
    {
        return $user->hasPermission('delete_badan::usaha');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('restore_any_badan::usaha');
    }

    public function restore(User $user, BadanUsaha $badanUsaha): bool
    {
        return $user->hasPermission('restore_badan::usaha');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('force_delete_any_badan::usaha');
    }

    public function forceDelete(User $user, BadanUsaha $badanUsaha): bool
    {
        return $user->hasPermission('force_delete_badan::usaha');
    }

}