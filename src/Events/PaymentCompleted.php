<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class PaymentCompleted
{
    public function __construct(
        public string|int $orderId,
        public array $payment,
        public array $response
    ) {}
}
