<?php

namespace App\Filament\Actions;

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
}
