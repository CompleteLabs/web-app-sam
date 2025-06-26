<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DivisionResource\Pages;
use App\Models\Division;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('badan_usaha_id')
                    ->label('Badan Usaha')
                    ->searchable()
                    ->required()
                    ->placeholder('Pilih badan usaha')
                    ->options(function (callable $get) {
                        return \App\Models\BadanUsaha::pluck('name', 'id');
                    }),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[\S]+$/')
                    ->helperText('Tidak boleh mengandung spasi'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('badanUsaha.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->deferLoading()
            ->filters([
                Tables\Filters\SelectFilter::make('badanUsaha')
                    ->relationship('badanUsaha', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Filter Badan Usaha'),
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $scopes = $user->userScopes;

        // Terapkan filter kombinasi scope per baris user_scopes (hirarki: division > badan usaha)
        $query->where(function ($q) use ($scopes) {
            foreach ($scopes as $scope) {
                $q->orWhere(function ($sub) use ($scope) {
                    if ($scope->division_id) {
                        // Handle JSON array for division_id
                        $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                        $sub->whereIn('divisions.id', $divisionIds);
                    } elseif ($scope->badan_usaha_id) {
                        // Handle JSON array for badan_usaha_id
                        $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                        $sub->whereIn('divisions.badan_usaha_id', $badanUsahaIds);
                    }
                });
            }
        });

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDivisions::route('/'),
        ];
    }
}
