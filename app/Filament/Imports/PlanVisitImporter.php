<?php

namespace App\Filament\Imports;

use App\Models\PlanVisit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Apriansyahrs\ImportExcel\Models\FailedImportRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Exception;

class PlanVisitImporter extends Importer
{
    protected static ?string $model = PlanVisit::class;

    // Store the record after it's been processed
    protected ?Model $record = null;

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
            ImportColumn::make('visit_date')
                ->requiredMapping()
                ->rules(['required', 'date']),
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
            Log::error('Error importing plan visit: ' . $e->getMessage(), [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the record that was created/updated during import
     */
    public function getRecord(): ?PlanVisit
    {
        return $this->record;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your plan visit import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
