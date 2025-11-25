<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Models\Subscription;

class SubscriptionCreated
{
    public function __construct(
        public Subscription $subscription
    ) {}
}
