<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Resources\OutletResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\OutletHistory;

class EditOutlet extends EditRecord
{
    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
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
