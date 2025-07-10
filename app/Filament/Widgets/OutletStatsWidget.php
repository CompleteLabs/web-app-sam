<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use App\Models\OutletHistory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OutletStatsWidget extends BaseWidget
{
    use UserScopedWidget;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Get outlet distribution by level with user scope
        $outletQuery = Outlet::query()->whereNull('deleted_at');
        $outletQuery = $this->applyUserScopeToOutlets($outletQuery);

        $outletsByLevel = $outletQuery->selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->get()
            ->pluck('count', 'level');

        $totalOutlets = $outletsByLevel->sum();

        // Get today's visit stats with user scope
        $todayVisitsQuery = Visit::query()
            ->whereDate('visit_date', today())
            ->whereNull('deleted_at');
        $todayVisitsQuery = $this->applyUserScopeToVisits($todayVisitsQuery);

        $todayVisitsCount = $todayVisitsQuery->count();
        $todayTransactions = $todayVisitsQuery->where('transaction', 'YES')->count();
        $transactionRate = $todayVisitsCount > 0 ? round(($todayTransactions / $todayVisitsCount) * 100, 1) : 0;

        // Get active sales count with user scope
        $activeSalesQuery = User::query()->whereNull('deleted_at');
        $activeSalesQuery = $this->applyUserScopeToUsers($activeSalesQuery);
        $activeSales = $activeSalesQuery->count();

        return [
            Stat::make('Total Outlets', number_format($totalOutlets))
                ->description('LEAD: ' . ($outletsByLevel['LEAD'] ?? 0) . ' | NOO: ' . ($outletsByLevel['NOO'] ?? 0) . ' | MEMBER: ' . ($outletsByLevel['MEMBER'] ?? 0))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->chart([7, 12, 11, 18, 23, 21, 24])
                ->color('slate'),

            Stat::make('Today\'s Visits', number_format($todayVisitsCount))
                ->description("With Transaction: {$todayTransactions} ({$transactionRate}%)")
                ->descriptionIcon('heroicon-m-map-pin')
                ->chart([3, 8, 6, 12, 15, 18, $todayVisitsCount])
                ->color($transactionRate >= 70 ? 'success' : ($transactionRate >= 50 ? 'warning' : 'danger')),

            Stat::make('Active Sales', number_format($activeSales))
                ->description('Field personnel')
                ->descriptionIcon('heroicon-m-users')
                ->chart([12, 15, 18, 21, 19, 22, $activeSales])
                ->color('info'),
        ];
    }
}
