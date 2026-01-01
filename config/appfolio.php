<?php

/**
 * AppFolio API Configuration
 *
 * This configuration file contains all settings related to the AppFolio API
 * integration including credentials, sync schedules, and rate limiting.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | AppFolio API Credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are used to authenticate with the AppFolio API.
    | For production, these should be stored encrypted in the database
    | via the appfolio_connections table.
    |
    */

    'client_id' => env('APPFOLIO_CLIENT_ID'),
    'client_secret' => env('APPFOLIO_CLIENT_SECRET'),
    'api_base_url' => env('APPFOLIO_API_BASE_URL', 'https://api.appfolio.com'),

    /*
    |--------------------------------------------------------------------------
    | Sync Schedule Configuration
    |--------------------------------------------------------------------------
    |
    | Configure when full and incremental syncs should run.
    | Full sync runs once daily, incremental runs at the specified interval.
    |
    */

    'full_sync_time' => env('APPFOLIO_FULL_SYNC_TIME', '02:00'),
    'incremental_sync_interval' => (int) env('APPFOLIO_INCREMENTAL_SYNC_INTERVAL', 15),

    /*
    |--------------------------------------------------------------------------
    | Business Hours Configuration
    |--------------------------------------------------------------------------
    |
    | Configure business hours for sync frequency optimization.
    | During business hours, sync runs every 15 minutes.
    | Outside business hours, sync runs hourly to conserve API resources.
    |
    */

    'business_hours' => [
        'enabled' => env('APPFOLIO_BUSINESS_HOURS_ENABLED', true),
        'timezone' => env('APPFOLIO_BUSINESS_HOURS_TIMEZONE', 'America/Los_Angeles'),
        'start_hour' => (int) env('APPFOLIO_BUSINESS_HOURS_START', 9),
        'end_hour' => (int) env('APPFOLIO_BUSINESS_HOURS_END', 17),
        'weekdays_only' => env('APPFOLIO_BUSINESS_HOURS_WEEKDAYS_ONLY', true),
        'business_hours_interval' => (int) env('APPFOLIO_BUSINESS_HOURS_INTERVAL', 15),
        'off_hours_interval' => (int) env('APPFOLIO_OFF_HOURS_INTERVAL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for API requests. The backoff multiplier
    | is used for exponential backoff on rate limit errors.
    |
    */

    'rate_limit' => [
        'requests_per_minute' => 60,
        'max_retries' => 5,
        'initial_backoff_seconds' => 1,
        'backoff_multiplier' => 2,
        'max_backoff_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Batch Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how many records to process in each batch and how many
    | days back to look for incremental syncs.
    |
    */

    'sync' => [
        'batch_size' => 100,
        'incremental_days' => 7,
        'full_sync_lookback_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Types
    |--------------------------------------------------------------------------
    |
    | Define which resource types to sync from AppFolio.
    | Each resource type maps to a specific API endpoint.
    |
    */

    'resources' => [
        'properties',
        'units',
        'people',
        'leases',
        'ledger_transactions',
        'work_orders',
    ],

];
