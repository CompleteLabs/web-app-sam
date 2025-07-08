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
            ExportColumn::make('user.name')
                ->label('Nama User'),
            ExportColumn::make('outlet.name')
                ->label('Nama Outlet'),
            ExportColumn::make('visit_date')
                ->label('Tanggal Visit')
                ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d-m-Y')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your plan visit export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
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
        $options->setColumnWidth(25, 1); // Nama User
        $options->setColumnWidth(30, 2); // Nama Outlet
        $options->setColumnWidth(15, 3); // Tanggal Visit

        return $options;
    }
}
