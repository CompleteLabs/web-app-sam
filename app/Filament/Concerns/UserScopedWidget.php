<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait UserScopedWidget
{
    /**
     * Apply user scope to query builder for outlets
     *
     * @param Builder $query
     * @param string $tableAlias - Table alias to use (default: 'outlets')
     * @return Builder
     */
    protected function applyUserScopeToOutlets(Builder $query, string $tableAlias = 'outlets'): Builder
    {
        $user = Auth::user();
        $scopes = $user->userScopes;

        return $query->where(function ($q) use ($scopes, $tableAlias) {
            foreach ($scopes as $scope) {
                $q->orWhere(function ($sub) use ($scope, $tableAlias) {
                    if ($scope->cluster_id) {
                        // Handle JSON array for cluster_id
                        $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                        $sub->whereIn("{$tableAlias}.cluster_id", $clusterIds);
                    } elseif ($scope->region_id) {
                        // Handle JSON array for region_id
                        $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                        $sub->whereIn("{$tableAlias}.region_id", $regionIds);
                    } elseif ($scope->division_id) {
                        // Handle JSON array for division_id
                        $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                        $sub->whereIn("{$tableAlias}.division_id", $divisionIds);
                    } elseif ($scope->badan_usaha_id) {
                        // Handle JSON array for badan_usaha_id
                        $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                        $sub->whereIn("{$tableAlias}.badan_usaha_id", $badanUsahaIds);
                    }
                });
            }
        });
    }

    /**
     * Apply user scope to query builder for users
     *
     * @param Builder $query
     * @return Builder
     */
    protected function applyUserScopeToUsers(Builder $query): Builder
    {
        $user = Auth::user();
        $scopes = $user->userScopes;

        return $query->where(function ($q) use ($scopes) {
            foreach ($scopes as $scope) {
                $q->orWhereHas('userScopes', function ($sub) use ($scope) {
                    if (!empty($scope->cluster_id)) {
                        $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                        $sub->where(function ($w) use ($clusterIds) {
                            foreach ($clusterIds as $id) {
                                $w->orWhereJsonContains('cluster_id', $id);
                            }
                        });
                    } elseif (!empty($scope->region_id)) {
                        $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                        $sub->where(function ($w) use ($regionIds) {
                            foreach ($regionIds as $id) {
                                $w->orWhereJsonContains('region_id', $id);
                            }
                        });
                    } elseif (!empty($scope->division_id)) {
                        $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                        $sub->where(function ($w) use ($divisionIds) {
                            foreach ($divisionIds as $id) {
                                $w->orWhereJsonContains('division_id', $id);
                            }
                        });
                    } elseif (!empty($scope->badan_usaha_id)) {
                        $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                        $sub->where(function ($w) use ($badanUsahaIds) {
                            foreach ($badanUsahaIds as $id) {
                                $w->orWhereJsonContains('badan_usaha_id', $id);
                            }
                        });
                    }
                });
            }
        });
    }

    /**
     * Apply user scope to query builder for visits
     *
     * @param Builder $query
     * @return Builder
     */
    protected function applyUserScopeToVisits(Builder $query): Builder
    {
        $user = Auth::user();
        $scopes = $user->userScopes;

        return $query->whereHas('outlet', function ($outletQuery) use ($scopes) {
            $outletQuery->where(function ($q) use ($scopes) {
                foreach ($scopes as $scope) {
                    $q->orWhere(function ($sub) use ($scope) {
                        if ($scope->cluster_id) {
                            $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                            $sub->whereIn('outlets.cluster_id', $clusterIds);
                        } elseif ($scope->region_id) {
                            $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                            $sub->whereIn('outlets.region_id', $regionIds);
                        } elseif ($scope->division_id) {
                            $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                            $sub->whereIn('outlets.division_id', $divisionIds);
                        } elseif ($scope->badan_usaha_id) {
                            $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                            $sub->whereIn('outlets.badan_usaha_id', $badanUsahaIds);
                        }
                    });
                }
            });
        });
    }

    /**
     * Apply user scope to query builder for outlet histories
     *
     * @param Builder $query
     * @return Builder
     */
    protected function applyUserScopeToOutletHistories(Builder $query): Builder
    {
        $user = Auth::user();
        $scopes = $user->userScopes;

        return $query->whereHas('outlet', function ($outletQuery) use ($scopes) {
            $outletQuery->where(function ($q) use ($scopes) {
                foreach ($scopes as $scope) {
                    $q->orWhere(function ($sub) use ($scope) {
                        if ($scope->cluster_id) {
                            $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                            $sub->whereIn('outlets.cluster_id', $clusterIds);
                        } elseif ($scope->region_id) {
                            $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                            $sub->whereIn('outlets.region_id', $regionIds);
                        } elseif ($scope->division_id) {
                            $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                            $sub->whereIn('outlets.division_id', $divisionIds);
                        } elseif ($scope->badan_usaha_id) {
                            $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                            $sub->whereIn('outlets.badan_usaha_id', $badanUsahaIds);
                        }
                    });
                }
            });
        });
    }
}
