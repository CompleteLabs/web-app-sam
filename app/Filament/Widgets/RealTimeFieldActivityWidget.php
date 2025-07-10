<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\Visit;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RealTimeFieldActivityWidget extends BaseWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Real-time Field Activity (Today)';
    protected static ?int $sort = 9;
    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        $query = Visit::query()
            ->with(['user', 'outlet'])
            ->whereDate('visit_date', today())
            ->whereNull('deleted_at')
            ->orderByDesc('checkin_time')
            ->limit(20);

        // Apply user scope to visits
        $query = $this->applyUserScopeToVisits($query);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sales Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->limit(25)
                    ->tooltip(function ($record) {
                        return $record->outlet?->name;
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('outlet.district')
                    ->label('District')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Visit Type')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('checkin_time')
                    ->label('Check-in')
                    ->dateTime('H:i')
                    ->placeholder('Not checked in'),

                Tables\Columns\TextColumn::make('checkout_time')
                    ->label('Check-out')
                    ->dateTime('H:i')
                    ->placeholder('Still on visit'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->state(function ($record) {
                        if ($record->duration) {
                            $hours = floor($record->duration / 60);
                            $minutes = $record->duration % 60;
                            return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                        }

                        if ($record->checkin_time && !$record->checkout_time) {
                            $duration = $record->checkin_time->diffInMinutes(now());
                            $hours = floor($duration / 60);
                            $minutes = $duration % 60;
                            return "🔴 " . ($hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m");
                        }

                        return '-';
                    }),

                Tables\Columns\TextColumn::make('transaction')
                    ->label('Transaction')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'YES' => 'success',
                        'NO' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('Pending'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->state(function ($record) {
                        if (!$record->checkin_time) {
                            return 'Planned';
                        } elseif ($record->checkin_time && !$record->checkout_time) {
                            return 'On Visit';
                        } else {
                            return 'Completed';
                        }
                    })
                    ->badge()
                    ->color(function ($record) {
                        if (!$record->checkin_time) {
                            return 'gray';
                        } elseif ($record->checkin_time && !$record->checkout_time) {
                            return 'warning';
                        } else {
                            return 'success';
                        }
                    }),
            ])
            ->defaultSort('checkin_time', 'desc')
            ->emptyStateHeading('No Visit Activity Today')
            ->emptyStateDescription('No visits have been recorded for today.');
    }
}
