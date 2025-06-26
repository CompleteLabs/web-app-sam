<?php

namespace App\Filament\Resources\OutletResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\OutletExporter;
use App\Filament\Imports\OutletImporter;
use App\Filament\Resources\OutletResource;
use Apriansyahrs\CustomFields\Filament\Tables\Concerns\InteractsWithCustomFields;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOutlets extends ListRecords
{
    use InteractsWithCustomFields;

    protected static string $resource = OutletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->exporter(OutletExporter::class),
            ImportAction::make()
                ->importer(OutletImporter::class),
        ];
    }
}
