<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Resources\OutletResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\OutletHistory;
use App\Services\FileUploadService;

class EditOutlet extends EditRecord
{
    protected static string $resource = OutletResource::class;

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
        // Simpan semua field foto & video yang lama
        $this->oldPhotos = [
            'photo_shop_sign' => $this->record->photo_shop_sign,
            'photo_front' => $this->record->photo_front,
            'photo_left' => $this->record->photo_left,
            'photo_right' => $this->record->photo_right,
            'photo_id_card' => $this->record->photo_id_card,
            'video' => $this->record->video,
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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $oldLevel = $record->level;
        $updatedRecord = parent::handleRecordUpdate($record, $data);
        $newLevel = $updatedRecord->level;
        if ($oldLevel !== $newLevel) {
            $approvalStatus = 'APPROVED';
            $approvedBy = Auth::id();
            if (($oldLevel === null && $newLevel === 'LEAD') || ($oldLevel === 'LEAD' && $newLevel === 'NOO')) {
                $approvalStatus = null;
                $approvedBy = null;
            }
            OutletHistory::create([
                'outlet_id' => $updatedRecord->id,
                'from_level' => $oldLevel,
                'to_level' => $newLevel,
                'requested_by' => Auth::id(),
                'approved_by' => $approvedBy,
                'approval_status' => $approvalStatus,
                'requested_at' => now(),
                'approved_at' => now(),
                'approval_notes' => null,
            ]);
        }
        return $updatedRecord;
    }
}
