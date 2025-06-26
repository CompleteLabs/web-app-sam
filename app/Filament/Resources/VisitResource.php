<?php

namespace App\Filament\Resources;

use App\Filament\Filters\TrashedFilter;
use App\Filament\Resources\VisitResource\Pages;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Visit Information')
                            ->schema([
                                Forms\Components\DatePicker::make('visit_date')
                                    ->label('Visit Date')
                                    ->required()
                                    ->default(now()),
                                Forms\Components\ToggleButtons::make('type')
                                    ->label('Visit Type')
                                    ->required()
                                    ->inline()
                                    ->options([
                                        'PLANNED' => 'PLANNED',
                                        'EXTRACALL' => 'EXTRACALL',
                                    ])
                                    ->icons([
                                        'PLANNED' => 'heroicon-o-calendar',
                                        'EXTRACALL' => 'heroicon-o-bolt',
                                    ])
                                    ->colors([
                                        'PLANNED' => 'primary',
                                        'EXTRACALL' => 'info',
                                    ])
                                    ->default('EXTRACALL'),
                                Forms\Components\Select::make('user_id')
                                    ->label('User')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('outlet_id')
                                    ->label('Outlet')
                                    ->relationship('outlet', 'name')
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            $outlet = \App\Models\Outlet::find($state);
                                            $set('checkin_location', $outlet?->latlong ?? null);
                                        } else {
                                            $set('checkin_location', null);
                                        }
                                    }),
                            ])
                            ->columns(2),
                        Forms\Components\Section::make('Location & Time')
                            ->schema([
                                Forms\Components\TextInput::make('checkin_location')
                                    ->label('Check-in Location')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('checkout_location')
                                    ->label('Check-out Location')
                                    ->maxLength(255)
                                    ->visible(fn ($context) => in_array($context, ['edit'])),
                                Forms\Components\TimePicker::make('checkin_time')
                                    ->label('Check-in Time')
                                    ->required(),
                                Forms\Components\TimePicker::make('checkout_time')
                                    ->label('Check-out Time')
                                    ->visible(fn ($context) => in_array($context, ['edit']))
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $checkIn = $get('checkin_time');
                                        if ($state && $checkIn) {
                                            $checkInTime = \Carbon\Carbon::createFromFormat('H:i:s', $checkIn);
                                            $checkOutTime = \Carbon\Carbon::createFromFormat('H:i:s', $state);
                                            $duration = $checkOutTime->diffInMinutes($checkInTime, false);
                                            $set('duration', abs($duration));
                                        }
                                    }),
                                Forms\Components\TextInput::make('duration')
                                    ->label('Duration (minutes)')
                                    ->numeric()
                                    ->columnSpanFull()
                                    ->visible(fn ($context) => in_array($context, ['edit']))
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $checkIn = $get('checkin_time');
                                        if ($state && $checkIn) {
                                            $checkInTime = \Carbon\Carbon::createFromFormat('H:i:s', $checkIn);
                                            $checkOutTime = $checkInTime->copy()->addMinutes((int) $state)->format('H:i:s');
                                            $set('checkout_time', $checkOutTime);
                                        }
                                    }),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Visit Proof')
                            ->schema([
                                Forms\Components\FileUpload::make('checkin_photo')
                                    ->label('Check-in Photo')
                                    ->required()
                                    ->visible(fn ($context) => in_array($context, ['edit', 'create'])),
                                Forms\Components\FileUpload::make('checkout_photo')
                                    ->label('Check-out Photo')
                                    ->visible(fn ($context) => in_array($context, ['edit'])),
                            ])
                            ->collapsible(),
                        Forms\Components\Section::make('Visit Result')
                            ->schema([
                                Forms\Components\ToggleButtons::make('transaction')
                                    ->label('Transaction')
                                    ->required()
                                    ->inline()
                                    ->options([
                                        'YES' => 'YES',
                                        'NO' => 'NO',
                                    ])
                                    ->icons([
                                        'YES' => 'heroicon-o-check-circle',
                                        'NO' => 'heroicon-o-x-circle',
                                    ])
                                    ->colors([
                                        'YES' => 'success',
                                        'NO' => 'danger',
                                    ]),
                                Forms\Components\Textarea::make('report')
                                    ->label('Visit Report'),
                            ])
                            ->collapsible()
                            ->visible(fn ($context) => in_array($context, ['edit'])),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit_date')
                    ->date(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('checkin_location'),
                Tables\Columns\TextColumn::make('checkout_location'),
                Tables\Columns\TextColumn::make('checkin_time')
                    ->time(),
                Tables\Columns\TextColumn::make('checkout_time')
                    ->time(),
                Tables\Columns\TextColumn::make('transaction'),
                Tables\Columns\TextColumn::make('duration')
                    ->numeric(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('checkin_time', 'desc')
            ->deferLoading()
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisits::route('/'),
            'create' => Pages\CreateVisit::route('/create'),
            'edit' => Pages\EditVisit::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $scopes = $user->userScopes;

        // Terapkan filter kombinasi scope per baris user_scopes (hirarki: cluster > region > division > badan usaha)
        $query->where(function ($q) use ($scopes) {
            foreach ($scopes as $scope) {
                $q->orWhereHas('outlet', function ($sub) use ($scope) {
                    if ($scope->cluster_id) {
                        // Handle JSON array for cluster_id
                        $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                        $sub->whereIn('cluster_id', $clusterIds);
                    } elseif ($scope->region_id) {
                        // Handle JSON array for region_id
                        $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                        $sub->whereIn('region_id', $regionIds);
                    } elseif ($scope->division_id) {
                        // Handle JSON array for division_id
                        $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                        $sub->whereIn('division_id', $divisionIds);
                    } elseif ($scope->badan_usaha_id) {
                        // Handle JSON array for badan_usaha_id
                        $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                        $sub->whereIn('badan_usaha_id', $badanUsahaIds);
                    }
                });
            }
        });

        return $query;
    }
}
