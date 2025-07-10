<?php

namespace App\Filament\Exports;

use App\Models\PlanVisit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;

class PlanVisitExporter extends Exporter
{
    protected static ?string $model = PlanVisit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('visit_date')
                ->label('Tanggal Visit')
                ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('d-m-Y')),
            ExportColumn::make('user.role.name')
                ->label('Role'),
            ExportColumn::make('outlet.code')
                ->label('Kode Outlet'),
            ExportColumn::make('outlet.name')
                ->label('Nama Outlet'),
            ExportColumn::make('outlet.badanUsaha.name')
                ->label('Badan Usaha'),
            ExportColumn::make('outlet.division.name')
                ->label('Divisi'),
            ExportColumn::make('outlet.region.name')
                ->label('Region'),
            ExportColumn::make('outlet.cluster.name')
                ->label('Cluster'),

        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your plan visit export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontBold()
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    public function getXlsxOptions(): Options
    {
        $options = new Options;

        // Set column widths for better readability
        $options->setColumnWidth(15, 1); // Tanggal Visit
        $options->setColumnWidth(20, 2); // Role
        $options->setColumnWidth(15, 3); // Kode Outlet
        $options->setColumnWidth(30, 4); // Nama Outlet
        $options->setColumnWidth(25, 5); // Badan Usaha
        $options->setColumnWidth(20, 6); // Divisi
        $options->setColumnWidth(20, 7); // Region
        $options->setColumnWidth(20, 8); // Cluster

        return $options;
    }
}
