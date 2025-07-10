<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\Visit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DailyVisitTrendsChart extends ChartWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Daily Visit Trends (Last 30 Days)';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $query = Visit::selectRaw('
                DATE(visit_date) as date,
                COUNT(*) as total_visits,
                COUNT(CASE WHEN transaction = "YES" THEN 1 END) as successful_visits
            ')
            ->where('visit_date', '>=', now()->subDays(30))
            ->whereNull('deleted_at');

        // Apply user scope to visits
        $query = $this->applyUserScopeToVisits($query);

        $data = $query->groupBy(DB::raw('DATE(visit_date)'))
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Visits',
                    'data' => $data->pluck('total_visits')->toArray(),
                    'borderColor' => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'borderWidth' => 3,
                    'pointBackgroundColor' => 'rgb(99, 102, 241)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
                [
                    'label' => 'Visits with Transaction',
                    'data' => $data->pluck('successful_visits')->toArray(),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.08)',
                    'fill' => true,
                    'tension' => 0.4,
                    'borderWidth' => 3,
                    'pointBackgroundColor' => 'rgb(34, 197, 94)',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
            ],
            'labels' => $data->map(function ($item) {
                return date('M j', strtotime($item->date));
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
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
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }
}
