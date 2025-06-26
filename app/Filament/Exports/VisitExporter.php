<?php

namespace App\Filament\Exports;

use App\Models\Visit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;

class VisitExporter extends Exporter
{
    protected static ?string $model = Visit::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tanggal_visit')
                ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d-m-Y')),
            ExportColumn::make('user.name')
                ->label('Nama'),
            ExportColumn::make('user.role.name')
                ->label('Role'),
            ExportColumn::make('outlet.kode_outlet')
                ->label('Kode Outlet'),
            ExportColumn::make('outlet.nama_outlet')
                ->label('Nama Outlet'),
            ExportColumn::make('outlet.badanUsaha.name')
                ->label('Badan Usaha'),
            ExportColumn::make('outlet.division.name')
                ->label('Divisi'),
            ExportColumn::make('outlet.region.name')
                ->label('Region'),
            ExportColumn::make('outlet.cluster.name')
                ->label('Cluster'),
            ExportColumn::make('tipe_visit'),
            ExportColumn::make('picture_visit_in'),
            ExportColumn::make('picture_visit_out'),
            ExportColumn::make('latlong_in'),
            ExportColumn::make('latlong_out'),
            ExportColumn::make('check_in_time')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : null),
            ExportColumn::make('check_out_time')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : null),
            ExportColumn::make('durasi_visit')
                ->formatStateUsing(fn ($state) => $state ? $state.' menit' : null),
            ExportColumn::make('transaksi'),
            ExportColumn::make('laporan_visit'),
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
}
