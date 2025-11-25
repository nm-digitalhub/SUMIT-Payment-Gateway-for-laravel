<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Models\Subscription;

class SubscriptionCancelled
{
    public function __construct(
        public Subscription $subscription,
        public ?string $reason = null
    ) {}
}
