<?php

namespace App\Concerns;

use App\Filament\Components\PresetView;
use App\Filament\Components\SavedView;
use App\Models\TableView as TableViewModel;
use App\Models\TableViewFavorite as TableViewFavoriteModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasTableViewsModel
{
    /**
     * Get the filterable type for this model
     */
    public function getFilterableType(): string
    {
        return static::class;
    }

    /**
     * Get all saved table views for this model
     *
     * @return array<string | int, SavedView>
     */
    public function getSavedTableViews(): array
    {
        return TableViewModel::where('filterable_type', $this->getFilterableType())
            ->where(function ($query) {
                $query->where('user_id', Auth::id())
                    ->orWhere('is_public', true);
            })
            ->get()
            ->mapWithKeys(function (TableViewModel $tableView) {
                return [
                    $tableView->id => SavedView::make($tableView->getKey())
                        ->model($tableView)
                        ->label($tableView->name)
                        ->icon($tableView->icon)
                        ->color($tableView->color),
                ];
            })->all();
    }

    /**
     * Get favorite table views for this model
     *
     * @return array<string | int, PresetView|SavedView>
     */
    public function getFavoriteTableViews(): array
    {
        $allViews = $this->getAllTableViews();

        return collect($allViews)
            ->filter(function (PresetView $presetView, string|int $id) {
                return $presetView->isFavorite($id);
            })
            ->all();
    }

    /**
     * Get preset table views for this model (to be overridden by each model)
     *
     * @return array<string | int, PresetView>
     */
    public function getPresetTableViews(): array
    {
        return [];
    }

    /**
     * Get all table views (preset + saved) for this model
     *
     * @return array<string | int, PresetView|SavedView>
     */
    public function getAllTableViews(): array
    {
        return $this->getPresetTableViews() + $this->getSavedTableViews();
    }

    /**
     * Get cached favorite table views with default view
     *
     * @return array<string | int, PresetView|SavedView>
     */
    public function getCachedFavoriteTableViews(): array
    {
        return [
            'default' => PresetView::make('default')
                ->label(__('table_views.default'))
                ->icon('heroicon-m-queue-list')
                ->favorite(),
        ] + $this->getFavoriteTableViews();
    }

    /**
     * Create a new table view for this model
     */
    public function createTableView(array $data): TableViewModel
    {
        return TableViewModel::create([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
            'color' => $data['color'] ?? null,
            'filterable_type' => $this->getFilterableType(),
            'user_id' => Auth::id(),
            'filters' => $data['filters'] ?? [],
            'is_public' => $data['is_public'] ?? false,
        ]);
    }

    /**
     * Add or remove a table view from favorites
     */
    public function toggleTableViewFavorite(string|int $viewKey, string $viewType = 'saved', bool $isFavorite = true): void
    {
        TableViewFavoriteModel::updateOrCreate(
            [
                'view_type' => $viewType,
                'view_key' => $viewKey,
                'filterable_type' => $this->getFilterableType(),
                'user_id' => Auth::id(),
            ], [
                'is_favorite' => $isFavorite,
            ]
        );
    }

    /**
     * Delete a table view
     */
    public function deleteTableView(string|int $viewKey): bool
    {
        $tableView = TableViewModel::find($viewKey);

        if (! $tableView || $tableView->user_id !== Auth::id()) {
            return false;
        }

        // Delete related favorites
        TableViewFavoriteModel::where('view_key', $viewKey)
            ->where('filterable_type', $this->getFilterableType())
            ->delete();

        return $tableView->delete();
    }

    /**
     * Check if a view is favorited by current user
     */
    public function isViewFavorited(string|int $viewKey, string $viewType = 'saved'): bool
    {
        return TableViewFavoriteModel::where('view_key', $viewKey)
            ->where('view_type', $viewType)
            ->where('filterable_type', $this->getFilterableType())
            ->where('user_id', Auth::id())
            ->where('is_favorite', true)
            ->exists();
    }
}
