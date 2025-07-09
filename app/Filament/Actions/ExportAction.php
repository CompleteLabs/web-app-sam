<?php

namespace App\Filament\Actions;

use App\Services\ActivityLogService;
use Filament\Actions\ExportAction as BaseExportAction;
use Illuminate\Support\Facades\Auth;

class ExportAction extends BaseExportAction
{
    public function isVisible(): bool
    {
        $user = Auth::user();
        $model = $this->getModel() ?? ($this->getResource()::$model ?? null);

        return $user && $model && $user->can('export', app($model));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->before(function () {
            $user = Auth::user();
            $model = $this->getModel() ?? ($this->getResource()::$model ?? null);

            if ($user && $model) {
                $modelName = class_basename($model);
                $exporterClass = $this->getExporter();

                ActivityLogService::logExport($modelName, $exporterClass);
            }
        });
    }
}
