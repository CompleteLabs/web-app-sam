<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\OutletExporter;
use App\Filament\Imports\OutletImporter;
use App\Filament\Resources\OutletResource;
use App\Jobs\SyncDataDispatcherJob;
use App\Models\Outlet;
use Apriansyahrs\CustomFields\Filament\Tables\Concerns\InteractsWithCustomFields;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ListOutlets extends ListRecords
{
    use InteractsWithCustomFields;

    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Only show sync button for super admin
        if (Auth::user() && Auth::user()->role && Auth::user()->role->name === 'SUPER ADMIN') {
            $actions[] = Actions\Action::make('sync_outlets')
                ->label('Sync dari API')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sync Data')
                ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data Outlet dari API? Proses ini akan berjalan di background.')
                ->modalSubmitActionLabel('Ya, Sync Data')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataDispatcherJob::dispatch('outlets', Auth::id());

                    Notification::make()
                        ->title('Sinkronisasi Dimulai')
                        ->body('Proses sinkronisasi Outlet telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
                        ->info()
                        ->send();
                });
        }

        $actions[] = ExportAction::make()
            ->exporter(OutletExporter::class);

        $actions[] = ImportAction::make()
            ->importer(OutletImporter::class);

        return $actions;
    }
}
