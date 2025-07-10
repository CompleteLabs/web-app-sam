<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\PlanVisit;
use App\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PlanVsActualVisitChart extends ChartWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Plan vs Actual Visits (Last 7 Days)';
    protected static ?int $sort = 8;

    protected function getData(): array
    {
        $data = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');

            // Apply user scope to planned visits through outlet relationship
            $plannedQuery = PlanVisit::whereDate('visit_date', $date)
                ->whereHas('outlet', function ($outletQuery) {
                    $this->applyUserScopeToOutlets($outletQuery);
                });
            $plannedCount = $plannedQuery->count();

            // Apply user scope to actual visits
            $actualQuery = Visit::whereDate('visit_date', $date)
                ->whereNull('deleted_at');
            $actualQuery = $this->applyUserScopeToVisits($actualQuery);
            $actualCount = $actualQuery->count();

            $data->push([
                'date' => $date,
                'planned' => $plannedCount,
                'actual' => $actualCount,
                'completion_rate' => $plannedCount > 0 ? round(($actualCount / $plannedCount) * 100, 1) : 0
            ]);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Planned Visits',
                    'data' => $data->pluck('planned')->toArray(),
                    'backgroundColor' => 'rgba(147, 51, 234, 0.8)',
                    'borderColor' => 'rgb(147, 51, 234)',
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Actual Visits',
                    'data' => $data->pluck('actual')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $data->map(function ($item) {
                return date('M j', strtotime($item['date']));
            })->toArray(),
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
                'tooltip' => [
                    'callbacks' => [
                        'afterBody' => 'function(context) {
                            if (context.length > 1) {
                                const planned = context[0].parsed.y;
                                const actual = context[1].parsed.y;
                                const rate = planned > 0 ? Math.round((actual / planned) * 100) : 0;
                                return "Completion Rate: " + rate + "%";
                            }
                        }'
                    ]
                ]
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ]
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
