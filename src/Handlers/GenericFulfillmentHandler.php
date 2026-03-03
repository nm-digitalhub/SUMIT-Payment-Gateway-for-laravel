<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Handlers;

use OfficeGuy\LaravelSumitGateway\Events\PayablePaid;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Generic Fulfillment Handler (Safety Net)
 *
 * Handles all payables that don't match specific PayableTypes.
 * Dispatches PayablePaid event; host listens to run fulfillment (e.g. jobs).
 *
 * @see PayablePaid
 * @see PHASE4.md
 */
class GenericFulfillmentHandler
{
    public function handle(OfficeGuyTransaction $transaction): void
    {
        OfficeGuyApi::writeToLog(
            "GenericFulfillmentHandler: Processing transaction {$transaction->id} (FALLBACK HANDLER)",
            'warning'
        );

        $payable = $transaction->payable;

        if (! $payable) {
            OfficeGuyApi::writeToLog(
                "GenericFulfillmentHandler: No payable found for transaction {$transaction->id}",
                'error'
            );

            return;
        }

        event(new PayablePaid($transaction, $payable));
    }
}
