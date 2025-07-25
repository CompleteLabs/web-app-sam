<?php

namespace App\Policies;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClusterPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_cluster');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cluster $cluster): bool
    {
        return $user->hasPermission('view_cluster');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_cluster');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cluster $cluster): bool
    {
        return $user->hasPermission('update_cluster');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_cluster');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cluster $cluster): bool
    {
        return $user->hasPermission('delete_cluster');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasPermission('restore_any_cluster');
    }

    public function restore(User $user, Cluster $cluster): bool
    {
        return $user->hasPermission('restore_cluster');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasPermission('force_delete_any_cluster');
    }

    public function forceDelete(User $user, Cluster $cluster): bool
    {
        return $user->hasPermission('force_delete_cluster');
    }

}