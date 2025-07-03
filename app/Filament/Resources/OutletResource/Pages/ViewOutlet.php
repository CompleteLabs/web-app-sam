<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Resources\OutletResource;
use App\Services\NotificationService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewOutlet extends ViewRecord
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->url(fn($record) => OutletResource::getUrl('edit', ['record' => $record])),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Persetujuan')
                ->modalSubheading('Apakah Anda yakin ingin mengapprove record ini?')
                ->color('success')
                ->form(fn($record) => [
                    \Filament\Forms\Components\TextInput::make('code')
                        ->label('Kode Outlet')
                        ->required()
                        ->regex('/^[\S]+$/', 'Kode outlet tidak boleh mengandung spasi')
                        ->helperText('Kode outlet tidak boleh mengandung spasi')
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
                    \Filament\Forms\Components\TextInput::make('limit')
                        ->label('Limit')
                        ->numeric()
                        ->required(),
                ])
                ->visible(
                    fn($record) => ($record->level === 'NOO') &&
                        (
                            !$record->outletHistories->count() ||
                            (
                                $record->outletHistories->last()?->approval_status !== 'APPROVED' &&
                                $record->outletHistories->last()?->approval_status !== 'REJECTED'
                            )
                        )
                )
                ->action(function ($record, $data) {
                    $oldLevel = $record->level;

                    // Find the original requester from the last history entry
                    $lastHistory = $record->outletHistories()
                        ->where('approval_status', 'PENDING')
                        ->orWhere('approval_status', null)
                        ->latest()
                        ->first();

                    $requestedById = $lastHistory ? $lastHistory->requested_by : Auth::id();

                    $record->update([
                        'code' => $data['code'],
                        'limit' => $data['limit'],
                        'level' => 'MEMBER',
                    ]);

                    // Tambah ke outlet_histories
                    $history = \App\Models\OutletHistory::create([
                        'outlet_id' => $record->id,
                        'from_level' => $oldLevel,
                        'to_level' => 'MEMBER',
                        'requested_by' => $requestedById,
                        'approved_by' => Auth::id(),
                        'approval_status' => 'APPROVED',
                        'requested_at' => $lastHistory ? $lastHistory->requested_at : now(),
                        'approved_at' => now(),
                        'approval_notes' => null,
                    ]);

                    // Send notification to the user who requested the approval
                    NotificationService::sendOutletApproval($record, $data['code'], $data['limit']);

                    Notification::make()
                        ->title($record->name . ' Approved')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('alasan')->label('Alasan')->required(),
                ])
                ->visible(
                    fn($record) => ($record->level === 'NOO') &&
                        (
                            !$record->outletHistories->count() ||
                            (
                                $record->outletHistories->last()?->approval_status !== 'APPROVED' &&
                                $record->outletHistories->last()?->approval_status !== 'REJECTED'
                            )
                        )
                )
                ->action(function ($record, $data) {
                    $oldLevel = $record->level;

                    // Find the original requester from the last history entry
                    $lastHistory = $record->outletHistories()
                        ->where('approval_status', 'PENDING')
                        ->orWhere('approval_status', null)
                        ->latest()
                        ->first();

                    $requestedById = $lastHistory ? $lastHistory->requested_by : Auth::id();

                    $record->update([
                        // 'confirmed_at' => Carbon::now(),
                        // 'confirmed_by' => auth()->user()->name,
                        // Tidak update status ke REJECTED karena enum status outlet tidak mengizinkan
                        'status' => 'UNPRODUCTIVE',
                    ]);

                    // Tambah ke outlet_histories
                    $history = \App\Models\OutletHistory::create([
                        'outlet_id' => $record->id,
                        'from_level' => $oldLevel,
                        'to_level' => $oldLevel,
                        'requested_by' => $requestedById,
                        'approved_by' => Auth::id(),
                        'approval_status' => 'REJECTED',
                        'requested_at' => $lastHistory ? $lastHistory->requested_at : now(),
                        'approved_at' => now(),
                        'approval_notes' => $data['alasan'] ?? null,
                    ]);

                    // Send notification to the user who requested the approval
                    NotificationService::sendOutletRejection($record, $data['alasan'] ?? null);

                    Notification::make()
                        ->title($record->name . ' Rejected')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
