<?php

namespace App\Services;

use App\Models\User;
use App\Models\Outlet;
use App\Notifications\OutletApprovalNotification;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    /**
     * Send outlet approval notification
     */
    public static function sendOutletApproval(Outlet $outlet, string $newCode = null, int $newLimit = null)
    {
        // Find the user who requested this (from outlet_histories)
        $lastHistory = $outlet->outletHistories()
            ->where('approval_status', 'APPROVED')
            ->latest()
            ->first();

        if ($lastHistory && $lastHistory->requested_by) {
            $requestedBy = User::find($lastHistory->requested_by);

            if ($requestedBy) {
                $requestedBy->notify(
                    new OutletApprovalNotification(
                        $outlet,
                        'approved',
                        Auth::user(),
                        null,
                        $newCode,
                        $newLimit
                    )
                );
            }
        }
    }

    /**
     * Send outlet rejection notification
     */
    public static function sendOutletRejection(Outlet $outlet, string $reason = null)
    {
        // Find the user who requested this (from outlet_histories)
        $lastHistory = $outlet->outletHistories()
            ->where('approval_status', 'REJECTED')
            ->latest()
            ->first();

        if ($lastHistory && $lastHistory->requested_by) {
            $requestedBy = User::find($lastHistory->requested_by);

            if ($requestedBy) {
                $requestedBy->notify(
                    new OutletApprovalNotification(
                        $outlet,
                        'rejected',
                        Auth::user(),
                        $reason
                    )
                );
            }
        }
    }

    /**
     * Send custom notification
     */
    public static function sendCustomNotification(User $user, array $data)
    {
        $user->notify(new \App\Notifications\CustomNotification($data));
    }

    /**
     * Send notification to multiple users
     */
    public static function sendToMultipleUsers(array $userIds, $notification)
    {
        $users = User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }

    /**
     * Send notification based on user scope
     */
    public static function sendToScope(array $scope, $notification)
    {
        $query = User::query();

        if (isset($scope['cluster_id'])) {
            $query->whereHas('userScopes', function ($q) use ($scope) {
                $q->whereJsonContains('cluster_id', $scope['cluster_id']);
            });
        } elseif (isset($scope['region_id'])) {
            $query->whereHas('userScopes', function ($q) use ($scope) {
                $q->whereJsonContains('region_id', $scope['region_id']);
            });
        } elseif (isset($scope['division_id'])) {
            $query->whereHas('userScopes', function ($q) use ($scope) {
                $q->whereJsonContains('division_id', $scope['division_id']);
            });
        } elseif (isset($scope['badan_usaha_id'])) {
            $query->whereHas('userScopes', function ($q) use ($scope) {
                $q->whereJsonContains('badan_usaha_id', $scope['badan_usaha_id']);
            });
        }

        $users = $query->get();

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }
}
