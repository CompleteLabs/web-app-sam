<?php

namespace App\Filament\Resources\DivisionResource\Pages;

use App\Filament\Resources\DivisionResource;
use App\Models\Division;
use App\Models\BadanUsaha;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ManageDivisions extends ManageRecords
{
    protected static string $resource = DivisionResource::class;

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
                        // Panggil API untuk mendapatkan data Division
                        $response = Http::timeout(30)->get('https://grosir.mediaselularindonesia.com/api/sync/division');

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

                        foreach ($apiData as $item) {
                            // Validasi data item
                            if (empty($item['id']) || empty($item['name']) || empty($item['badanusaha_id'])) {
                                $skippedCount++;
                                continue; // Skip item tanpa id, nama, atau badan_usaha_id
                            }

                            // Cek apakah BadanUsaha exists berdasarkan id dari API
                            $badanUsaha = BadanUsaha::find($item['badanusaha_id']);
                            if (!$badanUsaha) {
                                $skippedCount++;
                                continue; // Skip jika BadanUsaha tidak ditemukan
                            }

                            // Cek apakah record sudah ada berdasarkan id
                            $existingRecord = Division::where('id', $item['id'])->first();

                            if ($existingRecord) {
                                // Update record yang sudah ada
                                $updateData = [
                                    'name' => $item['name'],
                                    'badan_usaha_id' => $item['badanusaha_id'],
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
                                // Buat record baru dengan id dari API
                                $createData = [
                                    'id' => $item['id'],
                                    'name' => $item['name'],
                                    'badan_usaha_id' => $item['badanusaha_id'],
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

                                $result = Division::create($createData);
                                $result->wasRecentlyCreated = true;
                            }

                            if ($result->wasRecentlyCreated) {
                                $newCount++;
                            } else {
                                $updatedCount++;
                            }
                            $syncedCount++;
                        }

                        // Tampilkan notifikasi sukses
                        $message = "Berhasil sync {$syncedCount} data Division. Baru: {$newCount}, Diperbarui: {$updatedCount}";
                        if ($skippedCount > 0) {
                            $message .= ", Dilewati: {$skippedCount}";
                        }

                        Notification::make()
                            ->title('Sync Data Berhasil')
                            ->body($message)
                            ->success()
                            ->send();

                        // Refresh halaman untuk menampilkan data terbaru
                        $this->redirect(request()->header('Referer'));

                    } catch (\Exception $e) {
                        Log::error('Error syncing Division: ' . $e->getMessage());

                        Notification::make()
                            ->title('Gagal Sync Data')
                            ->body('Terjadi kesalahan: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sync Data')
                ->modalDescription('Apakah Anda yakin ingin melakukan sinkronisasi data Division dari API? Proses ini akan menambah atau memperbarui data yang sudah ada. Data yang tidak memiliki Badan Usaha yang valid akan dilewati.')
                ->modalSubmitActionLabel('Ya, Sync Data'),
        ];
    }
}
