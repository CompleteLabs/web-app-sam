<?php

namespace App\Filament\Resources\PlanVisitResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Exports\PlanVisitExporter;
use App\Filament\Imports\PlanVisitImporter;
use App\Filament\Resources\PlanVisitResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlanVisits extends ManageRecords
{
    protected static string $resource = PlanVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ExportAction::make()
                ->exporter(PlanVisitExporter::class),
            ImportAction::make()
                ->importer(PlanVisitImporter::class),
        ];
    }
}
