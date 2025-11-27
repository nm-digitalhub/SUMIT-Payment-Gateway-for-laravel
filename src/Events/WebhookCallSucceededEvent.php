<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Webhook Call Succeeded Event
 *
 * Fired when a webhook request receives a 2xx response.
 */
class WebhookCallSucceededEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $uuid,
        public readonly string $event,
        public readonly string $url,
        public readonly array $payload,
        public readonly array $headers,
        public readonly int $attempt,
        public readonly int $statusCode,
        public readonly string $responseBody,
        public readonly array $meta = []
    ) {
    }
}
