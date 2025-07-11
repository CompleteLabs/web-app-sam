<?php

namespace App\Filament\Imports;

use App\Models\Outlet;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Apriansyahrs\ImportExcel\Models\FailedImportRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Exception;

class OutletImporter extends Importer
{
    protected static ?string $model = Outlet::class;

    // Store the record after it's been processed
    protected ?Model $record = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('badan_usaha_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('division_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('region_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('cluster_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('code')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('address')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('district')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'in:MAINTAIN,UNMAINTAIN,UNPRODUCTIVE']),
            ImportColumn::make('radius')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('limit')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('location')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('level')
                ->rules(['nullable', 'in:LEAD,NOO,MEMBER']),
            // Optional columns that match model fillable fields
            ImportColumn::make('owner_name')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('owner_phone')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('photo_shop_sign')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('photo_front')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('photo_left')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('photo_right')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('photo_id_card')
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('video')
                ->rules(['nullable', 'max:255']),
        ];
    }

    public function resolveRecord(): ?Outlet
    {
        // return Outlet::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new Outlet;
    }

    protected function beforeFill(): void
    {
        // Convert name fields to IDs (similar to role_name -> role_id in UserImporter)

        // Set badan_usaha_id from badan_usaha_name if provided
        if (isset($this->data['badan_usaha_name']) && ! empty($this->data['badan_usaha_name'])) {
            $badanUsaha = \App\Models\BadanUsaha::where('name', $this->data['badan_usaha_name'])->first();
            if ($badanUsaha) {
                $this->data['badan_usaha_id'] = $badanUsaha->id;
            }
            unset($this->data['badan_usaha_name']);
        }

        // Set division_id from division_name if provided
        if (isset($this->data['division_name']) && ! empty($this->data['division_name'])) {
            $division = \App\Models\Division::where('name', $this->data['division_name'])->first();
            if ($division) {
                $this->data['division_id'] = $division->id;
            }
            unset($this->data['division_name']);
        }

        // Set region_id from region_name if provided
        if (isset($this->data['region_name']) && ! empty($this->data['region_name'])) {
            $region = \App\Models\Region::where('name', $this->data['region_name'])->first();
            if ($region) {
                $this->data['region_id'] = $region->id;
            }
            unset($this->data['region_name']);
        }

        // Set cluster_id from cluster_name if provided
        if (isset($this->data['cluster_name']) && ! empty($this->data['cluster_name'])) {
            $cluster = \App\Models\Cluster::where('name', $this->data['cluster_name'])->first();
            if ($cluster) {
                $this->data['cluster_id'] = $cluster->id;
            }
            unset($this->data['cluster_name']);
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // Mirip dengan alur role_id di UserImporter: set ID langsung dari data yang sudah diproses di beforeFill
        $needsUpdate = false;

        // Pastikan badan_usaha_id sudah benar setelah save (seperti role_id flow)
        if (isset($this->data['badan_usaha_id']) && $record->badan_usaha_id !== $this->data['badan_usaha_id']) {
            $record->badan_usaha_id = $this->data['badan_usaha_id'];
            $needsUpdate = true;
        }

        // Pastikan division_id sudah benar setelah save
        if (isset($this->data['division_id']) && $record->division_id !== $this->data['division_id']) {
            $record->division_id = $this->data['division_id'];
            $needsUpdate = true;
        }

        // Pastikan region_id sudah benar setelah save
        if (isset($this->data['region_id']) && $record->region_id !== $this->data['region_id']) {
            $record->region_id = $this->data['region_id'];
            $needsUpdate = true;
        }

        // Pastikan cluster_id sudah benar setelah save
        if (isset($this->data['cluster_id']) && $record->cluster_id !== $this->data['cluster_id']) {
            $record->cluster_id = $this->data['cluster_id'];
            $needsUpdate = true;
        }

        // Only save if there were actual changes
        if ($needsUpdate) {
            $record->save();
        }
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

            // Call the existing beforeFill method to process data
            $this->beforeFill();

            // Create and save the record
            $record = $this->resolveRecord();
            $record->fill($this->data);
            $record->save();

            // Store the record for afterSave method
            $this->record = $record;

            // Call afterSave to handle any post-save logic
            $this->afterSave();
        } catch (ValidationException $e) {
            // Re-throw validation exceptions to be caught by the import job
            throw $e;
        } catch (Exception $e) {
            // Log the error and re-throw for the import job to handle
            Log::error('Error importing outlet: ' . $e->getMessage(), [
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
    public function getRecord(): ?Outlet
    {
        return $this->record;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your outlet import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
