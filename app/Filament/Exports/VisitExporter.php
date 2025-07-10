<?php

namespace App\Filament\Exports;

use App\Models\Visit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;

class VisitExporter extends Exporter
{
    protected static ?string $model = Visit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('visit_date')
                ->label('Tanggal Visit')
                ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d-m-Y')),
            ExportColumn::make('user.name')
                ->label('Nama'),
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
            ExportColumn::make('type')
                ->label('Tipe Visit'),
            ExportColumn::make('checkin_photo')
                ->label('Foto Check In')
                ->formatStateUsing(fn($state) => $state ? config('app.url') . '/storage/' . $state : null),
            ExportColumn::make('checkout_photo')
                ->label('Foto Check Out')
                ->formatStateUsing(fn($state) => $state ? config('app.url') . '/storage/' . $state : null),
            ExportColumn::make('checkin_location')
                ->label('Lokasi Check In'),
            ExportColumn::make('checkout_location')
                ->label('Lokasi Check Out'),
            ExportColumn::make('checkin_time')
                ->label('Waktu Check In')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : null),
            ExportColumn::make('checkout_time')
                ->label('Waktu Check Out')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : null),
            ExportColumn::make('duration')
                ->label('Durasi')
                ->formatStateUsing(fn ($state) => $state ? $state.' menit' : null),
            ExportColumn::make('transaction')
                ->label('Transaksi'),
            ExportColumn::make('report')
                ->label('Laporan'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your visit export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

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
        $options->setColumnWidth(15, 1);  // Tanggal Visit
        $options->setColumnWidth(20, 2);  // Nama User
        $options->setColumnWidth(15, 3);  // Role
        $options->setColumnWidth(15, 4);  // Kode Outlet
        $options->setColumnWidth(25, 5);  // Nama Outlet
        $options->setColumnWidth(20, 6);  // Badan Usaha
        $options->setColumnWidth(15, 7);  // Divisi
        $options->setColumnWidth(15, 8);  // Region
        $options->setColumnWidth(15, 9);  // Cluster
        $options->setColumnWidth(12, 10); // Tipe Visit
        $options->setColumnWidth(20, 11); // Foto Check In
        $options->setColumnWidth(20, 12); // Foto Check Out
        $options->setColumnWidth(25, 13); // Lokasi Check In
        $options->setColumnWidth(25, 14); // Lokasi Check Out
        $options->setColumnWidth(12, 15); // Waktu Check In
        $options->setColumnWidth(12, 16); // Waktu Check Out
        $options->setColumnWidth(10, 17); // Durasi
        $options->setColumnWidth(10, 18); // Transaksi
        $options->setColumnWidth(30, 19); // Laporan

        return $options;
    }
}
