<?php

namespace App\Filament\Exports;

use App\Models\Outlet;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;

class OutletExporter extends Exporter
{
    protected static ?string $model = Outlet::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('badanUsaha.name')
                ->label('Badan Usaha'),
            ExportColumn::make('division.name')
                ->label('Divisi'),
            ExportColumn::make('region.name')
                ->label('Region'),
            ExportColumn::make('cluster.name')
                ->label('Cluster'),
            ExportColumn::make('code')
                ->label('Kode Outlet'),
            ExportColumn::make('name')
                ->label('Nama Outlet'),
            ExportColumn::make('address')
                ->label('Alamat Outlet'),
            ExportColumn::make('district')
                ->label('Distric'),
            ExportColumn::make('status')
                ->label('Status Outlet'),
            ExportColumn::make('level')
                ->label('Level Outlet'),
            ExportColumn::make('radius')
                ->label('Radius')
                ->getStateUsing(fn ($record) => $record->radius.' m'),
            ExportColumn::make('limit')
                ->label('Limit'),
            ExportColumn::make('location')
                ->label('LatLong'),
            ExportColumn::make('owner_name')
                ->label('Nama Pemilik Outlet'),
            ExportColumn::make('owner_phone')
                ->label('Nomor Telepon Outlet'),
            ExportColumn::make('photo_shop_sign')
                ->label('Foto Papan Nama'),
            ExportColumn::make('photo_front')
                ->label('Foto Depan'),
            ExportColumn::make('photo_left')
                ->label('Foto Kiri'),
            ExportColumn::make('photo_right')
                ->label('Foto Kanan'),
            ExportColumn::make('photo_id_card')
                ->label('Foto KTP'),
            ExportColumn::make('video')
                ->label('Video'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your outlet export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

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
        $options->setColumnWidth(20, 1);  // Badan Usaha
        $options->setColumnWidth(15, 2);  // Divisi
        $options->setColumnWidth(15, 3);  // Region
        $options->setColumnWidth(15, 4);  // Cluster
        $options->setColumnWidth(15, 5);  // Code
        $options->setColumnWidth(25, 6);  // Name
        $options->setColumnWidth(35, 7);  // Address
        $options->setColumnWidth(15, 8);  // District
        $options->setColumnWidth(15, 9);  // Status
        $options->setColumnWidth(12, 10); // Level
        $options->setColumnWidth(10, 11); // Radius
        $options->setColumnWidth(10, 12); // Limit
        $options->setColumnWidth(20, 13); // Location
        $options->setColumnWidth(20, 14); // Owner Name
        $options->setColumnWidth(20, 15); // Owner Phone

        return $options;
    }
}
