<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send WhatsApp message
     */
    public static function sendMessage(string $phone, string $message): bool
    {
        try {
            $config = config('business.whatsapp');

            if (! $config['api_endpoint'] || ! $config['api_key'] || ! $config['sender_number']) {
                Log::error('WhatsApp configuration tidak lengkap');

                return false;
            }

            $client = new Client;
            $response = $client->post($config['api_endpoint'], [
                'query' => [
                    'apikey' => $config['api_key'],
                    'sender' => $config['sender_number'],
                    'receiver' => $phone,
                    'message' => $message,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error('Gagal mengirim pesan WhatsApp: '.$response->getBody());

                return false;
            }

            return true;
        } catch (Exception $e) {
            Log::error('Error WhatsApp Service: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send user credentials via WhatsApp
     */
    public static function sendUserCredentials(string $phone, string $username, string $password): bool
    {
        $message = "Akun SAM Anda berhasil dibuat.\n\n"
                 ."Username: *{$username}*\n"
                 ."Password: *{$password}*\n\n"
                 .'Silakan login ke aplikasi. Jaga kerahasiaan password Anda dan segera ganti password setelah login.';

        return self::sendMessage($phone, $message);
    }

    /**
     * Send password reset via WhatsApp
     */
    public static function sendPasswordReset(string $phone, string $username, string $newPassword): bool
    {
        $message = "Password SAM Anda telah direset.\n\n"
                 ."Username: *{$username}*\n"
                 ."Password Baru: *{$newPassword}*\n\n"
                 .'Silakan login dengan password baru. Jaga kerahasiaan password Anda.';

        return self::sendMessage($phone, $message);
    }

    /**
     * Send general notification via WhatsApp
     */
    public static function sendNotification(string $phone, string $title, string $content): bool
    {
        $message = "*{$title}*\n\n{$content}";

        return self::sendMessage($phone, $message);
    }
}
