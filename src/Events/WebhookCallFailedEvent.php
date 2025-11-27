<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Webhook Call Failed Event
 *
 * Fired when a webhook request fails (non-2xx or exception).
 * This event fires on EVERY failed attempt, not just the final one.
 */
class WebhookCallFailedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $uuid,
        public readonly string $event,
        public readonly string $url,
        public readonly array $payload,
        public readonly array $headers,
        public readonly int $attempt,
        public readonly int|null $statusCode,
        public readonly string $errorMessage,
        public readonly array $meta = []
    ) {
    }
}
