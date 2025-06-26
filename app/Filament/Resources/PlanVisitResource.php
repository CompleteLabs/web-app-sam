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
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('outlet.name'),
                Tables\Columns\TextColumn::make('visit_date')
                    ->date(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
