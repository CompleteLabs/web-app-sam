<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\UserScopedWidget;
use App\Models\OutletHistory;
use App\Services\NotificationService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OutletApprovalsWidget extends BaseWidget
{
    use UserScopedWidget;

    protected static ?string $heading = 'Persetujuan Upgrade NOO ke MEMBER';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Logic dari ViewOutlet: button approve/reject muncul ketika:
        // 1. Outlet level = NOO (saat ini)
        // 2. Tidak ada history ATAU history terakhir belum approved/rejected
        // Button ini untuk approve upgrade NOO → MEMBER

        $query = OutletHistory::query()
            ->with(['outlet', 'requestedBy'])
            ->whereHas('outlet', function ($q) {
                $q->where('level', 'NOO'); // Outlet level saat ini harus NOO
            })
            ->where(function ($q) {
                // Get latest history per outlet where status is not APPROVED/REJECTED
                $q->whereRaw('id IN (
                    SELECT MAX(id)
                    FROM outlet_histories oh2
                    WHERE oh2.outlet_id = outlet_histories.outlet_id
                )')
                    ->where(function ($statusQ) {
                        $statusQ->where('approval_status', '!=', 'APPROVED')
                            ->where('approval_status', '!=', 'REJECTED')
                            ->orWhereNull('approval_status');
                    });
            })
            ->orderBy('requested_at', 'asc');

        // Apply user scope to outlet histories
        $query = $this->applyUserScopeToOutletHistories($query);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('outlet.name')
                    ->label('Nama Outlet')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->outlet?->name;
                    }),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Diminta Oleh')
                    ->searchable(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Tanggal Permintaan')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outlet.district')
                    ->label('Kabupaten')
                    ->searchable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->label('Setujui')
                    ->form(function ($record) {
                        // Semua outlet NOO yang muncul di widget ini butuh form untuk upgrade ke MEMBER
                        return [
                            Forms\Components\TextInput::make('code')
                                ->label('Kode')
                                ->required()
                                ->default($record->outlet->code)
                                ->helperText('Kode unik member untuk outlet ini')
                                ->regex('/^[\S]+$/', 'Kode outlet tidak boleh mengandung spasi')
                                ->rule(function () use ($record) {
                                    return function ($attribute, $value, $fail) use ($record) {
                                        $divisionId = $record->division_id;
                                        $outletId = $record->id;
                                        $exists = DB::table('outlets')
                                            ->where('code', $value)
                                            ->where('division_id', $divisionId)
                                            ->where('id', '!=', $outletId)
                                            ->where('deleted_at', null)
                                            ->exists();
                                        if ($exists) {
                                            $fail('Kode Outlet sudah digunakan untuk divisi ini.');
                                        }
                                    };
                                })
                                ->maxLength(255),
                            Forms\Components\TextInput::make('limit')
                                ->label('Limit Kredit')
                                ->required()
                                ->numeric()
                                ->default($record->outlet->limit ?: 5000000)
                                ->suffix('IDR')
                                ->helperText('Limit kredit dalam Rupiah'),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $outlet = $record->outlet;
                            $oldLevel = $outlet->level; // NOO

                            // Find the original requester from the existing history entry
                            $requestedById = $record->requested_by;

                            // Update outlet level ke MEMBER
                            $outlet->update([
                                'level' => 'MEMBER',
                                'code' => $data['code'],
                                'limit' => $data['limit'],
                            ]);

                            // Create new outlet history record (sama seperti ViewOutlet)
                            \App\Models\OutletHistory::create([
                                'outlet_id' => $outlet->id,
                                'from_level' => $oldLevel,
                                'to_level' => 'MEMBER',
                                'requested_by' => $requestedById,
                                'approved_by' => Auth::id(),
                                'approval_status' => 'APPROVED',
                                'requested_at' => $record->requested_at,
                                'approved_at' => now(),
                                'approval_notes' => null,
                            ]);

                            // Send notification to requester
                            try {
                                NotificationService::sendOutletApproval(
                                    $outlet,
                                    $data['code'],
                                    $data['limit']
                                );
                            } catch (\Exception $e) {
                                // Log error but don't fail the approval
                                Log::error('Failed to send approval notification: ' . $e->getMessage());
                            }

                            Notification::make()
                                ->title("Outlet {$outlet->name} Disetujui")
                                ->body("Level berubah dari {$oldLevel} ke MEMBER")
                                ->success()
                                ->send();
                        });
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Perubahan Level')
                    ->modalDescription(function ($record) {
                        return "Apakah Anda yakin ingin menyetujui upgrade outlet ini dari NOO ke MEMBER?";
                    })
                    ->modalSubmitActionLabel('Setujui'),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->label('Tolak')
                    ->form([
                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Berikan alasan detail untuk penolakan...'),
                    ])
                    ->action(function ($record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $outlet = $record->outlet;
                            $oldLevel = $outlet->level; // NOO

                            // Find the original requester from the existing history entry
                            $requestedById = $record->requested_by;

                            // Update outlet status to UNPRODUCTIVE for rejected NOO->MEMBER
                            $outlet->update([
                                'status' => 'UNPRODUCTIVE',
                            ]);

                            // Create new outlet history record (sama seperti ViewOutlet)
                            \App\Models\OutletHistory::create([
                                'outlet_id' => $outlet->id,
                                'from_level' => $oldLevel,
                                'to_level' => $oldLevel,
                                'requested_by' => $requestedById,
                                'approved_by' => Auth::id(),
                                'approval_status' => 'REJECTED',
                                'requested_at' => $record->requested_at,
                                'approved_at' => now(),
                                'approval_notes' => $data['approval_notes'],
                            ]);

                            // Send notification to requester
                            try {
                                NotificationService::sendOutletRejection(
                                    $outlet,
                                    $data['approval_notes']
                                );
                            } catch (\Exception $e) {
                                // Log error but don't fail the rejection
                                Log::error('Failed to send rejection notification: ' . $e->getMessage());
                            }

                            Notification::make()
                                ->title("Outlet {$outlet->name} Ditolak")
                                ->body("Upgrade NOO ke MEMBER telah ditolak")
                                ->danger()
                                ->send();
                        });
                    })
                    ->modalHeading('Tolak Perubahan Level')
                    ->modalDescription(function ($record) {
                        return "Berikan alasan untuk menolak upgrade outlet ini dari NOO ke MEMBER.";
                    })
                    ->modalSubmitActionLabel('Tolak'),

                Tables\Actions\Action::make('view_details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => route('filament.admin.resources.outlets.view', ['record' => $record->outlet_id]))
                    ->openUrlInNewTab()
                    ->label('Lihat Outlet'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_level')
                    ->label('Dari Level')
                    ->options([
                        'LEAD' => 'LEAD',
                        'NOO' => 'NOO',
                        'MEMBER' => 'MEMBER',
                    ]),

                Tables\Filters\SelectFilter::make('to_level')
                    ->label('Ke Level')
                    ->options([
                        'NOO' => 'NOO',
                        'MEMBER' => 'MEMBER',
                    ]),

                Tables\Filters\Filter::make('upgrade_type')
                    ->form([
                        Forms\Components\Select::make('upgrade_type')
                            ->label('Tipe Upgrade')
                            ->options([
                                'LEAD_TO_NOO' => 'LEAD → NOO',
                                'NOO_TO_MEMBER' => 'NOO → MEMBER',
                            ])
                            ->placeholder('Semua tipe'),
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['upgrade_type'] === 'LEAD_TO_NOO') {
                            $query->where('from_level', 'LEAD')->where('to_level', 'NOO');
                        } elseif ($data['upgrade_type'] === 'NOO_TO_MEMBER') {
                            $query->where('from_level', 'NOO')->where('to_level', 'MEMBER');
                        }
                    }),

                Tables\Filters\Filter::make('days_pending')
                    ->form([
                        Forms\Components\Select::make('days_range')
                            ->label('Hari Menunggu')
                            ->options([
                                '1' => '1+ hari',
                                '3' => '3+ hari',
                                '7' => '7+ hari',
                                '14' => '14+ hari',
                                '30' => '30+ hari',
                            ])
                            ->placeholder('Durasi apapun'),
                    ])
                    ->query(function ($query, array $data) {
                        if (isset($data['days_range'])) {
                            $query->where('requested_at', '<=', now()->subDays((int) $data['days_range']));
                        }
                    }),

                Tables\Filters\SelectFilter::make('outlet.status')
                    ->label('Status Outlet')
                    ->relationship('outlet', 'status')
                    ->options([
                        'PRODUCTIVE' => 'Produktif',
                        'UNPRODUCTIVE' => 'Tidak Produktif',
                        'POTENTIAL' => 'Potensial',
                    ]),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Status Persetujuan')
                    ->options([
                        'PENDING' => 'Menunggu',
                        'NEW' => 'Baru (Belum Diset)',
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['value'] === 'PENDING') {
                            $query->where('approval_status', 'PENDING');
                        } elseif ($data['value'] === 'NEW') {
                            $query->whereNull('approval_status');
                        }
                    }),
            ])
            ->defaultSort('requested_at', 'asc')
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_reject')
                    ->label('Tolak Massal')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('approval_notes')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Berikan alasan untuk penolakan massal...'),
                    ])
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                        DB::transaction(function () use ($records, $data) {
                            foreach ($records as $record) {
                                $outlet = $record->outlet;
                                $oldLevel = $outlet->level; // NOO

                                // Find the original requester from the existing history entry
                                $requestedById = $record->requested_by;

                                // Update outlet status to UNPRODUCTIVE for rejected NOO->MEMBER
                                $outlet->update(['status' => 'UNPRODUCTIVE']);

                                // Create new outlet history record (sama seperti ViewOutlet)
                                \App\Models\OutletHistory::create([
                                    'outlet_id' => $outlet->id,
                                    'from_level' => $oldLevel,
                                    'to_level' => $oldLevel,
                                    'requested_by' => $requestedById,
                                    'approved_by' => Auth::id(),
                                    'approval_status' => 'REJECTED',
                                    'requested_at' => $record->requested_at,
                                    'approved_at' => now(),
                                    'approval_notes' => $data['approval_notes'],
                                ]);

                                // Send notification
                                try {
                                    NotificationService::sendOutletRejection($outlet, $data['approval_notes']);
                                } catch (\Exception $e) {
                                    Log::error('Failed to send bulk rejection notification: ' . $e->getMessage());
                                }
                            }
                        });

                        Notification::make()
                            ->title('Penolakan Massal Berhasil')
                            ->body($records->count() . ' permintaan upgrade NOO ke MEMBER ditolak')
                            ->danger()
                            ->send();
                    })
                    ->modalHeading('Tolak Massal NOO → MEMBER')
                    ->modalDescription('Apakah Anda yakin ingin menolak semua permintaan upgrade NOO ke MEMBER yang dipilih?'),
            ])
            ->emptyStateHeading('Tidak Ada Persetujuan Diperlukan')
            ->emptyStateDescription('Semua permintaan perubahan level outlet telah diproses atau tidak ada permintaan yang ditemukan.');
    }
}
