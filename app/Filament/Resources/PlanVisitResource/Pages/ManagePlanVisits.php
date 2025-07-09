<?php

namespace App\Filament\Resources\PlanVisitResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Imports\PlanVisitImporter;
use App\Filament\Resources\PlanVisitResource;
use App\Jobs\SyncDataJob;
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
                ->label('Sync Plan Visits')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync Plan Visits')
                ->modalDescription('Sinkronisasi data plan visits dari API. Proses akan dijalankan di background menggunakan queue.')
                ->action(function () {
                    // Dispatch job to queue
                    SyncDataJob::dispatch('planvisits', Auth::id());

                    Notification::make()
                        ->title('Sync Plan Visits Started')
                        ->body('Proses sync plan visits telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.')
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
