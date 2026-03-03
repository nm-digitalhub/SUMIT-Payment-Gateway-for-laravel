<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Fired when a payable has been paid and fulfillment may be required.
 *
 * The package does not dispatch host jobs. Listen to this event in the host
 * to run your own fulfillment (e.g. ProcessPaidOrderJob, provisioning).
 *
 * @see PHASE4.md
 */
class PayablePaid
{
    public function __construct(
        public OfficeGuyTransaction $transaction,
        public Payable $payable
    ) {}
}
