<?php

namespace App\Policies;

use App\Models\Visit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_visit');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Visit $visit): bool
    {
        return $user->hasPermission('view_visit');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_visit');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Visit $visit): bool
    {
        return $user->hasPermission('update_visit');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_visit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Visit $visit): bool
    {
        return $user->hasPermission('delete_visit');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('restore_any_visit');
    }

    public function restore(User $user, Visit $visit): bool
    {
        return $user->hasPermission('restore_visit');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('force_delete_any_visit');
    }

    public function forceDelete(User $user, Visit $visit): bool
    {
        return $user->hasPermission('force_delete_visit');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('export_visit');
    }
    public function import(User $user): bool
    {
        return $user->hasPermission('import_visit');
    }
}