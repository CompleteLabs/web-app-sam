<?php

namespace App\Filament\Components;

use App\Models\TableView as ModelsTableView;
use Illuminate\Support\Facades\Auth;

class SavedView extends PresetView
{
    protected ModelsTableView $model;

    public function model(ModelsTableView $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ModelsTableView
    {
        return $this->model;
    }

    public function isFavorite(string|int|null $id = null): bool
    {
        $tableViewFavorite = $this->getCachedFavoriteTableViews()
            ->where('view_type', 'saved')
            ->where('view_key', $id ?? $this->model->id)
            ->first();

        return (bool) ($tableViewFavorite?->is_favorite ?? $this->isFavorite);
    }

    public function isPublic(): bool
    {
        return $this->model->is_public;
    }

    public function isEditable(): bool
    {
        return $this->model->user_id === Auth::id();
    }

    public function isReplaceable(): bool
    {
        return $this->model->user_id === Auth::id();
    }

    public function isDeletable(): bool
    {
        return $this->model->user_id === Auth::id();
    }

    public function getVisibilityIcon(): string
    {
        return $this->isPublic() ? 'heroicon-o-eye' : 'heroicon-o-user';
    }
}
