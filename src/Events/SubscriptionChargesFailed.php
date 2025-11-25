<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Models\Subscription;

class SubscriptionChargesFailed
{
    public function __construct(
        public Subscription $subscription,
        public string $errorMessage
    ) {}
}
