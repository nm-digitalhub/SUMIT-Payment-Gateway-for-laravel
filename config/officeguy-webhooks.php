<?php

/**
 * Webhook Server Configuration
 *
 * Configuration values follow 3-layer priority system:
 * 1. Database (officeguy_settings table) - HIGHEST PRIORITY
 * 2. This config file
 * 3. .env variables - FALLBACK ONLY
 *
 * The database values are loaded by OfficeGuyServiceProvider::loadDatabaseSettings()
 * which runs during boot and overrides these config values.
 *
 * To configure via Admin Panel:
 * Navigate to /admin/office-guy-settings → "Webhook System Configuration (v1.2.0+)"
 */
return [
    /**
     * Send webhooks asynchronously via queue (recommended).
     *
     * Set to false for synchronous/immediate execution (legacy mode).
     * Database key: webhook_async
     */
    'async' => env('WEBHOOK_ASYNC', true),

    /**
     * Default queue to dispatch webhook jobs to.
     *
     * Database key: webhook_queue
     */
    'queue' => env('WEBHOOK_QUEUE', 'default'),

    /**
     * Maximum number of retry attempts for failed webhooks.
     *
     * Database key: webhook_max_tries
     */
    'tries' => env('WEBHOOK_MAX_TRIES', 3),

    /**
     * Timeout in seconds for HTTP requests.
     *
     * Database key: webhook_timeout
     */
    'timeout_in_seconds' => env('WEBHOOK_TIMEOUT', 30),

    /**
     * Backoff strategy class for retry delays.
     *
     * Default: ExponentialBackoffStrategy
     * - Attempt 1: 10 seconds
     * - Attempt 2: 100 seconds
     * - Attempt 3: 1,000 seconds
     */
    'backoff_strategy' => \OfficeGuy\LaravelSumitGateway\BackoffStrategy\ExponentialBackoffStrategy::class,

    /**
     * Verify SSL certificates when sending webhooks.
     *
     * Database key: webhook_verify_ssl
     */
    'verify_ssl' => env('WEBHOOK_VERIFY_SSL', true),
];
