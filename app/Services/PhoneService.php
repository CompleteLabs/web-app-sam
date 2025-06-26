<?php

namespace App\Services;

class PhoneService
{
    /**
     * Normalize phone number to 62xxxxxxxxx format
     */
    public static function normalize(string $phone): string
    {
        // Remove all spaces and special characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Convert different formats to 62xxxxxxxxx
        if (strpos($phone, '+62') === 0) {
            return preg_replace('/^\+62/', '62', $phone);
        } elseif (strpos($phone, '08') === 0) {
            return preg_replace('/^08/', '628', $phone);
        } elseif (strpos($phone, '62') === 0) {
            return $phone;
        }

        // If no recognized format, assume it starts with 8 and add 62
        if (strpos($phone, '8') === 0) {
            return '62'.$phone;
        }

        return $phone;
    }

    /**
     * Validate Indonesian phone number format
     */
    public static function isValid(string $phone): bool
    {
        $normalizedPhone = self::normalize($phone);

        // Indonesian phone numbers should start with 628 and have 10-13 digits total
        return preg_match('/^628\d{8,10}$/', $normalizedPhone);
    }

    /**
     * Format phone number for display (with +62 prefix)
     */
    public static function formatForDisplay(string $phone): string
    {
        $normalized = self::normalize($phone);

        if (strpos($normalized, '62') === 0) {
            return '+'.$normalized;
        }

        return $phone;
    }
}
