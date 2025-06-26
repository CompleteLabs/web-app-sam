<?php

namespace App\Filament\Resources\OutletResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;

class OutletHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'outletHistories';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_level')
                    ->label('Dari Level')
                    ->badge(true)
                    ->color(fn($state) => match ($state) {
                        'LEAD' => 'warning',
                        'NOO' => 'info',
                        'MEMBER' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'LEAD' => 'heroicon-o-star',
                        'NOO' => 'heroicon-o-user-plus',
                        'MEMBER' => 'heroicon-o-user-group',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('to_level')
                    ->label('Ke Level')
                    ->badge(true)
                    ->color(fn($state) => match ($state) {
                        'LEAD' => 'warning',
                        'NOO' => 'info',
                        'MEMBER' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'LEAD' => 'heroicon-o-star',
                        'NOO' => 'heroicon-o-user-plus',
                        'MEMBER' => 'heroicon-o-user-group',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge(true)
                    ->color(fn($state) => match ($state) {
                        'PENDING' => 'warning',
                        'APPROVED' => 'success',
                        'REJECTED' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'PENDING' => 'heroicon-o-clock',
                        'APPROVED' => 'heroicon-o-check-circle',
                        'REJECTED' => 'heroicon-o-x-circle',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested At')
                    ->dateTime('d M Y H:i'),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime('d M Y H:i'),
                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->limit(18),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->limit(18),
            ])
            ->defaultSort('requested_at', 'desc');
    }
}
