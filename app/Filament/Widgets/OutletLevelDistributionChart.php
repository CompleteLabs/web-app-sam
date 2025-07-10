<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\Outlet;
use Filament\Widgets\ChartWidget;

class OutletLevelDistributionChart extends ChartWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Outlet Level Distribution';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $query = Outlet::selectRaw('level, COUNT(*) as count')
            ->whereNull('deleted_at');

        // Apply user scope to outlets
        $query = $this->applyUserScopeToOutlets($query);

        $data = $query->groupBy('level')
            ->orderBy('level')
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        'rgb(34, 197, 94)',   // Modern green for LEAD
                        'rgb(251, 191, 36)',  // Modern yellow for NOO
                        'rgb(59, 130, 246)',  // Modern blue for MEMBER
                    ],
                    'borderColor' => [
                        'rgb(21, 128, 61)',   // Darker green
                        'rgb(217, 119, 6)',   // Darker yellow
                        'rgb(37, 99, 235)',   // Darker blue
                    ],
                    'borderWidth' => 3,
                    'hoverBorderWidth' => 4,
                    'cutout' => '65%',
                ],
            ],
            'labels' => $data->pluck('level')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                        'font' => [
                            'size' => 12,
                            'weight' => '500',
                        ],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleColor' => '#fff',
                    'bodyColor' => '#fff',
                    'borderColor' => 'rgba(255, 255, 255, 0.1)',
                    'borderWidth' => 1,
                    'cornerRadius' => 8,
                    'callbacks' => [
                        'label' => 'function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed * 100) / total);
                            return context.label + ": " + context.parsed + " (" + percentage + "%)";
                        }'
                    ]
                ]
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
            'elements' => [
                'arc' => [
                    'borderRadius' => 4,
                ]
            ],
        ];
    }
}
