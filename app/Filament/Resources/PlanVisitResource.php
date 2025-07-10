<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanVisitResource\Pages;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PlanVisitResource extends Resource
{
    protected static ?string $model = PlanVisit::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->label('Pilih User')
                    ->placeholder('Cari User berdasarkan nama lengkap')
                    ->options(function () {
                        $users = User::with('role')->get();

                        return $users->mapWithKeys(function ($user) {
                            $roleName = $user->role ? $user->role->name : 'Tidak ada role';

                            return [$user->id => "{$user->name} - {$roleName}"];
                        });
                    }),
                Forms\Components\Select::make('outlet_id')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Pilih Outlet')
                    ->options(function () {
                        // Eager load badanUsaha dan divisi
                        $outlets = Outlet::with(['badanUsaha', 'division'])->get();

                        return $outlets->mapWithKeys(function ($outlet) {
                            // Menggabungkan nama outlet, badan usaha, dan divisi untuk label
                            $badanUsahaName = $outlet->badanUsaha ? $outlet->badanUsaha->name : 'Tidak ada badan usaha';
                            $divisionName = $outlet->division ? $outlet->division->name : 'Tidak ada divisi';

                            return [$outlet->id => "[{$outlet->code}] {$outlet->name} - {$badanUsahaName} / {$divisionName}"];
                        });
                    }),
                Forms\Components\DatePicker::make('visit_date')
                    ->native(false)
                    ->required()
                    ->label('Tanggal Visit')
                    ->placeholder('Pilih tanggal kunjungan')
                    ->helperText('Tanggal kunjungan akan dicatat di sini')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Tanggal Visit')
                    ->date('M j, Y'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sales Person')
                    ->searchable()
                    ->formatStateUsing(function ($record) {
                        $roleName = $record->user->role ? $record->user->role->name : '';
                        return $record->user->name . ($roleName ? " ({$roleName})" : '');
                    }),
                Tables\Columns\TextColumn::make('outlet.code')
                    ->label('Kode Outlet')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Nama Outlet')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 40) {
                            return null;
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('outlet.level')
                    ->label('Level Outlet')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'LEAD' => 'warning',
                        'NOO' => 'info',
                        'MEMBER' => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'LEAD' => 'heroicon-o-star',
                        'NOO' => 'heroicon-o-user-plus',
                        'MEMBER' => 'heroicon-o-user-group',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('outlet.district')
                    ->label('Distrik')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('outlet.badanUsaha.name')
                    ->label('Badan Usaha')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('outlet.division.name')
                    ->label('Divisi')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('outlet.region.name')
                    ->label('Region')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('outlet.cluster.name')
                    ->label('Cluster')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('visit_date', 'asc')
            ->striped()
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Sales Person')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('visit_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('visit_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('visit_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Dari: ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Sampai: ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePlanVisits::route('/'),
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
