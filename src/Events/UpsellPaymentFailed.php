<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class UpsellPaymentFailed
{
    public function __construct(
        public string|int $upsellOrderId,
        public string|int|null $parentOrderId,
        public string $errorMessage
    ) {}
}
