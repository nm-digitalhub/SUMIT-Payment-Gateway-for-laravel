<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class MultiVendorPaymentFailed
{
    public function __construct(
        public string | int $orderId,
        public array $vendorResults
    ) {}
}
