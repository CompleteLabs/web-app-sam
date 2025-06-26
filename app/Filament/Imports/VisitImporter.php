<?php

namespace App\Filament\Imports;

use App\Models\Visit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class VisitImporter extends Importer
{
    protected static ?string $model = Visit::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('tanggal_visit')
                ->requiredMapping()
                ->rules(['required', 'datetime']),
            ImportColumn::make('user_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('outlet_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('tipe_visit')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('latlong_in')
                ->rules(['max:255']),
            ImportColumn::make('latlong_out')
                ->rules(['max:255']),
            ImportColumn::make('check_in_time')
                ->rules(['datetime']),
            ImportColumn::make('check_out_time')
                ->rules(['datetime']),
            ImportColumn::make('laporan_visit'),
            ImportColumn::make('transaksi'),
            ImportColumn::make('durasi_visit')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('picture_visit_in'),
            ImportColumn::make('picture_visit_out'),
        ];
    }

    public function resolveRecord(): ?Visit
    {
        // return Visit::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new Visit;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your visit import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
