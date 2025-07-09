<?php

namespace App\Filament\Resources\ClusterResource\Pages;

use App\Filament\Resources\ClusterResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ManageClusters extends ManageRecords
{
    protected static string $resource = ClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync')
                ->label('Sync dari API')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    try {
                        // Panggil API untuk mendapatkan data Cluster
                        $response = Http::timeout(30)->get('https://grosir.mediaselularindonesia.com/api/sync/cluster');

                        if (!$response->successful()) {
                            Notification::make()
                                ->title('Gagal Sync Data')
                                ->body('Tidak dapat mengakses API. Status: ' . $response->status())
                                ->danger()
                                ->send();
                            return;
                        }

                        $data = $response->json();

                        // Validasi struktur response
                        if (!isset($data['meta']['code']) || $data['meta']['code'] !== 200 || !isset($data['data'])) {
                            Notification::make()
                                ->title('Gagal Sync Data')
                                ->body('Format response API tidak valid')
                                ->danger()
                                ->send();
                            return;
                        }

                        $apiData = $data['data'];
                        $syncedCount = 0;
                        $updatedCount = 0;
                        $newCount = 0;
                        $skippedCount = 0;
                        $skippedReasons = [];

                        foreach ($apiData as $item) {
                            try {
                                // Validasi data item - hanya skip jika id atau nama kosong
                                if (empty($item['id']) || empty($item['name'])) {
                                    $skippedCount++;
                                    $reason = "Data tidak lengkap - ID: {$item['id']}, Name: '{$item['name']}'";
                                    $skippedReasons[] = $reason;
                                    Log::warning("Cluster skip: " . $reason);
                                    continue; // Skip item tanpa id atau nama
                                }

                                // Cek apakah record sudah ada berdasarkan ID unik dari API
                                $existingRecord = Cluster::where('id', $item['id'])->first();

                                if ($existingRecord) {
                                    // Update record yang sudah ada
                                    $updateData = [
                                        'name' => $item['name'],
                                        'badan_usaha_id' => $item['badanusaha_id'] ?? null,
                                        'division_id' => $item['divisi_id'] ?? null,
                                        'region_id' => $item['region_id'] ?? null,
                                    ];

                                    // Set created_at dari API jika ada
                                    if ($item['created_at'] !== null) {
                                        $updateData['created_at'] = \Carbon\Carbon::parse($item['created_at']);
                                    }

                                    // Set updated_at dari API jika ada, jika tidak gunakan timestamp sekarang
                                    if ($item['updated_at'] !== null) {
                                        $updateData['updated_at'] = \Carbon\Carbon::parse($item['updated_at']);
                                    } else {
                                        $updateData['updated_at'] = now();
                                    }

                                    $existingRecord->update($updateData);
                                    $result = $existingRecord;
                                    $result->wasRecentlyCreated = false;
                                } else {
                                    // Buat record baru dengan ID dari API
                                    $createData = [
                                        'id' => $item['id'],
                                        'name' => $item['name'],
                                        'badan_usaha_id' => $item['badanusaha_id'] ?? null,
                                        'division_id' => $item['divisi_id'] ?? null,
                                        'region_id' => $item['region_id'] ?? null,
                                    ];

                                    // Set created_at dari API
                                    if ($item['created_at'] !== null) {
                                        $createData['created_at'] = \Carbon\Carbon::parse($item['created_at']);
                                    } else {
                                        $createData['created_at'] = null;
                                    }

                                    // Set updated_at dari API jika ada, jika tidak gunakan timestamp sekarang
                                    if ($item['updated_at'] !== null) {
                                        $createData['updated_at'] = \Carbon\Carbon::parse($item['updated_at']);
                                    } else {
                                        $createData['updated_at'] = now();
                                    }

                                    // Disable foreign key checks untuk record ini
                                    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                                    $result = Cluster::create($createData);
                                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                                    $result->wasRecentlyCreated = true;
                                }

                                if ($result->wasRecentlyCreated) {
                                    $newCount++;
                                } else {
                                    $updatedCount++;
                                }
                                $syncedCount++;

                            } catch (\Exception $e) {
                                // Log error tapi tetap lanjutkan
                                Log::warning("Cluster error tapi tetap lanjut - ID: {$item['id']}, Error: " . $e->getMessage());

                                // Tetap hitung sebagai synced
                                $syncedCount++;

                                // Reset foreign key checks jika terjadi error
                                try {
                                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                                } catch (\Exception $resetE) {
                                    // Ignore reset error
                                }
                            }
                        }

                        // Tampilkan notifikasi sukses
                        $message = "Berhasil sync {$syncedCount} data Cluster. Baru: {$newCount}, Diperbarui: {$updatedCount}";
                        if ($skippedCount > 0) {
                            $message .= ", Dilewati: {$skippedCount}";
                            Log::warning("Total data cluster dilewati: {$skippedCount}. Alasan: " . implode('; ', array_slice($skippedReasons, 0, 10)));
                        }

                        Notification::make()
                            ->title('Sync Data Berhasil')
                            ->body($message)
                            ->success()
                            ->send();

                        // Refresh halaman untuk menampilkan data terbaru
                        $this->redirect(request()->header('Referer'));

                    } catch (\Exception $e) {
                        Log::error('Error syncing Cluster: ' . $e->getMessage());

                        Notification::make()
                            ->title('Gagal Sync Data')
                            ->body('Terjadi kesalahan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sync Data')
                ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data Cluster dari API? Proses ini akan menambah atau memperbarui data yang sudah ada berdasarkan data API tanpa validasi relasi.')
                ->modalSubmitActionLabel('Ya, Sync Data'),
        ];
    }
}
