<?php

namespace App\Filament\Actions;

use App\Services\ActivityLogService;
use Apriansyahrs\ImportExcel\Actions\FullImportAction;
use Illuminate\Support\Facades\Auth;

class ImportAction extends FullImportAction
{
    public function isVisible(): bool
    {
        $user = Auth::user();
        $model = $this->getModel() ?? ($this->getResource()::$model ?? null);

        return $user && $model && $user->can('import', app($model));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->before(function () {
            $user = Auth::user();
            $model = $this->getModel() ?? ($this->getResource()::$model ?? null);

            if ($user && $model) {
                $modelName = class_basename($model);
                $importerClass = $this->getImporter();

                ActivityLogService::logImport($modelName, $importerClass);
            }
        });
    }
}
