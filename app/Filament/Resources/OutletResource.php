<?php

namespace App\Filament\Resources;

use App\Filament\Filters\TrashedFilter;
use App\Filament\Resources\OutletResource\Pages;
use App\Filament\Resources\OutletResource\RelationManagers\OutletHistoriesRelationManager;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Apriansyahrs\CustomFields\Filament\Forms\Components\CustomFieldsComponent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OutletResource extends Resource
{
    protected static ?string $model = Outlet::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Outlet')
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->regex('/^[\S]+$/', 'Kode outlet tidak boleh mengandung spasi')
                                    ->helperText('Kode outlet tidak boleh mengandung spasi')
                                    ->rule(function (callable $get) {
                                        return function ($attribute, $value, $fail) use ($get) {
                                            $divisionId = $get('division_id');
                                            $outletId = $get('id');
                                            $exists = DB::table('outlets')
                                                ->where('code', $value)
                                                ->where('division_id', $divisionId)
                                                ->where('id', '!=', $outletId)
                                                ->where('deleted_at', null)
                                                ->exists();
                                            if ($exists) {
                                                $fail(__('Kode Outlet sudah digunakan untuk divisi ini.'));
                                            }
                                        };
                                    })
                                    ->maxLength(255)
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null && ($get('level') === 'MEMBER')),
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\TextInput::make('district')
                                    ->required()
                                    ->maxLength(255)
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\TextInput::make('location')
                                    ->maxLength(255)
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\Textarea::make('address')
                                    ->required()
                                    ->columnSpanFull()
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null),
                            ])
                            ->columns(2),
                        Forms\Components\Section::make('Kontak & Pemilik Outlet')
                            ->schema([
                                Forms\Components\TextInput::make('owner_name')
                                    ->maxLength(255)
                                    ->reactive()
                                    ->required()
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\TextInput::make('owner_phone')
                                    ->maxLength(255)
                                    ->reactive()
                                    ->required()
                                    ->visible(fn($get) => $get('level') !== null)
                                    ->dehydrateStateUsing(fn ($state) => \App\Services\PhoneService::normalize($state))
                                    ->rule(function () {
                                        return function ($attribute, $value, $fail) {
                                            if (!\App\Services\PhoneService::isValid($value)) {
                                                $fail('Format nomor handphone tidak valid. Harus diawali 08 atau +62 dan minimal 10 digit, maksimal 15 digit.');
                                            }
                                        };
                                    }),
                            ])
                            ->columns(2),
                        Forms\Components\Section::make('Foto & Video')
                            ->schema([
                                Forms\Components\FileUpload::make('photo_shop_sign')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null)
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $username = strtolower($livewire->data['owner_name'] ?? 'outlet');
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "outlet-photo_shop_sign_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                                Forms\Components\FileUpload::make('photo_front')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null)
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $username = strtolower($livewire->data['owner_name'] ?? 'outlet');
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "outlet-photo_front_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                                Forms\Components\FileUpload::make('photo_left')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null)
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $username = strtolower($livewire->data['owner_name'] ?? 'outlet');
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "outlet-photo_left_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                                Forms\Components\FileUpload::make('photo_right')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null)
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $username = strtolower($livewire->data['owner_name'] ?? 'outlet');
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "outlet-photo_right_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                                Forms\Components\FileUpload::make('photo_id_card')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null && ($get('level') === 'MEMBER' || $get('level') === 'NOO'))
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $username = strtolower($livewire->data['owner_name'] ?? 'outlet');
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "outlet-photo_idcard_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                                Forms\Components\FileUpload::make('video')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null)
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $username = strtolower($livewire->data['owner_name'] ?? 'outlet');
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "outlet-video_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                            ])
                            ->columns(2),
                        CustomFieldsComponent::make()
                            ->columns(1)
                            ->visible(fn($get) => $get('level') !== null),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Pengaturan Outlet')
                            ->schema([
                                Forms\Components\ToggleButtons::make('level')
                                    ->label('Status Outlet')
                                    ->inline()
                                    ->options([
                                        'LEAD' => 'LEAD',
                                        'NOO' => 'NOO',
                                        'MEMBER' => 'MEMBER',
                                    ])
                                    ->icons([
                                        'LEAD' => 'heroicon-o-star',
                                        'NOO' => 'heroicon-o-user-plus',
                                        'MEMBER' => 'heroicon-o-user-group',
                                    ])
                                    ->colors([
                                        'LEAD' => 'warning',
                                        'NOO' => 'info',
                                        'MEMBER' => 'success',
                                    ])
                                    ->required()
                                    ->reactive(),
                                Forms\Components\ToggleButtons::make('status')
                                    ->label('Status Outlet')
                                    ->options([
                                        'MAINTAIN' => 'MAINTAIN',
                                        'UNMAINTAIN' => 'UNMAINTAIN',
                                        'UNPRODUCTIVE' => 'UNPRODUCTIVE',
                                    ])
                                    ->icons([
                                        'MAINTAIN' => 'heroicon-o-check-circle',
                                        'UNMAINTAIN' => 'heroicon-o-x-circle',
                                        'UNPRODUCTIVE' => 'heroicon-o-exclamation-circle',
                                    ])
                                    ->colors([
                                        'MAINTAIN' => 'success',
                                        'UNMAINTAIN' => 'warning',
                                        'UNPRODUCTIVE' => 'danger',
                                    ])
                                    ->required()
                                    ->visible(fn($get) => $get('level') !== null),

                                Forms\Components\TextInput::make('limit')
                                    ->required()
                                    ->numeric()
                                    ->label('Limit')
                                    ->default('0')
                                    ->placeholder('Masukkan limit outlet')
                                    ->reactive()
                                    ->visible(fn($get) => $get('level') !== null && $get('level') === 'MEMBER'),

                                Forms\Components\TextInput::make('radius')
                                    ->required()
                                    ->numeric()
                                    ->label('Radius')
                                    ->default('100')
                                    ->helperText('Default 100 meter untuk checkin sales visit')
                                    ->placeholder('Masukkan radius outlet')
                                    ->visible(fn($get) => $get('level') !== null),
                            ])
                            ->collapsible(),
                        Forms\Components\Section::make('Badan Usaha')
                            ->schema([
                                Forms\Components\Select::make('badan_usaha_id')
                                    ->label('Badan Usaha')
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->placeholder('Pilih badan usaha')
                                    ->options(function (callable $get) {
                                        return \App\Models\BadanUsaha::pluck('name', 'id');
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('division_id', null);
                                        $set('region_id', null);
                                        $set('cluster_id', null);
                                    })
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\Select::make('division_id')
                                    ->label('Divisi')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $badanusahaId = $get('badan_usaha_id');
                                        if (! $badanusahaId) {
                                            return [];
                                        }

                                        return \App\Models\Division::where('badan_usaha_id', $badanusahaId)
                                            ->pluck('name', 'id');
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('region_id', null);
                                        $set('cluster_id', null);
                                    })
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\Select::make('region_id')
                                    ->label('Region')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $divisionId = $get('division_id');
                                        if (! $divisionId) {
                                            return [];
                                        }

                                        return \App\Models\Region::where('division_id', $divisionId)
                                            ->pluck('name', 'id');
                                    })
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('cluster_id', null);
                                    })
                                    ->visible(fn($get) => $get('level') !== null),
                                Forms\Components\Select::make('cluster_id')
                                    ->label('Cluster')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->options(function (callable $get) {
                                        $regionId = $get('region_id');
                                        if (! $regionId) {
                                            return [];
                                        }

                                        return \App\Models\Cluster::where('region_id', $regionId)
                                            ->pluck('name', 'id');
                                    })
                                    ->visible(fn($get) => $get('level') !== null),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Outlet')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
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
                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
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
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'MAINTAIN' => 'success',
                        'UNMAINTAIN' => 'warning',
                        'UNPRODUCTIVE' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'MAINTAIN' => 'heroicon-o-check-circle',
                        'UNMAINTAIN' => 'heroicon-o-x-circle',
                        'UNPRODUCTIVE' => 'heroicon-o-exclamation-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Pemilik')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner_phone')
                    ->label('Telepon')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('district')
                    ->label('Distrik')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Lokasi')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        return 'View Map';
                    })
                    ->url(function ($record) {
                        if (!$record->location) return null;
                        return "https://www.google.com/maps?q={$record->location}";
                    })
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->icon('heroicon-o-map-pin')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('limit')
                    ->label('Limit')
                    ->formatStateUsing(fn ($state) => $state ? 'Rp ' . number_format($state, 0, ',', '.') : 'N/A')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('radius')
                    ->label('Radius')
                    ->formatStateUsing(fn ($state) => $state ? $state . ' m' : 'N/A')
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('photo_shop_sign')
                    ->label('Foto Papan')
                    ->square()
                    ->size(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('photo_front')
                    ->label('Foto Depan')
                    ->square()
                    ->size(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('photo_left')
                    ->label('Foto Kiri')
                    ->square()
                    ->size(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('photo_right')
                    ->label('Foto Kanan')
                    ->square()
                    ->size(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ImageColumn::make('photo_id_card')
                    ->label('Foto KTP')
                    ->square()
                    ->size(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('badanUsaha.name')
                    ->label('Badan Usaha')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('division.name')
                    ->label('Divisi')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cluster.name')
                    ->label('Cluster')
                    ->searchable()
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
            ->defaultSort('code', 'asc')
            ->striped()
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('level')
                    ->searchable()
                    ->label('Level')
                    ->options([
                        'LEAD' => 'LEAD',
                        'NOO' => 'NOO',
                        'MEMBER' => 'MEMBER',
                    ]),
                Tables\Filters\Filter::make('userScopes')
                    ->form([
                        Forms\Components\Select::make('badan_usaha_id')
                            ->label('Badan Usaha')
                            ->searchable()
                            ->options(\App\Models\BadanUsaha::pluck('name', 'id'))
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
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['badan_usaha_id'] ?? null) {
                            $query->where('badan_usaha_id', $data['badan_usaha_id']);
                        }
                        if ($data['division_id'] ?? null) {
                            $query->where('division_id', $data['division_id']);
                        }
                        if ($data['region_id'] ?? null) {
                            $query->where('region_id', $data['region_id']);
                        }
                        if ($data['cluster_id'] ?? null) {
                            $query->where('cluster_id', $data['cluster_id']);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            OutletHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutlets::route('/'),
            'create' => Pages\CreateOutlet::route('/create'),
            'view' => Pages\ViewOutlet::route('/{record}'),
            'edit' => Pages\EditOutlet::route('/{record}/edit'),
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
                $q->orWhere(function ($sub) use ($scope) {
                    if ($scope->cluster_id) {
                        // Handle JSON array for cluster_id
                        $clusterIds = is_array($scope->cluster_id) ? $scope->cluster_id : [$scope->cluster_id];
                        $sub->whereIn('outlets.cluster_id', $clusterIds);
                    } elseif ($scope->region_id) {
                        // Handle JSON array for region_id
                        $regionIds = is_array($scope->region_id) ? $scope->region_id : [$scope->region_id];
                        $sub->whereIn('outlets.region_id', $regionIds);
                    } elseif ($scope->division_id) {
                        // Handle JSON array for division_id
                        $divisionIds = is_array($scope->division_id) ? $scope->division_id : [$scope->division_id];
                        $sub->whereIn('outlets.division_id', $divisionIds);
                    } elseif ($scope->badan_usaha_id) {
                        // Handle JSON array for badan_usaha_id
                        $badanUsahaIds = is_array($scope->badan_usaha_id) ? $scope->badan_usaha_id : [$scope->badan_usaha_id];
                        $sub->whereIn('outlets.badan_usaha_id', $badanUsahaIds);
                    }
                });
            }
        });

        return $query;
    }
}
