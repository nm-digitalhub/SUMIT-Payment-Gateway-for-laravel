<?php

return [
    /**
     * Send webhooks asynchronously via queue (recommended).
     *
     * Set to false for synchronous/immediate execution (legacy mode).
     */
    'async' => env('WEBHOOK_ASYNC', true),

    /**
     * Default queue to dispatch webhook jobs to.
     */
    'queue' => env('WEBHOOK_QUEUE', 'default'),

    /**
     * Maximum number of retry attempts for failed webhooks.
     */
    'tries' => env('WEBHOOK_MAX_TRIES', 3),

    /**
     * Timeout in seconds for HTTP requests.
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
     */
    'verify_ssl' => env('WEBHOOK_VERIFY_SSL', true),
];
