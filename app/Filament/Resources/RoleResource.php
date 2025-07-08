<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Models\Permission;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('parent_id')
                                    ->label('Parent Role')
                                    ->relationship('parent', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->placeholder('No Parent Role')
                                    ->helperText('Pilih role induk jika ada, biarkan kosong jika tidak ada.'),
                                Forms\Components\Toggle::make('can_access_web')
                                    ->required(),
                                Forms\Components\Toggle::make('can_access_mobile')
                                    ->required(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Scope Configuration')
                            ->schema([
                                Forms\Components\CheckboxList::make('scope_required_fields')
                                    ->label('Required Fields')
                                    ->options(function (callable $get) {
                                        $selected = $get('scope_required_fields') ?? [];
                                        $options = [];
                                        // Always show Badan Usaha
                                        $options['badan_usaha_id'] = 'Badan Usaha';
                                        if (in_array('badan_usaha_id', $selected)) {
                                            $options['division_id'] = 'Divisi';
                                        }
                                        if (in_array('badan_usaha_id', $selected) && in_array('division_id', $selected)) {
                                            $options['region_id'] = 'Region';
                                        }
                                        if (in_array('badan_usaha_id', $selected) && in_array('division_id', $selected) && in_array('region_id', $selected)) {
                                            $options['cluster_id'] = 'Cluster';
                                        }

                                        return $options;
                                    })
                                    ->helperText('Centang field yang wajib diisi pada user_scopes untuk role ini.')
                                    ->columns(2)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        // Update available options for multiple fields
                                        // Only show required fields and only allow lowest level to be multiple
                                        $currentMultiple = $get('scope_multiple_fields') ?? [];
                                        $requiredFields = $state ?? [];

                                        // Find the lowest required level
                                        $hierarchy = ['cluster_id', 'region_id', 'division_id', 'badan_usaha_id'];
                                        $lowestRequired = null;
                                        foreach ($hierarchy as $field) {
                                            if (in_array($field, $requiredFields)) {
                                                $lowestRequired = $field;
                                                break;
                                            }
                                        }

                                        // Filter current multiple fields to only include valid ones
                                        $validMultiple = [];
                                        if ($lowestRequired && in_array($lowestRequired, $currentMultiple)) {
                                            $validMultiple = [$lowestRequired];
                                        }

                                        $set('scope_multiple_fields', $validMultiple);
                                    }),
                                Forms\Components\CheckboxList::make('scope_multiple_fields')
                                    ->label('Multiple Selection Fields')
                                    ->options(function ($get) {
                                        $requiredFields = $get('scope_required_fields') ?? [];

                                        // Find the lowest required level
                                        $hierarchy = ['cluster_id', 'region_id', 'division_id', 'badan_usaha_id'];
                                        $lowestRequired = null;
                                        foreach ($hierarchy as $field) {
                                            if (in_array($field, $requiredFields)) {
                                                $lowestRequired = $field;
                                                break;
                                            }
                                        }

                                        $allOptions = [
                                            'badan_usaha_id' => 'Badan Usaha',
                                            'division_id' => 'Divisi',
                                            'region_id' => 'Region',
                                            'cluster_id' => 'Cluster',
                                        ];

                                        // Only show the lowest required level
                                        if ($lowestRequired && isset($allOptions[$lowestRequired])) {
                                            return [$lowestRequired => $allOptions[$lowestRequired]];
                                        }

                                        return [];
                                    })
                                    ->helperText('Pilih field yang bisa dipilih multiple. Hanya level terendah dari required fields yang bisa multiple.')
                                    ->columns(2)
                                    ->reactive(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Permissions')
                            ->schema(static::getPermissionSchema())
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('can_access_web')
                    ->boolean()
                    ->action(function ($record) {
                        $record->update(['can_access_web' => ! $record->can_access_web]);
                    })
                    ->tooltip('Click to toggle'),
                Tables\Columns\IconColumn::make('can_access_mobile')
                    ->boolean()
                    ->action(function ($record) {
                        $record->update(['can_access_mobile' => ! $record->can_access_mobile]);
                    })
                    ->tooltip('Click to toggle'),
                Tables\Columns\TextColumn::make('scope_required_fields')
                    ->label('Required Scopes')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $data = $record->scope_required_fields;

                        if (empty($data) || ! is_array($data)) {
                            return [];
                        }

                        $labels = [
                            'badan_usaha_id' => 'Badan Usaha',
                            'division_id' => 'Divisi',
                            'region_id' => 'Region',
                            'cluster_id' => 'Cluster',
                        ];

                        return array_map(fn ($field) => $labels[$field] ?? $field, $data);
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scope_multiple_fields')
                    ->label('Multiple Scopes')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(function ($record) {
                        $data = $record->scope_multiple_fields;

                        if (empty($data) || ! is_array($data)) {
                            return [];
                        }

                        $labels = [
                            'badan_usaha_id' => 'Badan Usaha',
                            'division_id' => 'Divisi',
                            'region_id' => 'Region',
                            'cluster_id' => 'Cluster',
                        ];

                        return array_map(fn ($field) => $labels[$field] ?? $field, $data);
                    })
                    ->toggleable(),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    protected static function getPermissionSchema(): array
    {
        $permissions = Permission::all()
            ->groupBy(function ($permission) {
                $lastUnderscorePosition = strrpos($permission->name, '_');

                return $lastUnderscorePosition !== false
                    ? substr($permission->name, $lastUnderscorePosition + 1)
                    : $permission->name;
            });

        return [
            Forms\Components\Grid::make(3)
                ->schema(
                    $permissions->map(function ($permissions, $resource) {
                        $operations = $permissions->pluck('name')->toArray();

                        return Card::make(self::formatHeadline($resource))
                            ->schema([
                                Toggle::make("select_all_{$resource}")
                                    ->label('Select All')
                                    ->reactive()
                                    ->afterStateHydrated(function ($component, $state) use ($operations) {
                                        $record = $component->getRecord();
                                        if ($record) {
                                            // FLATTEN permissions array to avoid nested arrays in whereIn
                                            $existingPermissions = $record->permissions()
                                                ->whereIn('name', array_values($operations))
                                                ->pluck('name')
                                                ->toArray();
                                            $component->state(count($existingPermissions) === count($operations));
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $get, $set) use ($operations, $resource) {
                                        if ($state) {
                                            $set("permissions.{$resource}", $operations);
                                        } else {
                                            $set("permissions.{$resource}", []);
                                        }
                                    }),
                                CheckboxList::make("permissions.{$resource}")
                                    ->label('')
                                    ->options(self::formatOptions($operations))
                                    ->dehydrated(true)
                                    ->reactive()
                                    ->afterStateHydrated(function ($component, $state) use ($operations) {
                                        $record = $component->getRecord();
                                        if ($record) {
                                            // FLATTEN permissions array to avoid nested arrays in whereIn
                                            $existingPermissions = $record->permissions()
                                                ->whereIn('name', array_values($operations))
                                                ->pluck('name')
                                                ->toArray();

                                            $component->state($existingPermissions);
                                        }
                                    })
                                    ->afterStateUpdated(function ($state, $get, $set) use ($operations, $resource) {
                                        if (is_array($state) && count($state) === count($operations)) {
                                            $set("select_all_{$resource}", true);
                                        } else {
                                            $set("select_all_{$resource}", false);
                                        }
                                    })
                                    ->columns(2),
                            ])
                            ->collapsible()
                            ->columnSpan(1);
                    })->values()->toArray()
                )
                ->columnSpanFull(),
        ];
    }

    protected static function formatHeadline(string $resource): string
    {
        return Str::headline(str_replace('::', ' ', $resource));
    }

    protected static function formatOptions(array $operations): array
    {
        return collect($operations)
            ->mapWithKeys(function ($operation) {
                $lastUnderscorePosition = strrpos($operation, '_');
                $baseOperation = $lastUnderscorePosition !== false
                    ? substr($operation, 0, $lastUnderscorePosition)
                    : $operation;
                $label = Str::headline(str_replace('_', ' ', $baseOperation));

                return [$operation => $label];
            })
            ->toArray();
    }
}
