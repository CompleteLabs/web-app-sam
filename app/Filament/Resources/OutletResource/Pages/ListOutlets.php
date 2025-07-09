<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\OutletExporter;
use App\Filament\Imports\OutletImporter;
use App\Filament\Resources\OutletResource;
use App\Jobs\SyncDataJob;
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
                ->label('Sync Outlets')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Outlets')
                ->modalDescription('Sinkronisasi data outlets dari API. Proses akan dijalankan di background menggunakan queue.')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataJob::dispatch('outlets', Auth::id());

                    Notification::make()
                        ->title('Sync Outlets Started')
                        ->body('Proses sync outlets telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
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
