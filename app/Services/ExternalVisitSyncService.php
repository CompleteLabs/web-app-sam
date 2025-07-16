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
            'tipe_visit' => $visit->type, // Send original value: EXTRACALL or PLANNED
            'latlong_in' => $visit->checkin_location,
            'latlong_out' => $visit->checkout_location,
            'check_in_time' => $visit->checkin_time ? $visit->checkin_time->format('Y-m-d H:i:s') : null,
            'check_out_time' => $visit->checkout_time ? $visit->checkout_time->format('Y-m-d H:i:s') : null,
            'laporan_visit' => $visit->report,
            'transaksi' => $visit->transaction, // Send original value: YES or NO
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
                Log::info('Checkin photo added to multipart', [
                    'original_path' => $data['checkin_photo'],
                    'resolved_path' => $checkinPhotoPath,
                    'filename' => basename($data['checkin_photo']),
                    'mime_type' => Storage::mimeType($checkinPhotoPath)
                ]);
            } else {
                Log::error('Checkin photo is required but not found', [
                    'photo_path' => $data['checkin_photo']
                ]);
                throw new \Exception("Checkin photo is required but file not found: {$data['checkin_photo']}");
            }
        } else {
            Log::error('Checkin photo is required but not provided');
            throw new \Exception("Checkin photo is required but not provided");
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
                Log::info('Checkout photo added to multipart', [
                    'original_path' => $data['checkout_photo'],
                    'resolved_path' => $checkoutPhotoPath,
                    'filename' => basename($data['checkout_photo']),
                    'mime_type' => Storage::mimeType($checkoutPhotoPath)
                ]);
            } else {
                Log::error('Checkout photo is required but not found', [
                    'photo_path' => $data['checkout_photo']
                ]);
                throw new \Exception("Checkout photo is required but file not found: {$data['checkout_photo']}");
            }
        } else {
            Log::error('Checkout photo is required but not provided');
            throw new \Exception("Checkout photo is required but not provided");
        }

        $response = Http::timeout($this->timeout)
            ->asMultipart()
            ->post($url, $multipart);

        Log::info('External API request sent', [
            'url' => $url,
            'form_fields' => collect($multipart)->whereNotIn('name', ['picture_visit_in', 'picture_visit_out'])->toArray(),
            'has_checkin_photo' => collect($multipart)->where('name', 'picture_visit_in')->isNotEmpty(),
            'has_checkout_photo' => collect($multipart)->where('name', 'picture_visit_out')->isNotEmpty(),
            'checkin_photo_size' => collect($multipart)->where('name', 'picture_visit_in')->first()['contents'] ? strlen(collect($multipart)->where('name', 'picture_visit_in')->first()['contents']) : 0,
            'checkout_photo_size' => collect($multipart)->where('name', 'picture_visit_out')->first()['contents'] ? strlen(collect($multipart)->where('name', 'picture_visit_out')->first()['contents']) : 0,
            'response_status' => $response->status()
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to post visit to external API. Status: {$response->status()}, Body: {$response->body()}");
        }

        return $response;
    }

    /**
     * Get photo path from storage
     *
     * @param string $photoPath
     * @return string|null
     */
    private function getPhotoPath(string $photoPath): ?string
    {
        if (empty($photoPath)) {
            return null;
        }

        // Remove leading slash if present
        $photoPath = ltrim($photoPath, '/');

        // If path starts with 'storage/', it's a public URL path
        // Convert /storage/filename to public/filename for Storage facade
        if (str_starts_with($photoPath, 'storage/')) {
            $photoPath = str_replace('storage/', 'public/', $photoPath);
        } else {
            // If it doesn't start with storage/, assume it's already a storage path
            // Try to find it in public storage first
            $photoPath = 'public/' . $photoPath;
        }

        // Check if file exists in the resolved path
        if (Storage::exists($photoPath)) {
            Log::info('Photo found in storage', [
                'original_path' => func_get_arg(0),
                'resolved_path' => $photoPath
            ]);
            return $photoPath;
        }

        Log::warning('Photo file not found in storage', [
            'photo_path' => func_get_arg(0),
            'resolved_path' => $photoPath
        ]);

        return null;
    }

}
