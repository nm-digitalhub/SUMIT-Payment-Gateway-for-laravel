<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Services\FulfillmentDispatcher;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Fulfillment Listener
 *
 * Triggered when PaymentCompleted event is fired.
 * Dispatches to appropriate fulfillment handler based on PayableType.
 *
 * Architecture: Container-Driven Fulfillment Pattern
 * - Event: PaymentCompleted (contains transaction + payable)
 * - Listener: FulfillmentListener (this class)
 * - Service: FulfillmentDispatcher (resolves Type → Handler)
 * - Handlers: Infrastructure/Digital/Subscription (execute fulfillment logic)
 *
 * @see docs/ARCHITECTURE_DECISION_FULFILLMENT_PATTERN.md
 */
class FulfillmentListener
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected FulfillmentDispatcher $dispatcher
    ) {}

    /**
     * Handle the event.
     *
     * @param PaymentCompleted $event
     * @return void
     */
    public function handle(PaymentCompleted $event): void
    {
        OfficeGuyApi::writeToLog(
            "FulfillmentListener: Payment completed for transaction {$event->transaction?->id}",
            'info'
        );

        // Ensure we have required data
        if (! $event->transaction) {
            OfficeGuyApi::writeToLog(
                'FulfillmentListener: No transaction in PaymentCompleted event - skipping fulfillment',
                'warning'
            );
            return;
        }

        if (! $event->payable) {
            OfficeGuyApi::writeToLog(
                "FulfillmentListener: No payable in PaymentCompleted event for transaction {$event->transaction->id} - skipping fulfillment",
                'warning'
            );
            return;
        }

        // Ensure payable implements Payable contract
        if (! $event->payable instanceof Payable) {
            OfficeGuyApi::writeToLog(
                "FulfillmentListener: Payable does not implement Payable contract for transaction {$event->transaction->id} - skipping fulfillment",
                'warning'
            );
            return;
        }

        // Dispatch to fulfillment handler
        try {
            $this->dispatcher->dispatch($event->payable, $event->transaction);

            OfficeGuyApi::writeToLog(
                "FulfillmentListener: Successfully dispatched fulfillment for transaction {$event->transaction->id}",
                'info'
            );
        } catch (\Exception $e) {
            OfficeGuyApi::writeToLog(
                "FulfillmentListener: Fulfillment dispatch failed for transaction {$event->transaction->id}: {$e->getMessage()}",
                'error'
            );

            // Re-throw exception to ensure it's logged and monitored
            // Projects can catch this in their exception handler
            throw $e;
        }
    }

    /**
     * Determine whether the listener should queue.
     *
     * Fulfillment should happen immediately after payment confirmation
     * for instant delivery products (eSIM, software licenses, etc.)
     *
     * Projects can override this by implementing ShouldQueue on custom handlers.
     *
     * @return bool
     */
    public function shouldQueue(): bool
    {
        return false;
    }
}
