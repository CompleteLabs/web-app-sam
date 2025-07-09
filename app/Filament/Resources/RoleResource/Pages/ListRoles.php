<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Jobs\SyncDataJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Only show sync button for super admin
        if (Auth::user() && Auth::user()->role && Auth::user()->role->name === 'SUPER ADMIN') {
            $actions[] = Actions\Action::make('sync_roles')
                ->label('Sync Roles')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Roles')
                ->modalDescription('Sinkronisasi data roles dari API. Proses akan dijalankan di background menggunakan queue.')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataJob::dispatch('roles', Auth::id());

                    Notification::make()
                        ->title('Sync Roles Started')
                        ->body('Proses sync roles telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
                        ->info()
                        ->send();
                });
        }

        return $actions;
    }
}
