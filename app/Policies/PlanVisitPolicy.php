<?php

namespace App\Policies;

use App\Models\PlanVisit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanVisitPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_any_plan::visit');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PlanVisit $planVisit): bool
    {
        return $user->hasPermission('view_plan::visit');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_plan::visit');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PlanVisit $planVisit): bool
    {
        return $user->hasPermission('update_plan::visit');
    }

    /**
     * Determine whether the user can delete any models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasPermission('delete_any_plan::visit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PlanVisit $planVisit): bool
    {
        return $user->hasPermission('delete_plan::visit');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('export_plan::visit');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('import_plan::visit');
    }
}
