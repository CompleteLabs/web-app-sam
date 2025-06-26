<?php

namespace App\Filament\Exports;

use App\Models\Outlet;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

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
            ExportColumn::make('cluser.name')
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
            ExportColumn::make('photo_shop_sign'),
            ExportColumn::make('photo_front'),
            ExportColumn::make('photo_left'),
            ExportColumn::make('photo_right'),
            ExportColumn::make('video'),
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
}
