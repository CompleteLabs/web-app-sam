<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\OutletStatsWidget::class,
            \App\Filament\Widgets\OutletProgressionWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\OutletLevelDistributionChart::class,
            \App\Filament\Widgets\DailyVisitTrendsChart::class,
            \App\Filament\Widgets\PlanVsActualVisitChart::class,
            \App\Filament\Widgets\GeographicPerformanceChart::class,
            \App\Filament\Widgets\TopSalesPerformanceWidget::class,
            \App\Filament\Widgets\OutletApprovalsWidget::class,
            \App\Filament\Widgets\RealTimeFieldActivityWidget::class,
        ];
    }
}
