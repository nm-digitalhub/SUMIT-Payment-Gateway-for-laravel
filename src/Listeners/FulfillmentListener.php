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
 * ## Role in Architecture
 *
 * This listener is the **entry point from Payment Events to Fulfillment Actions**.
 * It acts as a bridge between:
 * - **Payment Events** (package layer)
 * - **Fulfillment Execution** (handler layer)
 *
 * ## Flow
 *
 * ```
 * PaymentCompleted Event (from PaymentService)
 *     ↓
 * FulfillmentListener::handle()
 *     ↓
 * FulfillmentDispatcher::dispatch(payable, transaction)
 *     ↓
 * Handler::handle(transaction) [e.g., ProvisionServiceJob]
 * ```
 *
 * ## Integration with Application State Machine
 *
 * The **Application Layer** (/httpdocs) owns the Order State Machine:
 * - OrderStateMachine manages state transitions (pending → processing → provisioning → completed)
 * - OrderStatusAudit tracks all state changes
 *
 * This **Package Layer** handles fulfillment execution:
 * - Receives PaymentCompleted event
 * - Dispatches to appropriate fulfillment handler
 * - Does NOT manage application state (that's the app's responsibility)
 *
 * ## Error Handling
 *
 * - Logs detailed error information for debugging
 * - Re-throws exceptions to ensure they're caught by exception handler
 * - Projects can catch these exceptions in their custom exception handlers
 *
 * ## Should Queue Behavior
 *
 * Returns `false` for `shouldQueue()` because:
 * - Fulfillment should happen immediately after payment confirmation
 * - For instant delivery products (eSIM, software licenses, etc.)
 * - Projects can override by implementing ShouldQueue on custom handlers
 *
 * @see docs/STATE_MACHINE_ARCHITECTURE.md
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
