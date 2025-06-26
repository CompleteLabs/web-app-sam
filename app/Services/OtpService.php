<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Generate OTP code
     */
    public static function generate(): string
    {
        $length = config('business.otp.length');
        $min = str_repeat('1', $length);
        $max = str_repeat('9', $length);

        return (string) rand($min, $max);
    }

    /**
     * Store OTP in cache
     */
    public static function store(string $phone, string $otp): void
    {
        $cacheKey = config('business.otp.cache_prefix').$phone;
        $expiryMinutes = config('business.otp.expiry_minutes');

        Cache::put($cacheKey, $otp, now()->addMinutes($expiryMinutes));
    }

    /**
     * Verify OTP
     */
    public static function verify(string $phone, string $otp): bool
    {
        $cacheKey = config('business.otp.cache_prefix').$phone;
        $cachedOtp = Cache::get($cacheKey);

        return $cachedOtp && $cachedOtp === $otp;
    }

    /**
     * Clear OTP from cache
     */
    public static function clear(string $phone): void
    {
        $cacheKey = config('business.otp.cache_prefix').$phone;
        Cache::forget($cacheKey);
    }

    /**
     * Send OTP via WhatsApp
     *
     * @throws Exception
     */
    public static function sendViaWhatsApp(string $phone, string $otp): bool
    {
        $message = "*$otp* adalah kode verifikasi Anda. Demi keamanan akun Anda, jangan berikan kode ini kepada siapapun.";

        $client = new Client;
        $config = config('business.whatsapp');

        try {
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
            Log::error('Error WhatsApp OTP: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate and send OTP
     *
     * @return string The generated OTP
     *
     * @throws Exception
     */
    public static function generateAndSend(string $phone): string
    {
        $otp = self::generate();
        self::store($phone, $otp);

        if (! self::sendViaWhatsApp($phone, $otp)) {
            throw new Exception('Gagal mengirim OTP');
        }

        return $otp;
    }
}
