<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Business Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains business-specific configuration values that are used
    | throughout the application.
    |
    */

    // App version requirements
    'app_version' => env('APP_VERSION', '2.0.0'),
    'supported_versions' => ['2.0.0'],

    // External API Configuration
    'external_api' => [
        'login_url' => env('EXTERNAL_API_LOGIN_URL', 'https://grosir.mediaselularindonesia.com/api/user/login'),
        'timeout' => env('EXTERNAL_API_TIMEOUT', 10),
    ],

    // Pagination defaults
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OTP generation, validation, and security
    |
    */
    'otp' => [
        'length' => 4,                          // OTP length in digits
        'expiry_minutes' => 5,                  // OTP expiry time in minutes

        // Per Phone Number Rate Limiting
        'max_requests_per_hour' => 5,           // Max OTP requests per phone per hour
        'max_attempts' => 5,                    // Max verification attempts per phone
        'cooldown_seconds' => 60,               // Cooldown between OTP requests (seconds)
        'lockout_minutes' => 30,                // Lockout duration after max attempts
        'resend_after_seconds' => 60,           // Minimum time before allowing resend

        // Global Rate Limiting (optional)
        'global_rate_limiting' => [
            'enabled' => false,                 // Enable/disable global rate limiting
            'max_requests_per_hour' => 50,      // Max total OTP requests per hour globally
            'max_requests_per_ip_per_hour' => 10, // Max requests per IP per hour
        ],
    ],

    // File Upload Configuration
    'upload' => [
        'path' => 'public',
        'allowed_photo_extensions' => ['jpg', 'jpeg', 'png'],
        'allowed_video_extensions' => ['mp4', 'avi', 'mov'],
        'max_photo_size' => 5120, // KB
        'max_video_size' => 51200, // KB
    ],

    // WhatsApp Configuration
    'whatsapp' => [
        'api_endpoint' => env('WHATSAPP_API_ENDPOINT'),
        'api_key' => env('WHATSAPP_API_KEY'),
        'sender_number' => env('WHATSAPP_SENDER_NUMBER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Visit Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for visit planning and execution business rules
    |
    */
    'visit' => [
        'plan_advance_days' => 3, // H-3: Plan visit minimal 3 hari sebelumnya
        'delete_advance_days' => 3, // Minimal 3 hari sebelum visit untuk delete plan
        'max_visits_per_day' => 20, // Maksimal kunjungan per hari per user
        'min_duration_minutes' => 0, // Durasi minimal kunjungan (menit)
    ],

    // User Management
    'user' => [
        'password_length' => 8, // Generated password length
        'password_chars' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', // Allowed chars
        'auto_send_credentials' => env('AUTO_SEND_CREDENTIALS', true), // Auto send via WhatsApp
    ],
];
