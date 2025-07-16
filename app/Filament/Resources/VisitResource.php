<?php

namespace App\Filament\Resources;

use App\Filament\Filters\TrashedFilter;
use App\Filament\Resources\VisitResource\Pages;
use App\Jobs\PostVisitToExternalJob;
use App\Models\Visit;
use App\Services\ExternalVisitSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
                                            $set('checkin_location', $outlet?->location ?? null);
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
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('checkout_location')
                                    ->label('Check-out Location')
                                    ->maxLength(255)
                                    ->visible(fn($context) => in_array($context, ['edit'])),
                                Forms\Components\TimePicker::make('checkin_time')
                                    ->label('Check-in Time')
                                    ->required(),
                                Forms\Components\TimePicker::make('checkout_time')
                                    ->label('Check-out Time')
                                    ->visible(fn($context) => in_array($context, ['edit']))
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
                                    ->visible(fn($context) => in_array($context, ['edit']))
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
                                    ->image()
                                    ->required()
                                    ->visible(fn($context) => in_array($context, ['edit', 'create']))
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $userId = $livewire->data['user_id'] ?? 'user';
                                        $username = null;
                                        if ($userId) {
                                            $userModel = \App\Models\User::find($userId);
                                            $username = $userModel?->username ?? 'user';
                                            $username = Str::slug($username);
                                        } else {
                                            $username = 'user';
                                        }
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "visit-checkin_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
                                Forms\Components\FileUpload::make('checkout_photo')
                                    ->label('Check-out Photo')
                                    ->image()
                                    ->visible(fn($context) => in_array($context, ['edit']))
                                    ->getUploadedFileNameForStorageUsing(function ($file, $livewire) {
                                        $userId = $livewire->data['user_id'] ?? 'user';
                                        $username = null;
                                        if ($userId) {
                                            $userModel = \App\Models\User::find($userId);
                                            $username = $userModel?->username ?? 'user';
                                            $username = Str::slug($username);
                                        } else {
                                            $username = 'user';
                                        }
                                        $date = date('Y-m-d');
                                        $time = date('His');
                                        $random = substr(bin2hex(random_bytes(4)), 0, 6);
                                        $extension = $file->getClientOriginalExtension();
                                        return "visit-checkout_{$username}_{$date}_{$time}_{$random}.{$extension}";
                                    }),
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
                            ->visible(fn($context) => in_array($context, ['edit'])),
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
                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Visit Date')
                    ->date('M j, Y'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Sales Person')
                    ->searchable(),
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('type')
                    ->label('Visit Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PLANNED' => 'primary',
                        'EXTRACALL' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'PLANNED' => 'heroicon-o-calendar',
                        'EXTRACALL' => 'heroicon-o-bolt',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                Tables\Columns\TextColumn::make('checkin_time')
                    ->label('Check-in')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('checkout_time')
                    ->label('Check-out')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->formatStateUsing(fn($state) => $state ? $state . ' min' : 'N/A')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction')
                    ->label('Transaction')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'YES' => 'success',
                        'NO' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'YES' => 'heroicon-o-check-circle',
                        'NO' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    }),
                Tables\Columns\TextColumn::make('external_sync_status')
                    ->label('Sync Status')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn(?string $state): string => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-minus-circle',
                    })
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'success' => 'Synced',
                        'failed' => 'Failed',
                        default => 'Not Synced',
                    })
                    ->visible(fn() => config('sync.post_api_enabled', false))
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('checkin_photo')
                    ->label('Check-in Photo')
                    ->square()
                    ->size(50)
                    ->toggleable(),
                Tables\Columns\ImageColumn::make('checkout_photo')
                    ->label('Check-out Photo')
                    ->square()
                    ->size(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('checkin_location')
                    ->label('Check-in Location')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        return 'View Map';
                    })
                    ->url(function ($record) {
                        if (!$record->checkin_location) return null;
                        return "https://www.google.com/maps?q={$record->checkin_location}";
                    })
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->icon('heroicon-o-map-pin')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('checkout_location')
                    ->label('Check-out Location')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        return 'View Map';
                    })
                    ->url(function ($record) {
                        if (!$record->checkout_location) return null;
                        return "https://www.google.com/maps?q={$record->checkout_location}";
                    })
                    ->openUrlInNewTab()
                    ->color('primary')
                    ->icon('heroicon-o-map-pin')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->filters([
                TrashedFilter::make(),
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
                                fn(Builder $query, $date): Builder => $query->whereDate('visit_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('visit_date', '<=', $date),
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
                Tables\Filters\SelectFilter::make('external_sync_status')
                    ->label('Sync Status')
                    ->options([
                        'success' => 'Synced',
                        'failed' => 'Failed',
                        'not_synced' => 'Not Synced',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'not_synced',
                            fn(Builder $query): Builder => $query->whereNull('external_sync_status')
                        )->when(
                            $data['value'] === 'success',
                            fn(Builder $query): Builder => $query->where('external_sync_status', 'success')
                        )->when(
                            $data['value'] === 'failed',
                            fn(Builder $query): Builder => $query->where('external_sync_status', 'failed')
                        );
                    })
                    ->visible(fn() => config('sync.post_api_enabled', false)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('post_to_external')
                    ->label('Post ke Sistem Eksternal')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->color('info')
                    ->visible(fn(Visit $record) => config('sync.post_api_enabled', false) && $record->external_sync_status !== 'success')
                    ->requiresConfirmation()
                    ->modalHeading('Post Visit ke Sistem Eksternal')
                    ->modalDescription('Apakah Anda yakin ingin mengirim data visit ini ke sistem eksternal?')
                    ->modalSubmitActionLabel('Ya, Kirim')
                    ->action(function (Visit $record) {
                        try {
                            $syncService = new ExternalVisitSyncService();
                            $result = $syncService->postVisit($record);

                            if ($result['success']) {
                                Notification::make()
                                    ->title('Berhasil!')
                                    ->body($result['message'])
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Gagal!')
                                    ->body($result['message'])
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error!')
                                ->body('Terjadi kesalahan: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('post_to_external')
                        ->label('Post ke Sistem Eksternal')
                        ->icon('heroicon-o-arrow-up-on-square')
                        ->color('info')
                        ->visible(fn() => config('sync.post_api_enabled', false))
                        ->requiresConfirmation()
                        ->modalHeading('Post Visit ke Sistem Eksternal')
                        ->modalDescription('Apakah Anda yakin ingin mengirim data visit yang dipilih ke sistem eksternal?')
                        ->modalSubmitActionLabel('Ya, Kirim Semua')
                        ->action(function (Collection $records) {
                            $visitIds = $records->pluck('id')->toArray();
                            $userId = Auth::id();

                            // Dispatch job for batch processing
                            PostVisitToExternalJob::dispatch($visitIds, $userId);

                            Notification::make()
                                ->title('Batch Post Dimulai!')
                                ->body('Proses post ' . count($visitIds) . ' visit ke sistem eksternal telah dimulai. Anda akan menerima notifikasi setelah selesai.')
                                ->info()
                                ->send();
                        }),
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
