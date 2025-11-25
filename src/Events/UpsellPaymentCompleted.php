<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class UpsellPaymentCompleted
{
    public function __construct(
        public string|int $upsellOrderId,
        public string|int|null $parentOrderId,
        public array $payment,
        public array $response
    ) {}
}
