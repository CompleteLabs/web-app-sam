<?php

namespace App\Helpers;

class SendNotif
{
    public static function sendMessage($content, array $id)
    {
        // Validate configuration
        if (!config('onesignal.app_id')) {
            error_log('OneSignal App ID not configured');
            return false;
        }

        if (empty($id)) {
            error_log('No player IDs provided for notification');
            return false;
        }

        $content = [
            'en' => $content,
        ];

        $fields = [
            'app_id' => config('onesignal.app_id'),
            'include_player_ids' => $id,
            'large_icon' => config('onesignal.icons.large_icon'),
            'small_icon' => config('onesignal.icons.small_icon'),
            'contents' => $content,
        ];

        $fields = json_encode($fields);
        error_log('OneSignal notification payload: ' . $fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, config('onesignal.api_url'));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log('OneSignal cURL error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('OneSignal API error (HTTP ' . $httpCode . '): ' . $response);
            return false;
        }

        error_log('OneSignal notification sent successfully: ' . $response);
        return true;
    }
}
