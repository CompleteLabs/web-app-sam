<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Resources\OutletResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use App\Models\OutletHistory;

class CreateOutlet extends CreateRecord
{
    protected static string $resource = OutletResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $record = parent::handleRecordCreation($data);
        // Catat history pembuatan outlet baru
        OutletHistory::create([
            'outlet_id' => $record->id,
            'from_level' => null,
            'to_level' => $record->level,
            'requested_by' => Auth::id(),
            'approved_by' => null,
            'approval_status' => null,
            'requested_at' => now(),
            'approved_at' => now(),
            'approval_notes' => null,
        ]);
        return $record;
    }
}
