<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OneSignal Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for OneSignal push notification service.
    |
    */

    'app_id' => env('ONESIGNAL_APP_ID'),

    'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),

    'user_auth_key' => env('ONESIGNAL_USER_AUTH_KEY'),

    'api_url' => 'https://onesignal.com/api/v1/notifications',

    'icons' => [
        'large_icon' => '@drawable/msilogo',
        'small_icon' => '@drawable/msilogo',
    ],

];
