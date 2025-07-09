<?php

namespace App\Filament\Resources\DivisionResource\Pages;

use App\Filament\Resources\DivisionResource;
use App\Jobs\SyncDataJob;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ManageDivisions extends ManageRecords
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Add sync action only for Super Admin
        if (Auth::user() && Auth::user()->role && Auth::user()->role->name === 'SUPER ADMIN') {
            $actions[] = Actions\Action::make('sync')
                ->label('Sync dari API')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    // Dispatch sync job to queue
                    SyncDataJob::dispatch('divisions', Auth::id());

                    Notification::make()
                        ->title('Sync Started')
                        ->body('Data sync has been queued and will run in the background. You will be notified when it completes.')
                        ->info()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sync Data')
                ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data Division dari API? Proses ini akan berjalan di background.')
                ->modalSubmitActionLabel('Ya, Sync Data');
        }

        return $actions;
    }
}
