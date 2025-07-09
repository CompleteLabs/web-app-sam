<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Facades\LogActivity;

class ActivityLogService
{
    /**
     * Log export activity
     */
    public static function logExport(string $modelName, ?string $exporterClass = null, array $additionalData = []): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Get the full model class name
        $modelClass = "App\\Models\\{$modelName}";

        // Create a representative model instance for subject
        if (class_exists($modelClass)) {
            $modelInstance = new $modelClass();
        } else {
            // Fallback if model doesn't exist
            $modelInstance = $user; // Use user as fallback subject
        }

        $properties = array_merge([
            'model' => $modelName,
            'action' => 'export',
            'exporter_class' => $exporterClass,
            'timestamp' => now()->toDateTimeString(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ], $additionalData);

        activity('custom')
            ->causedBy($user)
            ->performedOn($modelInstance)
            ->event('export')
            ->withProperties($properties)
            ->log("Exported {$modelName} data");
    }

    /**
     * Log import activity
     */
    public static function logImport(string $modelName, ?string $importerClass = null, array $additionalData = []): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Get the full model class name
        $modelClass = "App\\Models\\{$modelName}";

        // Create a representative model instance for subject
        if (class_exists($modelClass)) {
            $modelInstance = new $modelClass();
        } else {
            // Fallback if model doesn't exist
            $modelInstance = $user; // Use user as fallback subject
        }

        $properties = array_merge([
            'model' => $modelName,
            'action' => 'import',
            'importer_class' => $importerClass,
            'timestamp' => now()->toDateTimeString(),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ], $additionalData);

        activity('custom')
            ->causedBy($user)
            ->performedOn($modelInstance)
            ->event('import')
            ->withProperties($properties)
            ->log("Imported {$modelName} data");
    }

    /**
     * Log export completion with results
     */
    public static function logExportCompleted(string $modelName, int $recordsExported, ?string $fileName = null): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Get the full model class name
        $modelClass = "App\\Models\\{$modelName}";

        // Create a representative model instance for subject
        if (class_exists($modelClass)) {
            $modelInstance = new $modelClass();
        } else {
            // Fallback if model doesn't exist
            $modelInstance = $user; // Use user as fallback subject
        }

        activity('custom')
            ->causedBy($user)
            ->performedOn($modelInstance)
            ->event('export_completed')
            ->withProperties([
                'model' => $modelName,
                'action' => 'export_completed',
                'records_exported' => $recordsExported,
                'file_name' => $fileName,
                'timestamp' => now()->toDateTimeString(),
            ])
            ->log("Export completed: {$recordsExported} {$modelName} records exported" . ($fileName ? " to {$fileName}" : ''));
    }

    /**
     * Log import completion with results
     */
    public static function logImportCompleted(string $modelName, int $recordsImported, int $recordsFailed = 0, ?string $fileName = null): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        // Get the full model class name
        $modelClass = "App\\Models\\{$modelName}";

        // Create a representative model instance for subject
        if (class_exists($modelClass)) {
            $modelInstance = new $modelClass();
        } else {
            // Fallback if model doesn't exist
            $modelInstance = $user; // Use user as fallback subject
        }

        activity('custom')
            ->causedBy($user)
            ->performedOn($modelInstance)
            ->event('import_completed')
            ->withProperties([
                'model' => $modelName,
                'action' => 'import_completed',
                'records_imported' => $recordsImported,
                'records_failed' => $recordsFailed,
                'file_name' => $fileName,
                'timestamp' => now()->toDateTimeString(),
            ])
            ->log("Import completed: {$recordsImported} {$modelName} records imported" .
                  ($recordsFailed > 0 ? ", {$recordsFailed} failed" : '') .
                  ($fileName ? " from {$fileName}" : ''));
    }

    /**
     * Log custom activity
     */
    public static function logCustomActivity(string $description, array $properties = [], string $logName = 'custom'): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        activity($logName)
            ->causedBy($user)
            ->withProperties(array_merge($properties, [
                'timestamp' => now()->toDateTimeString(),
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
            ]))
            ->log($description);
    }
}
