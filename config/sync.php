<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sync API URLs
    |--------------------------------------------------------------------------
    |
    | API URLs for syncing different entity types from external source
    |
    */
    'api_urls' => [
        'users' => env('EXTERNAL_API_BASE_URL') . '/sync/user',
        'outlets' => env('EXTERNAL_API_BASE_URL') . '/sync/outlet',
        'visits' => env('EXTERNAL_API_BASE_URL') . '/sync/visit',
        'planvisits' => env('EXTERNAL_API_BASE_URL') . '/sync/planvisit',
        'roles' => env('EXTERNAL_API_BASE_URL') . '/sync/role',
        'badanusahas' => env('EXTERNAL_API_BASE_URL') . '/sync/badanusaha',
        'divisions' => env('EXTERNAL_API_BASE_URL') . '/sync/division',
        'regions' => env('EXTERNAL_API_BASE_URL') . '/sync/region',
        'clusters' => env('EXTERNAL_API_BASE_URL') . '/sync/cluster',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for sync batch processing
    |
    */
    'default_batch_size' => 100,
    'max_batch_size' => 500,
    'timeout' => 600, // 10 minutes
    'max_retries' => 3,
];
