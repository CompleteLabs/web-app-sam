<?php

namespace App\Filament\Resources\PlanVisitResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Imports\PlanVisitImporter;
use App\Filament\Resources\PlanVisitResource;
use App\Jobs\SyncDataDispatcherJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManagePlanVisits extends ManageRecords
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make(),
        ];

        // Only show sync button for super admin
        if (Auth::user() && Auth::user()->role && Auth::user()->role->name === 'SUPER ADMIN') {
            $actions[] = Actions\Action::make('sync_plan_visits')
                ->label('Sync dari API')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sync Data')
                ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data Plan Visit dari API? Proses ini akan berjalan di background.')
                ->modalSubmitActionLabel('Ya, Sync Data')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataDispatcherJob::dispatch('planvisits', Auth::id());

                    Notification::make()
                        ->title('Sinkronisasi Dimulai')
                        ->body('Proses sinkronisasi Plan Visit telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
                        ->info()
                        ->send();
                });
        }

        $actions[] = ExportAction::make()
            ->exporter(PlanVisitExporter::class);

        $actions[] = ImportAction::make()
            ->importer(PlanVisitImporter::class);

        return $actions;
    }
}
