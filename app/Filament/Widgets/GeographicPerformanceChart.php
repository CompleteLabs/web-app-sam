<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\Outlet;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GeographicPerformanceChart extends ChartWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Top 10 Districts by Outlet Count';
    protected static ?int $sort = 7;

    protected function getData(): array
    {
        $query = Outlet::selectRaw('
                district,
                COUNT(*) as outlet_count,
                COUNT(CASE WHEN level = "MEMBER" THEN 1 END) as member_count,
                COUNT(CASE WHEN status = "MAINTAIN" THEN 1 END) as maintain_count
            ')
            ->whereNull('deleted_at');

        // Apply user scope to outlets
        $query = $this->applyUserScopeToOutlets($query);

        $data = $query->groupBy('district')
            ->orderByDesc('outlet_count')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Outlets',
                    'data' => $data->pluck('outlet_count')->toArray(),
                    'backgroundColor' => 'rgba(99, 102, 241, 0.8)',
                    'borderColor' => 'rgb(99, 102, 241)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'MEMBER Level',
                    'data' => $data->pluck('member_count')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'MAINTAIN Status',
                    'data' => $data->pluck('maintain_count')->toArray(),
                    'backgroundColor' => 'rgba(251, 191, 36, 0.8)',
                    'borderColor' => 'rgb(251, 191, 36)',
                    'borderWidth' => 2,
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $data->pluck('district')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ]
                ],
                'x' => [
                    'ticks' => [
                        'maxRotation' => 45,
                    ]
                ]
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
