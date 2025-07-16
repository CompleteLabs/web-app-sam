<?php

namespace App\Services;

use App\Models\Visit;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExternalVisitSyncService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('sync.post_api_base_url');
        $this->timeout = config('sync.timeout', 30);
    }

    /**
     * Post visit data to external system
     *
     * @param Visit $visit
     * @return array
     * @throws \Exception
     */
    public function postVisit(Visit $visit): array
    {
        try {
            // Prepare the data according to the external API format
            $data = $this->prepareVisitData($visit);

            // Post to external API
            $response = $this->sendPostRequest($data);

            // Update visit record with sync status
            $visit->update([
                'external_synced' => true,
                'external_synced_at' => now(),
                'external_sync_response' => $response->json(),
                'external_sync_status' => 'success'
            ]);

            Log::info('Visit posted to external system', [
                'visit_id' => $visit->id,
                'response_status' => $response->status(),
                'response_body' => $response->json()
            ]);

            return [
                'success' => true,
                'status' => $response->status(),
                'response' => $response->json(),
                'message' => 'Visit berhasil dikirim ke sistem eksternal'
            ];

        } catch (\Exception $e) {
            // Update visit record with error status
            $visit->update([
                'external_synced' => false,
                'external_synced_at' => now(),
                'external_sync_response' => ['error' => $e->getMessage()],
                'external_sync_status' => 'failed'
            ]);

            Log::error('Failed to post visit to external system', [
                'visit_id' => $visit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Gagal mengirim visit ke sistem eksternal'
            ];
        }
    }

    /**
     * Prepare visit data for external API
     *
     * @param Visit $visit
     * @return array
     */
    private function prepareVisitData(Visit $visit): array
    {
        $data = [
            'tanggal_visit' => $visit->visit_date->format('Y-m-d'),
            'user_id' => $visit->user_id,
            'outlet_id' => $visit->outlet_id,
            'tipe_visit' => $this->mapVisitType($visit->type),
            'latlong_in' => $visit->checkin_location,
            'latlong_out' => $visit->checkout_location,
            'check_in_time' => $visit->checkin_time ? $visit->checkin_time->format('Y-m-d H:i:s') : null,
            'check_out_time' => $visit->checkout_time ? $visit->checkout_time->format('Y-m-d H:i:s') : null,
            'laporan_visit' => $visit->report,
            'transaksi' => $this->mapTransactionStatus($visit->transaction),
            'durasi_visit' => $visit->duration,
            'checkin_photo' => $visit->checkin_photo,
            'checkout_photo' => $visit->checkout_photo,
        ];

        return $data;
    }

    /**
     * Send POST request to external API
     *
     * @param array $data
     * @return Response
     * @throws \Exception
     */
    private function sendPostRequest(array $data): Response
    {
        $url = $this->baseUrl . '/sync/visit/create';

        $multipart = [];

        // Add regular form fields
        foreach ($data as $key => $value) {
            if ($value !== null && !in_array($key, ['checkin_photo', 'checkout_photo'])) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => (string) $value
                ];
            }
        }

        // Add photo files if they exist
        if (!empty($data['checkin_photo'])) {
            $checkinPhotoPath = $this->getPhotoPath($data['checkin_photo']);
            if ($checkinPhotoPath && Storage::exists($checkinPhotoPath)) {
                $multipart[] = [
                    'name' => 'picture_visit_in',
                    'contents' => Storage::get($checkinPhotoPath),
                    'filename' => basename($data['checkin_photo']),
                    'headers' => [
                        'Content-Type' => Storage::mimeType($checkinPhotoPath)
                    ]
                ];
            }
        }

        if (!empty($data['checkout_photo'])) {
            $checkoutPhotoPath = $this->getPhotoPath($data['checkout_photo']);
            if ($checkoutPhotoPath && Storage::exists($checkoutPhotoPath)) {
                $multipart[] = [
                    'name' => 'picture_visit_out',
                    'contents' => Storage::get($checkoutPhotoPath),
                    'filename' => basename($data['checkout_photo']),
                    'headers' => [
                        'Content-Type' => Storage::mimeType($checkoutPhotoPath)
                    ]
                ];
            }
        }

        $response = Http::timeout($this->timeout)
            ->asMultipart()
            ->post($url, $multipart);

        if (!$response->successful()) {
            throw new \Exception("Failed to post visit to external API. Status: {$response->status()}, Body: {$response->body()}");
        }

        return $response;
    }

    /**
     * Map visit type to external format
     *
     * @param string $type
     * @return string
     */
    private function mapVisitType(string $type): string
    {
        return match($type) {
            'PLANNED' => 'rutin',
            'EXTRACALL' => 'ekstrakol',
            default => 'rutin'
        };
    }

    /**
     * Map transaction status to external format
     *
     * @param string $transaction
     * @return string
     */
    private function mapTransactionStatus(string $transaction): string
    {
        return match($transaction) {
            'YES' => 'Ada transaksi',
            'NO' => 'Tidak ada transaksi',
            default => 'Tidak ada transaksi'
        };
    }

    /**
     * Get photo path from storage
     *
     * @param string $photoPath
     * @return string|null
     */
    private function getPhotoPath(string $photoPath): ?string
    {
        // If it's already a full path, return as is
        if (Storage::exists($photoPath)) {
            return $photoPath;
        }

        // Try with public prefix
        $publicPath = 'public/' . $photoPath;
        if (Storage::exists($publicPath)) {
            return $publicPath;
        }

        return null;
    }
}
