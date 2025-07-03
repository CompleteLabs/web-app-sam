<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Services\FileUploadService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVisit extends EditRecord
{
    protected static string $resource = VisitResource::class;

    /**
     * Store old photo fields for afterSave
     */
    protected array $oldPhotos = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Simpan field foto lama
        $this->oldPhotos = [
            'checkin_photo' => $this->record->checkin_photo,
            'checkout_photo' => $this->record->checkout_photo,
        ];
        return $data;
    }

    protected function afterSave(): void
    {
        // Hapus file lama jika diganti
        foreach ($this->oldPhotos as $field => $oldFile) {
            if ($oldFile && $oldFile !== $this->record->{$field}) {
                FileUploadService::deleteFile($oldFile);
            }
        }
    }
}
