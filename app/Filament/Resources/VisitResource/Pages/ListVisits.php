<?php

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\VisitExporter;
use App\Filament\Imports\VisitImporter;
use App\Filament\Resources\VisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVisits extends ListRecords
{
    protected static string $resource = VisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->exporter(VisitExporter::class),
            ImportAction::make()
                ->importer(VisitImporter::class),
        ];
    }
}
