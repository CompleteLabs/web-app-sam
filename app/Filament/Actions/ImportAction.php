<?php

namespace App\Filament\Actions;

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
}
