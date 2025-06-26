<?php

namespace App\Filament\Exports;

use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('no')
                ->label('No')
                ->getStateUsing(function ($record) {
                    static $users = null;
                    if ($users === null) {
                        $users = User::orderBy('id')->pluck('id')->toArray();
                        $users = array_flip($users);
                    }

                    return ($users[$record->id] ?? 0) + 1;
                }),
            ExportColumn::make('name'),
            ExportColumn::make('role.name')
                ->label('Role'),
            ExportColumn::make('userScopes.badan_usaha_id')
                ->label('Badan Usaha')
                ->getStateUsing(function ($record) {
                    $scopes = $record->userScopes;
                    $badanUsahaNames = [];
                    foreach ($scopes as $scope) {
                        if ($scope->badan_usaha_id) {
                            $ids = is_string($scope->badan_usaha_id) ? json_decode($scope->badan_usaha_id, true) : $scope->badan_usaha_id;
                            if (is_array($ids)) {
                                $names = \App\Models\BadanUsaha::whereIn('id', $ids)->pluck('name')->toArray();
                                $badanUsahaNames = array_merge($badanUsahaNames, $names);
                            }
                        }
                    }

                    return implode('; ', array_unique($badanUsahaNames));
                }),
            ExportColumn::make('userScopes.division_id')
                ->label('Divisi')
                ->getStateUsing(function ($record) {
                    $scopes = $record->userScopes;
                    $divisionNames = [];
                    foreach ($scopes as $scope) {
                        if ($scope->division_id) {
                            $ids = is_string($scope->division_id) ? json_decode($scope->division_id, true) : $scope->division_id;
                            if (is_array($ids)) {
                                $names = \App\Models\Division::whereIn('id', $ids)->pluck('name')->toArray();
                                $divisionNames = array_merge($divisionNames, $names);
                            }
                        }
                    }

                    return implode('; ', array_unique($divisionNames));
                }),
            ExportColumn::make('userScopes.region_id')
                ->label('Region')
                ->getStateUsing(function ($record) {
                    $scopes = $record->userScopes;
                    $regionNames = [];
                    foreach ($scopes as $scope) {
                        if ($scope->region_id) {
                            $ids = is_string($scope->region_id) ? json_decode($scope->region_id, true) : $scope->region_id;
                            if (is_array($ids)) {
                                $names = \App\Models\Region::whereIn('id', $ids)->pluck('name')->toArray();
                                $regionNames = array_merge($regionNames, $names);
                            }
                        }
                    }

                    return implode('; ', array_unique($regionNames));
                }),
            ExportColumn::make('userScopes.cluster_id')
                ->label('Cluster')
                ->getStateUsing(function ($record) {
                    $scopes = $record->userScopes;
                    $clusterNames = [];
                    foreach ($scopes as $scope) {
                        if ($scope->cluster_id) {
                            $ids = is_string($scope->cluster_id) ? json_decode($scope->cluster_id, true) : $scope->cluster_id;
                            if (is_array($ids)) {
                                $names = \App\Models\Cluster::whereIn('id', $ids)->pluck('name')->toArray();
                                $clusterNames = array_merge($clusterNames, $names);
                            }
                        }
                    }

                    return implode('; ', array_unique($clusterNames));
                }),
            ExportColumn::make('tm.name')
                ->label('TM'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your user export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

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

        $options->setColumnWidth(4, 1);
        $options->setColumnWidth(34, 2);
        $options->setColumnWidth(13, 3);
        $options->setColumnWidth(30, 4);
        $options->setColumnWidth(30, 5);
        $options->setColumnWidth(30, 6);
        $options->setColumnWidth(30, 7);
        $options->setColumnWidth(34, 8);

        return $options;
    }
}
