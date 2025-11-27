<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Final Webhook Call Failed Event
 *
 * Fired when all retry attempts are exhausted and the webhook has permanently failed.
 * Use this event to implement custom error handling, alerting, or logging.
 */
class FinalWebhookCallFailedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $uuid,
        public readonly string $event,
        public readonly string $url,
        public readonly array $payload,
        public readonly array $headers,
        public readonly int $totalAttempts,
        public readonly string $errorMessage,
        public readonly array $meta = []
    ) {
    }
}
