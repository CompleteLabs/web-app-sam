<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\VisitExporter;
use App\Filament\Imports\VisitImporter;
use App\Filament\Resources\VisitResource;
use App\Jobs\SyncDataDispatcherJob;
use Filament\Actions;
use Filament\Forms;
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
                    // Get current month and year as default
                    $currentMonth = now()->month;
                    $currentYear = now()->year;

                    // Dispatch job to queue with current month/year
                    SyncDataDispatcherJob::dispatch('visits', Auth::id(), 100, $currentMonth, $currentYear);

                    Notification::make()
                        ->title('Sync Visits Started')
                        ->body("Proses sync visits untuk bulan {$currentMonth} tahun {$currentYear} telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.")
                        ->info()
                        ->send();
                });

            $actions[] = Actions\Action::make('sync_visits_custom')
                ->label('Sync Visits (Custom Month)')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sync Visits - Custom Month')
                ->modalDescription('Sinkronisasi data visits dari API untuk bulan dan tahun tertentu.')
                ->form([
                    Forms\Components\Select::make('month')
                        ->label('Month')
                        ->options([
                            1 => 'January',
                            2 => 'February',
                            3 => 'March',
                            4 => 'April',
                            5 => 'May',
                            6 => 'June',
                            7 => 'July',
                            8 => 'August',
                            9 => 'September',
                            10 => 'October',
                            11 => 'November',
                            12 => 'December'
                        ])
                        ->default(now()->month)
                        ->required(),
                    Forms\Components\TextInput::make('year')
                        ->label('Year')
                        ->numeric()
                        ->minValue(2020)
                        ->maxValue(2030)
                        ->default(now()->year)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $month = $data['month'];
                    $year = $data['year'];

                    // Dispatch job to queue with custom month/year
                    SyncDataDispatcherJob::dispatch('visits', Auth::id(), 100, $month, $year);

                    $monthName = \Carbon\Carbon::create($year, $month, 1)->format('F');
                    Notification::make()
                        ->title('Sync Visits Started')
                        ->body("Proses sync visits untuk {$monthName} {$year} telah dimulai di background. Anda akan mendapat notifikasi setelah selesai.")
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
