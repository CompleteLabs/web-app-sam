<?php

namespace App\Filament\Resources;

use App\Filament\Filters\TrashedFilter;
use App\Filament\Resources\UserResource\Pages;
use App\Helpers\ScopeHelper;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

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
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn ($state) => strtoupper($state))
                                    ->columnSpan('full'),
                                Forms\Components\TextInput::make('username')
                                    ->required()
                                    ->maxLength(255)
                                    ->regex('/^[\S]+$/', 'Username tidak boleh mengandung spasi')
                                    ->helperText('Username tidak boleh mengandung spasi'),
                                Forms\Components\TextInput::make('phone')
                                    ->maxLength(20)
                                    ->unique(ignoreRecord: true)
                                    ->tel()
                                    ->required()
                                    ->helperText('Nomor handphone harus aktif (untuk login menggunakan WhatsApp)'),
                                Forms\Components\Select::make('role_id')
                                    ->relationship('role', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->label('Role')
                                    ->placeholder('Pilih role')
                                    ->options(function (callable $get) {
                                        return \App\Models\Role::pluck('name', 'id')->toArray();
                                    }),
                                Forms\Components\Select::make('tm_id')
                                    ->label('TM')
                                    ->relationship('tm', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->placeholder('Pilih TM'),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->maxLength(255)
                                    ->label('Password')
                                    ->placeholder('Masukkan password')
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->revealable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Repeater::make('user_scopes')
                            ->label('Scope Akses')
                            ->hiddenLabel(true)
                            ->relationship('userScopes')
                            ->addable(false)
                            ->deletable(false)
                            ->schema([
                                Forms\Components\Select::make('badan_usaha_id')
                                    ->label('Badan Usaha')
                                    ->searchable()
                                    ->reactive()
                                    ->placeholder('Pilih badan usaha')
                                    ->options(fn () => \App\Models\BadanUsaha::pluck('name', 'id'))
                                    ->multiple(fn (callable $get) => ScopeHelper::canBeMultiple('badan_usaha_id', $get('../../role_id')))
                                    ->required(fn (callable $get) => ScopeHelper::isRequired('badan_usaha_id', $get('../../role_id')))
                                    ->visible(fn (callable $get) => ScopeHelper::isVisible('badan_usaha_id', $get('../../role_id')))
                                    ->helperText(function (callable $get) {
                                        $roleId = $get('../../role_id');
                                        if (! $roleId) {
                                            return null;
                                        }
                                        $canMultiple = ScopeHelper::canBeMultiple('badan_usaha_id', $roleId);
                                        $isRequired = ScopeHelper::isRequired('badan_usaha_id', $roleId);
                                        $text = $isRequired ? 'Wajib diisi. ' : 'Opsional. ';
                                        $text .= $canMultiple ? 'Dapat memilih multiple badan usaha.' : 'Hanya bisa pilih satu badan usaha.';

                                        return $text;
                                    }),
                                Forms\Components\Select::make('division_id')
                                    ->label('Divisi')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $badanusahaId = $get('badan_usaha_id');
                                        if (! $badanusahaId) {
                                            return [];
                                        }
                                        // Handle both single and multiple selections
                                        $badanUsahaIds = is_array($badanusahaId) ? $badanusahaId : [$badanusahaId];

                                        return Division::whereIn('badan_usaha_id', $badanUsahaIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->multiple(fn (callable $get) => ScopeHelper::canBeMultiple('division_id', $get('../../role_id')))
                                    ->required(fn (callable $get) => ScopeHelper::isRequired('division_id', $get('../../role_id')))
                                    ->visible(fn (callable $get) => ScopeHelper::isVisible('division_id', $get('../../role_id')))
                                    ->helperText(function (callable $get) {
                                        $roleId = $get('../../role_id');
                                        if (! $roleId) {
                                            return null;
                                        }
                                        $canMultiple = ScopeHelper::canBeMultiple('division_id', $roleId);
                                        $isRequired = ScopeHelper::isRequired('division_id', $roleId);
                                        $text = $isRequired ? 'Wajib diisi. ' : 'Opsional. ';
                                        $text .= $canMultiple ? 'Dapat memilih multiple divisi.' : 'Hanya bisa pilih satu divisi.';

                                        return $text;
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('region_id', null);
                                        $set('cluster_id', null);
                                    }),
                                Forms\Components\Select::make('region_id')
                                    ->label('Region')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $divisionId = $get('division_id');
                                        if (! $divisionId) {
                                            return [];
                                        }
                                        // Handle both single and multiple selections
                                        $divisionIds = is_array($divisionId) ? $divisionId : [$divisionId];

                                        return Region::whereIn('division_id', $divisionIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->multiple(fn (callable $get) => ScopeHelper::canBeMultiple('region_id', $get('../../role_id')))
                                    ->required(fn (callable $get) => ScopeHelper::isRequired('region_id', $get('../../role_id')))
                                    ->visible(fn (callable $get) => ScopeHelper::isVisible('region_id', $get('../../role_id')))
                                    ->helperText(function (callable $get) {
                                        $roleId = $get('../../role_id');
                                        if (! $roleId) {
                                            return null;
                                        }
                                        $canMultiple = ScopeHelper::canBeMultiple('region_id', $roleId);
                                        $isRequired = ScopeHelper::isRequired('region_id', $roleId);
                                        $text = $isRequired ? 'Wajib diisi. ' : 'Opsional. ';
                                        $text .= $canMultiple ? 'Dapat memilih multiple region.' : 'Hanya bisa pilih satu region.';

                                        return $text;
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('cluster_id', null);
                                    }),
                                Forms\Components\Select::make('cluster_id')
                                    ->label('Cluster')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $regionId = $get('region_id');
                                        if (! $regionId) {
                                            return [];
                                        }
                                        // Handle both single and multiple selections
                                        $regionIds = is_array($regionId) ? $regionId : [$regionId];

                                        return \App\Models\Cluster::whereIn('region_id', $regionIds)
                                            ->pluck('name', 'id');
                                    })
                                    ->multiple(fn (callable $get) => ScopeHelper::canBeMultiple('cluster_id', $get('../../role_id')))
                                    ->required(fn (callable $get) => ScopeHelper::isRequired('cluster_id', $get('../../role_id')))
                                    ->visible(fn (callable $get) => ScopeHelper::isVisible('cluster_id', $get('../../role_id')))
                                    ->helperText(function (callable $get) {
                                        $roleId = $get('../../role_id');
                                        if (! $roleId) {
                                            return null;
                                        }
                                        $canMultiple = ScopeHelper::canBeMultiple('cluster_id', $roleId);
                                        $isRequired = ScopeHelper::isRequired('cluster_id', $roleId);
                                        $text = $isRequired ? 'Wajib diisi. ' : 'Opsional. ';
                                        $text .= $canMultiple ? 'Dapat memilih multiple cluster.' : 'Hanya bisa pilih satu cluster.';

                                        return $text;
                                    }),
                            ])
                            ->maxItems(1) // Only one scope entry needed since each field can be multiple
                            ->minItems(function (callable $get) {
                                $roleId = $get('role_id');
                                if (! $roleId) {
                                    return 0;
                                }

                                // Check if any field is required
                                $fields = ['badan_usaha_id', 'division_id', 'region_id', 'cluster_id'];
                                foreach ($fields as $field) {
                                    if (ScopeHelper::isRequired($field, $roleId)) {
                                        return 1; // At least one item required
                                    }
                                }

                                return 0;
                            })
                            ->collapsible()
                            ->collapsed(false)
                            ->visible(fn (callable $get) => $get('role_id')),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role'),
                Tables\Columns\TextColumn::make('userScopes.badan_usaha_id')
                    ->label('Badan Usaha')
                    ->getStateUsing(function ($record) {
                        // Ambil nama badan usaha dari relasi badan_usaha_list pada setiap scope
                        return $record->userScopes->flatMap(function ($scope) {
                            return $scope->badan_usaha_list->pluck('name');
                        })->unique()->implode(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('userScopes.division_id')
                    ->label('Divisi')
                    ->getStateUsing(function ($record) {
                        return $record->userScopes->flatMap(function ($scope) {
                            return $scope->division_list->pluck('name');
                        })->unique()->implode(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('userScopes.region_id')
                    ->label('Region')
                    ->getStateUsing(function ($record) {
                        return $record->userScopes->flatMap(function ($scope) {
                            return $scope->region_list->pluck('name');
                        })->unique()->implode(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('userScopes.cluster_id')
                    ->label('Cluster')
                    ->getStateUsing(function ($record) {
                        return $record->userScopes->flatMap(function ($scope) {
                            return $scope->cluster_list->pluck('name');
                        })->unique()->implode(', ');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tm.name')
                    ->label('TM')
                    ->searchable(),
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
                TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role')
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('tm_id')
                    ->label('TM')
                    ->relationship('tm', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('userScopes')
                    ->form([
                        Forms\Components\Select::make('badan_usaha_id')
                            ->label('Badan Usaha')
                            ->options(fn () => \App\Models\BadanUsaha::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->reactive(),
                        Forms\Components\Select::make('division_id')
                            ->label('Divisi')
                            ->options(function (callable $get) {
                                $badanUsahaId = $get('badan_usaha_id');
                                if (! $badanUsahaId) {
                                    return [];
                                }
                                $badanUsahaIds = is_array($badanUsahaId) ? $badanUsahaId : [$badanUsahaId];

                                return Division::whereIn('badan_usaha_id', $badanUsahaIds)->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->reactive(),
                        Forms\Components\Select::make('region_id')
                            ->label('Region')
                            ->options(function (callable $get) {
                                $divisionId = $get('division_id');
                                if (! $divisionId) {
                                    return [];
                                }
                                $divisionIds = is_array($divisionId) ? $divisionId : [$divisionId];

                                return Region::whereIn('division_id', $divisionIds)->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->reactive(),
                        Forms\Components\Select::make('cluster_id')
                            ->label('Cluster')
                            ->options(function (callable $get) {
                                $regionId = $get('region_id');
                                if (! $regionId) {
                                    return [];
                                }
                                $regionIds = is_array($regionId) ? $regionId : [$regionId];

                                return \App\Models\Cluster::whereIn('region_id', $regionIds)->pluck('name', 'id')->toArray();
                            })
                            ->searchable(),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if (! blank($data['badan_usaha_id'] ?? null)) {
                            $query->whereHas('userScopes', function ($q) use ($data) {
                                $q->whereJsonContains('badan_usaha_id', (int) $data['badan_usaha_id']);
                            });
                        }
                        if (! blank($data['division_id'] ?? null)) {
                            $query->whereHas('userScopes', function ($q) use ($data) {
                                $q->whereJsonContains('division_id', (int) $data['division_id']);
                            });
                        }
                        if (! blank($data['region_id'] ?? null)) {
                            $query->whereHas('userScopes', function ($q) use ($data) {
                                $q->whereJsonContains('region_id', (int) $data['region_id']);
                            });
                        }
                        if (! blank($data['cluster_id'] ?? null)) {
                            $query->whereHas('userScopes', function ($q) use ($data) {
                                $q->whereJsonContains('cluster_id', (int) $data['cluster_id']);
                            });
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Impersonate::make()
                    ->redirectTo(route('filament.admin.pages.dashboard')),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        $scopes = $user->userScopes;

        $query->where(function ($q) use ($scopes) {
            foreach ($scopes as $scope) {
                $q->orWhereHas('userScopes', function ($sub) use ($scope) {
                    if (! empty($scope->cluster_id)) {
                        $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                        $sub->where(function ($w) use ($clusterIds) {
                            foreach ($clusterIds as $id) {
                                $w->orWhereJsonContains('cluster_id', $id);
                            }
                        });
                    } elseif (! empty($scope->region_id)) {
                        $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                        $sub->where(function ($w) use ($regionIds) {
                            foreach ($regionIds as $id) {
                                $w->orWhereJsonContains('region_id', $id);
                            }
                        });
                    } elseif (! empty($scope->division_id)) {
                        $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                        $sub->where(function ($w) use ($divisionIds) {
                            foreach ($divisionIds as $id) {
                                $w->orWhereJsonContains('division_id', $id);
                            }
                        });
                    } elseif (! empty($scope->badan_usaha_id)) {
                        $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                        $sub->where(function ($w) use ($badanUsahaIds) {
                            foreach ($badanUsahaIds as $id) {
                                $w->orWhereJsonContains('badan_usaha_id', $id);
                            }
                        });
                    }
                });
            }
        });

        return $query;
    }
}
