<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class PaymentFailed
{
    public function __construct(
        public string|int $orderId,
        public ?array $response,
        public string $message
    ) {}
}
