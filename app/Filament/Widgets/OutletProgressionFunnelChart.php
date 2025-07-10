<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\Outlet;
use App\Models\Visit;
use App\Models\OutletHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OutletProgressionFunnelChart extends ChartWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Funnel Progres Outlet';
    protected static ?int $sort = 10;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get current outlet counts at each level with user scope
        $leadQuery = Outlet::where('level', 'LEAD')->whereNull('deleted_at');
        $leadQuery = $this->applyUserScopeToOutlets($leadQuery);
        $leadOutlets = $leadQuery->count();

        $nooQuery = Outlet::where('level', 'NOO')->whereNull('deleted_at');
        $nooQuery = $this->applyUserScopeToOutlets($nooQuery);
        $nooOutlets = $nooQuery->count();

        $memberQuery = Outlet::where('level', 'MEMBER')->whereNull('deleted_at');
        $memberQuery = $this->applyUserScopeToOutlets($memberQuery);
        $memberOutlets = $memberQuery->count();

        // Get approved NOO to MEMBER upgrades from history with user scope
        $nooToMemberQuery = OutletHistory::where('from_level', 'NOO')
            ->where('to_level', 'MEMBER')
            ->where('approval_status', 'APPROVED');
        $nooToMemberQuery = $this->applyUserScopeToOutletHistories($nooToMemberQuery);
        $nooToMemberApproved = $nooToMemberQuery->count();

        return [
            'datasets' => [
                [
                    'label' => 'Outlet',
                    'data' => [$leadOutlets, $nooOutlets, $nooToMemberApproved, $memberOutlets],
                    'backgroundColor' => [
                        'rgba(251, 191, 36, 0.8)',   // LEAD - amber
                        'rgba(99, 102, 241, 0.8)',   // NOO - indigo
                        'rgba(34, 197, 94, 0.8)',    // NOO→MEMBER approved - green
                        'rgba(16, 185, 129, 0.8)',   // Current MEMBER - emerald
                    ],
                    'borderColor' => [
                        'rgb(251, 191, 36)',
                        'rgb(99, 102, 241)',
                        'rgb(34, 197, 94)',
                        'rgb(16, 185, 129)',
                    ],
                    'borderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => [
                'LEAD',
                'NOO',
                'NOO → MEMBER (Disetujui)',
                'MEMBER Saat Ini'
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
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
                            return context.dataset.label + ": " + context.parsed.x;
                        }'
                    ]
                ]
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.05)',
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ]
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ]
            ],
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
