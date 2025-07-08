<?php

namespace App\Filament\Imports;

use App\Models\Visit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Apriansyahrs\ImportExcel\Models\FailedImportRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Exception;

class VisitImporter extends Importer
{
    protected static ?string $model = Visit::class;

    // Store the record after it's been processed
    protected ?Model $record = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('visit_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
            ImportColumn::make('user_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('outlet_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('type')
                ->requiredMapping()
                ->rules(['required', 'in:EXTRACALL,PLANNED']),
            ImportColumn::make('checkin_location')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('checkout_location')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('checkin_time')
                ->rules(['nullable', 'date']),
            ImportColumn::make('checkout_time')
                ->rules(['nullable', 'date']),
            ImportColumn::make('checkin_photo')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('checkout_photo')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('duration')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('transaction')
                ->rules(['nullable', 'in:YES,NO']),
            ImportColumn::make('report')
                ->rules(['nullable']),
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

    /**
     * Custom import method for Excel functionality with FailedImportRow support
     * This method is called by the apriansyahrs/import-excel package
     */
    public function import(array $data, array $map, array $options = []): void
    {
        try {
            // Store data for processing
            $this->data = $data;

            // Create and save the record
            $record = $this->resolveRecord();
            $record->fill($this->data);
            $record->save();

            // Store the record for future reference
            $this->record = $record;
        } catch (ValidationException $e) {
            // Re-throw validation exceptions to be caught by the import job
            throw $e;
        } catch (Exception $e) {
            // Log the error and re-throw for the import job to handle
            Log::error('Error importing visit: ' . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get the record that was created/updated during import
     */
    public function getRecord(): ?Visit
    {
        return $this->record;
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
