<?php

namespace App\Filament\Filters;

use Filament\Tables\Filters\TrashedFilter as BaseTrashedFilter;
use Illuminate\Support\Facades\Auth;

class TrashedFilter extends BaseTrashedFilter
{
    public function isVisible(): bool
    {
        $user = Auth::user();
        // Use the model from the table context if available
        $model = $this->table?->getModel() ?? null;

        return $user && $model && $user->can('forceDelete', app($model));
    }
}
