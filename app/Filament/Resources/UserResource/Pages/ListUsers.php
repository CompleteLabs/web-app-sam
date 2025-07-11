<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\UserExporter;
use App\Filament\Imports\UserImporter;
use App\Filament\Resources\UserResource;
use App\Jobs\SyncDataDispatcherJob;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Only show sync button for super admin
        if (Auth::user() && Auth::user()->role && Auth::user()->role->name === 'SUPER ADMIN') {
            $actions[] = Actions\Action::make('sync_users')
                ->label('Sync Users')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Users')
                ->modalDescription('Sinkronisasi data users dari API. Proses akan dijalankan di background menggunakan queue.')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataDispatcherJob::dispatch('users', Auth::id());

                    Notification::make()
                        ->title('Sync Users Started')
                        ->body('Proses sync users telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
                        ->info()
                        ->send();
                });
        }

        $actions[] = ImportAction::make()
            ->importer(UserImporter::class);

        $actions[] = ExportAction::make()
            ->exporter(UserExporter::class);

        return $actions;
    }
}
