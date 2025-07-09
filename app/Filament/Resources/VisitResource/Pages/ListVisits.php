<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\VisitExporter;
use App\Filament\Imports\VisitImporter;
use App\Filament\Resources\VisitResource;
use App\Jobs\SyncDataJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Only show sync button for super admin
        if (Auth::user() && Auth::user()->role && Auth::user()->role->name === 'SUPER ADMIN') {
            $actions[] = Actions\Action::make('sync_visits')
                ->label('Sync Visits')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Visits')
                ->modalDescription('Sinkronisasi data visits dari API. Proses akan dijalankan di background menggunakan queue.')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataJob::dispatch('visits', Auth::id());

                    Notification::make()
                        ->title('Sync Visits Started')
                        ->body('Proses sync visits telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
                        ->info()
                        ->send();
                });
        }

        $actions[] = ExportAction::make()
            ->exporter(VisitExporter::class);

        $actions[] = ImportAction::make()
            ->importer(VisitImporter::class);

        return $actions;
    }
}
