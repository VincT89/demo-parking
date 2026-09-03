<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Parking Platforms Integration
    |--------------------------------------------------------------------------
    */

    'parkos' => [
        'enabled' => env('PARKOS_ENABLED', false),
        'fixture_mode' => env('PARKOS_FIXTURE_MODE', false),

        'base_url' => env('PARKOS_BASE_URL', 'https://api.parkos.com'),
        'auth_path' => env('PARKOS_AUTH_PATH', '/oauth/token'),
        'reservations_path' => env('PARKOS_RESERVATIONS_PATH', '/v1/reservations'),

        'username' => env('PARKOS_USERNAME'),
        'password' => env('PARKOS_PASSWORD'),
        'client_id' => env('PARKOS_CLIENT_ID'),
        'client_secret' => env('PARKOS_CLIENT_SECRET'),

        'timeout' => env('PARKOS_TIMEOUT', 20),
        'token_cache_key' => env('PARKOS_TOKEN_CACHE_KEY', 'parkos_access_token'),
        'token_cache_ttl' => env('PARKOS_TOKEN_CACHE_TTL', 3300),

        'sync_lookback_hours' => env('PARKOS_SYNC_LOOKBACK_HOURS', 72),
        'location_id' => env('PARKOS_LOCATION_ID', '1895'),
        'parking_id' => env('PARKOS_PARKING_ID', 1),
        'fixture_file' => env('PARKOS_FIXTURE_FILE', 'reservations_success.json'),
    ],

    'my_parking' => [
        'enabled' => env('MY_PARKING_ENABLED', false),
        'base_url' => env('MY_PARKING_BASE_URL', 'https://api.myparking.it/v1'),
        'api_key' => env('MY_PARKING_API_KEY'),
        'timeout' => env('MY_PARKING_TIMEOUT', 15),
        'fixture_mode' => env('MY_PARKING_FIXTURE_MODE', false),
        'fixture_file' => env('MY_PARKING_FIXTURE_FILE', 'reservations_success.json'),
    ],

    'vologio' => [
        'enabled' => env('VOLOGIO_ENABLED', false),
        'base_url' => env('VOLOGIO_BASE_URL', 'https://api.backend.staging.roosh.online/provider/v1'),
        'api_key' => env('VOLOGIO_API_KEY'),
        'client_id' => env('VOLOGIO_CLIENT_ID'),
        'timeout' => env('VOLOGIO_TIMEOUT', 20),
        'sync_lookback_hours' => env('VOLOGIO_SYNC_LOOKBACK_HOURS', 72),
    ],

    'parking_my_car' => [
        'enabled' => env('PARKING_MY_CAR_ENABLED', false),
        'base_url' => env('PARKING_MY_CAR_BASE_URL', 'https://api.parkingmycar.it'),

        'client_id' => env('PARKING_MY_CAR_CLIENT_ID'),
        'client_secret' => env('PARKING_MY_CAR_CLIENT_SECRET'),
        'username' => env('PARKING_MY_CAR_USERNAME'),
        'password' => env('PARKING_MY_CAR_PASSWORD'),

        'timeout' => env('PARKING_MY_CAR_TIMEOUT', 20),
        'auth_path' => env('PARKING_MY_CAR_AUTH_PATH', '/oauth/token'),
        'refresh_path' => env('PARKING_MY_CAR_REFRESH_PATH', '/oauth/token'),
        'resources_path' => env('PARKING_MY_CAR_RESOURCES_PATH', '/pmc_rest/parkings_resource'),
        'reservations_path' => env('PARKING_MY_CAR_RESERVATIONS_PATH', '/pmc_rest/bookings_resource'),
        'reservations_update_path' => env('PARKING_MY_CAR_RESERVATIONS_UPDATE_PATH', '/pmc_rest/bookings_resource_updated'),

        'token_cache_key' => env('PARKING_MY_CAR_TOKEN_CACHE_KEY', 'parking_my_car_access_token'),
        'refresh_token_cache_key' => env('PARKING_MY_CAR_REFRESH_TOKEN_CACHE_KEY', 'parking_my_car_refresh_token'),
        'token_cache_ttl' => env('PARKING_MY_CAR_TOKEN_CACHE_TTL', 3300),

        'default_product_ref' => env('PARKING_MY_CAR_DEFAULT_PRODUCT_REF', 'DEFAULT'),

        'sync_lookback_hours' => env('PARKING_MY_CAR_SYNC_LOOKBACK_HOURS', 72),
        'operational_past_days' => env('PARKING_MY_CAR_OPERATIONAL_PAST_DAYS', 1),
        'operational_future_days' => env('PARKING_MY_CAR_OPERATIONAL_FUTURE_DAYS', 60),
        
        'debug_sync' => env('PARKING_MY_CAR_SYNC_DEBUG', false),
    ],

];
