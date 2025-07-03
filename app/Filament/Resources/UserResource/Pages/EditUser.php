<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\FileUploadService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Store old photo filename for afterSave
     */
    protected ?string $oldPhoto = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldPhoto = $this->record->photo;
        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->oldPhoto && $this->oldPhoto !== $this->record->photo) {
            FileUploadService::deleteFile($this->oldPhoto);
        }
    }
}
