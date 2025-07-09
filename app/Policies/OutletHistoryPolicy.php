<?php

namespace App\Policies;

use App\Models\OutletHistory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutletHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_outlet::history');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OutletHistory $outletHistory): bool
    {
        return $user->hasPermission('view_outlet::history');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_outlet::history');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OutletHistory $outletHistory): bool
    {
        return $user->hasPermission('update_outlet::history');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_outlet::history');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OutletHistory $outletHistory): bool
    {
        return $user->hasPermission('delete_outlet::history');
    }


}