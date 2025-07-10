<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\OutletHistory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OutletProgressionWidget extends BaseWidget
{
    use UserScopedWidget;

    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // Pending Approvals - sama seperti OutletApprovalsWidget
        $pendingQuery = OutletHistory::query()
            ->with(['outlet'])
            ->whereHas('outlet', function ($q) {
                $q->where('level', 'NOO'); // Outlet level saat ini harus NOO
            })
            ->where(function ($q) {
                // Get latest history per outlet where status is not APPROVED/REJECTED
                $q->whereRaw('id IN (
                    SELECT MAX(id)
                    FROM outlet_histories oh2
                    WHERE oh2.outlet_id = outlet_histories.outlet_id
                )')
                    ->where(function ($statusQ) {
                        $statusQ->where('approval_status', '!=', 'APPROVED')
                            ->where('approval_status', '!=', 'REJECTED')
                            ->orWhereNull('approval_status');
                    });
            });

        // Apply user scope to outlet histories
        $pendingQuery = $this->applyUserScopeToOutletHistories($pendingQuery);
        $pendingCount = $pendingQuery->count();

        // NOO → MEMBER success rate with user scope
        $nooToMemberQuery = OutletHistory::query()
            ->where('from_level', 'NOO')
            ->where('to_level', 'MEMBER');
        $nooToMemberQuery = $this->applyUserScopeToOutletHistories($nooToMemberQuery);

        $nooToMember = $nooToMemberQuery->selectRaw('
                COUNT(*) as total_requests,
                COUNT(CASE WHEN approval_status = "APPROVED" THEN 1 END) as approved,
                COUNT(CASE WHEN approval_status = "REJECTED" THEN 1 END) as rejected,
                COUNT(CASE WHEN approval_status = "PENDING" THEN 1 END) as pending
            ')
            ->first();

        $nooToMemberRate = $nooToMember->total_requests > 0
            ? round(($nooToMember->approved / $nooToMember->total_requests) * 100, 1)
            : 0;

        // Recent trend (last 30 days)
        $recentNooToMemberQuery = OutletHistory::query()
            ->where('from_level', 'NOO')
            ->where('to_level', 'MEMBER')
            ->where('requested_at', '>=', now()->subDays(30));
        $recentNooToMemberQuery = $this->applyUserScopeToOutletHistories($recentNooToMemberQuery);

        $recentNooToMember = $recentNooToMemberQuery->selectRaw('
                COUNT(*) as total_requests,
                COUNT(CASE WHEN approval_status = "APPROVED" THEN 1 END) as approved
            ')
            ->first();

        $recentRate = $recentNooToMember->total_requests > 0
            ? round(($recentNooToMember->approved / $recentNooToMember->total_requests) * 100, 1)
            : 0;

        return [
            Stat::make('Pending Approvals', $pendingCount)
                ->description('NOO outlets awaiting approval for MEMBER upgrade')
                ->descriptionIcon('heroicon-m-clock')
                ->chart([5, 8, 6, 12, 9, 15, $pendingCount])
                ->color($pendingCount > 10 ? 'warning' : ($pendingCount > 5 ? 'info' : 'success')),

            Stat::make('NOO → MEMBER Success Rate', $nooToMemberRate . '%')
                ->description("Approved: {$nooToMember->approved} | Rejected: {$nooToMember->rejected}")
                ->descriptionIcon('heroicon-m-star')
                ->chart([35, 42, 38, 51, 47, 59, $nooToMemberRate])
                ->color($nooToMemberRate >= 60 ? 'success' : ($nooToMemberRate >= 40 ? 'warning' : 'danger')),

            Stat::make('Recent Trend (30d)', $recentRate . '%')
                ->description("Recent NOO→MEMBER: {$recentNooToMember->approved}/{$recentNooToMember->total_requests}")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart([38, 45, 42, 48, 44, 52, $recentRate])
                ->color($recentRate >= $nooToMemberRate ? 'success' : 'warning'),
        ];
    }
}
