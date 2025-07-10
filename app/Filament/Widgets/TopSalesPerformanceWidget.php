<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\User;
use App\Models\OutletHistory;
use App\Models\Visit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopSalesPerformanceWidget extends BaseWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Top Sales Performance (NOO → MEMBER Success Rate)';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    /**
     * Get the scoped query for top sales performance
     */
    protected function getScopedQuery(): Builder
    {
        $userQuery = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.username',
                DB::raw('COUNT(outlet_histories.id) as upgrade_requests'),
                DB::raw('COUNT(CASE WHEN outlet_histories.approval_status = "APPROVED" THEN 1 END) as approved_upgrades'),
                DB::raw('ROUND(COUNT(CASE WHEN outlet_histories.approval_status = "APPROVED" THEN 1 END) * 100.0 / COUNT(outlet_histories.id), 1) as success_rate'),
                DB::raw('COUNT(DISTINCT visits.id) as total_visits'),
                DB::raw('COUNT(CASE WHEN visits.transaction = "YES" THEN 1 END) as visits_with_transaction'),
            ])
            ->leftJoin('outlet_histories', function ($join) {
                $join->on('users.id', '=', 'outlet_histories.requested_by')
                    ->where('outlet_histories.from_level', '=', 'NOO')
                    ->where('outlet_histories.to_level', '=', 'MEMBER');
            })
            ->leftJoin('visits', function ($join) {
                $join->on('users.id', '=', 'visits.user_id')
                    ->where('visits.visit_date', '>=', now()->subDays(30))
                    ->whereNull('visits.deleted_at');
            })
            ->whereNull('users.deleted_at');

        // Apply user scope to users
        $userQuery = $this->applyUserScopeToUsers($userQuery);

        return $userQuery
            ->groupBy('users.id', 'users.name', 'users.username')
            ->havingRaw('COUNT(outlet_histories.id) > 0')
            ->orderByDesc('success_rate')
            ->orderByDesc('approved_upgrades')
            ->limit(10);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getScopedQuery())
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->state(function ($record, $livewire) {
                        static $rank = 0;
                        return ++$rank;
                    })
                    ->badge()
                    ->color(function ($record, $livewire) {
                        static $rank = 0;
                        $currentRank = ++$rank;
                        return match (true) {
                            $currentRank === 1 => 'warning',
                            $currentRank === 2 => 'gray',
                            $currentRank === 3 => 'info',
                            default => 'gray',
                        };
                    })
                    ->icon(function ($record, $livewire) {
                        static $rank = 0;
                        $currentRank = ++$rank;
                        return match (true) {
                            $currentRank === 1 => 'heroicon-m-trophy',
                            $currentRank === 2 => 'heroicon-m-star',
                            $currentRank === 3 => 'heroicon-m-fire',
                            default => null,
                        };
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Sales Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('upgrade_requests')
                    ->label('Requests')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('approved_upgrades')
                    ->label('Approved')
                    ->alignCenter()
                    ->sortable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('success_rate')
                    ->label('Success Rate')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->badge()
                    ->color(fn ($state) => $state >= 70 ? 'success' : ($state >= 50 ? 'warning' : 'danger'))
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('total_visits')
                    ->label('Visits (30d)')
                    ->alignCenter()
                    ->sortable()
                    ->color('info'),

                Tables\Columns\TextColumn::make('visits_with_transaction')
                    ->label('With Transaction')
                    ->alignCenter()
                    ->sortable()
                    ->color('success'),

                Tables\Columns\TextColumn::make('visit_transaction_rate')
                    ->label('Visit Success')
                    ->alignCenter()
                    ->state(function ($record) {
                        if ($record->total_visits > 0) {
                            return round(($record->visits_with_transaction / $record->total_visits) * 100, 1) . '%';
                        }
                        return '0%';
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->total_visits > 0) {
                            $rate = ($record->visits_with_transaction / $record->total_visits) * 100;
                            return $rate >= 70 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                        }
                        return 'gray';
                    }),
            ])
            ->defaultSort('success_rate', 'desc');
    }
}
