<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Handlers;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Generic Fulfillment Handler (Safety Net)
 *
 * Handles all orders that don't match specific PayableTypes.
 * Acts as a fallback to ensure NO orders are missed.
 *
 * Dispatches to app-specific ProcessPaidOrderJob for:
 * - Unknown service types
 * - Custom products
 * - Future expansions
 */
class GenericFulfillmentHandler
{
    /**
     * Handle generic fulfillment
     *
     * @param OfficeGuyTransaction $transaction
     * @return void
     */
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

        // Dispatch to application's provisioning job
        if ($payable instanceof \App\Models\Order) {
            \App\Jobs\ProcessPaidOrderJob::dispatch($payable->id);

            OfficeGuyApi::writeToLog(
                "GenericFulfillmentHandler: Dispatched ProcessPaidOrderJob for order {$payable->id} (service_type: {$payable->service_type->value})",
                'info'
            );

            // Alert: This means we're using fallback - might need specific handler
            OfficeGuyApi::writeToLog(
                "GenericFulfillmentHandler: Consider creating specific handler for service_type '{$payable->service_type->value}'",
                'notice'
            );
        } else {
            OfficeGuyApi::writeToLog(
                "GenericFulfillmentHandler: Payable is not an Order instance (type: " . get_class($payable) . ")",
                'warning'
            );
        }
    }
}
