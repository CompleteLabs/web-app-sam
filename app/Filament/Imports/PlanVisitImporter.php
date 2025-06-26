<?php

namespace App\Filament\Imports;

use App\Models\PlanVisit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class PlanVisitImporter extends Importer
{
    protected static ?string $model = PlanVisit::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('user_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('outlet_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('tanggal_visit')
                ->requiredMapping()
                ->rules(['required', 'datetime']),
        ];
    }

    public function resolveRecord(): ?PlanVisit
    {
        // return PlanVisit::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new PlanVisit;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your plan visit import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
