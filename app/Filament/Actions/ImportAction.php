<?php

namespace App\Filament\Actions;

use Filament\Actions\ImportAction as BaseImportAction;
use Illuminate\Support\Facades\Auth;

class ImportAction extends BaseImportAction
{
    public function isVisible(): bool
    {
        $user = Auth::user();
        $model = $this->getModel() ?? ($this->getResource()::$model ?? null);

        return $user && $model && $user->can('import', app($model));
    }
}
