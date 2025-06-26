<?php

namespace App\Filament\Actions;

use App\Models\TableView;
use App\Models\TableViewFavorite;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanCustomizeProcess;
use Filament\Forms;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;

class CreateViewAction extends Action
{
    use CanCustomizeProcess;

    public static function getDefaultName(): ?string
    {
        return 'table_views.save.action';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->model(TableView::class)
            ->form([
                Forms\Components\TextInput::make('name')
                    ->label(__('table_views.actions.create_view.form.name'))
                    ->autofocus()
                    ->required(),
                \Guava\FilamentIconPicker\Forms\IconPicker::make('icon')
                    ->label(__('table_views.actions.create_view.form.icon'))
                    ->sets(['heroicons'])
                    ->columns(4)
                    ->preload()
                    ->optionsLimit(50),
                Forms\Components\Toggle::make('is_favorite')
                    ->label(__('table_views.actions.create_view.form.add_to_favorites'))
                    ->helperText(__('table_views.actions.create_view.form.add_to_favorites_help')),
                Forms\Components\Toggle::make('is_public')
                    ->label(__('table_views.actions.create_view.form.make_public'))
                    ->helperText(__('table_views.actions.create_view.form.make_public_help')),
            ])->action(function (): void {
                $model = $this->getModel();

                $record = $this->process(function (array $data) use ($model): TableView {
                    $record = new $model;
                    $record->fill($data);

                    $record->save();

                    TableViewFavorite::create([
                        'view_type' => 'saved',
                        'view_key' => $record->id,
                        'filterable_type' => $record->filterable_type,
                        'user_id' => Auth::id(),
                        'is_favorite' => $data['is_favorite'],
                    ]);

                    return $record;
                });

                $this->record($record);

                $this->success();
            })
            ->label(__('table_views.actions.create_view.label'))
            ->link()
            ->successNotificationTitle(__('table_views.actions.create_view.form.notification.created'))
            ->modalHeading(__('table_views.actions.create_view.form.modal.title'))
            ->modalWidth(MaxWidth::Medium);
    }
}
