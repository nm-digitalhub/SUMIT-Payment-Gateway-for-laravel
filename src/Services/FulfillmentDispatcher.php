<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Fulfillment Dispatcher
 *
 * Container-driven fulfillment orchestration based on PayableType.
 *
 * This service acts as the **bridge between payment completion and service fulfillment**.
 * It receives PaymentCompleted events from the package and dispatches to appropriate
 * fulfillment handlers based on the Payable type (Infrastructure, Digital, Subscription, etc.).
 *
 * ## Architecture Decision: Type-Based Dispatch (not Payable-Driven)
 *
 * **Principle:** Payable only returns its Type via getPayableType() → Dispatcher maps Type→Handler
 *
 * Benefits:
 * - ✅ Centralized configuration (all mappings in ServiceProvider)
 * - ✅ Type = Single Source of Truth
 * - ✅ Testable (easy to swap handlers in tests)
 * - ✅ Laravel convention (bindings in ServiceProvider)
 * - ✅ Application can override by implementing getFulfillmentHandler()
 *
 * ## Integration with Application State Machine
 *
 * The Application Layer (/httpdocs) owns the Order State Machine and manages state transitions.
 * This Package Layer receives PaymentCompleted events and dispatches fulfillment actions.
 *
 * Flow:
 * ```
 * Application: OrderStateMachine::transitionTo(PROCESSING)
 *             → PaymentCompleted event dispatched
 *             → Package: FulfillmentListener
 *             → Package: FulfillmentDispatcher
 *             → Package: Handler (provision service)
 *             → Application: OrderStateMachine::transitionTo(COMPLETED)
 * ```
 *
 * ## Registration
 *
 * Handlers are registered in OfficeGuyServiceProvider::registerFulfillmentHandlers():
 *
 * ```php
 * $dispatcher->registerMany([
 *     PayableType::INFRASTRUCTURE->value => InfrastructureFulfillmentHandler::class,
 *     PayableType::DIGITAL_PRODUCT->value => DigitalProductFulfillmentHandler::class,
 *     PayableType::SUBSCRIPTION->value => SubscriptionFulfillmentHandler::class,
 * ]);
 * ```
 *
 * @see docs/ARCHITECTURE_DECISION_FULFILLMENT_PATTERN.md
 * @see docs/STATE_MACHINE_ARCHITECTURE.md
 */
class FulfillmentDispatcher
{
    /**
     * Registered Type→Handler mappings
     *
     * @var array<string, string>
     */
    protected array $handlers = [];

    /**
     * Register a handler for a specific PayableType
     *
     * Called from ServiceProvider::boot()
     *
     * @param PayableType $type The type to handle
     * @param string $handlerClass Fully-qualified handler class name
     * @return void
     */
    public function register(PayableType $type, string $handlerClass): void
    {
        $this->handlers[$type->value] = $handlerClass;

        OfficeGuyApi::writeToLog(
            "FulfillmentDispatcher: Registered {$handlerClass} for type {$type->value}",
            'debug'
        );
    }

    /**
     * Dispatch fulfillment based on Payable Type
     *
     * Priority:
     * 1. Check if Payable overrides handler (rare - getFulfillmentHandler())
     * 2. Use Type-based registered handler (common)
     * 3. Fallback to generic event (if no handler registered)
     *
     * @param Payable $payable The payable entity
     * @param OfficeGuyTransaction $transaction The completed transaction
     * @return void
     */
    public function dispatch(Payable $payable, OfficeGuyTransaction $transaction): void
    {
        // Priority 1: Optional Payable-specific override (rare)
        if (method_exists($payable, 'getFulfillmentHandler')) {
            $customHandler = $payable->getFulfillmentHandler();

            if ($customHandler && class_exists($customHandler)) {
                OfficeGuyApi::writeToLog(
                    "FulfillmentDispatcher: Using custom handler {$customHandler} for payable {$payable->id}",
                    'info'
                );

                app($customHandler)->handle($transaction);
                return;
            }
        }

        // Priority 2: Type-based handler (common path)
        $type = $payable->getPayableType();

        if ($handler = $this->handlers[$type->value] ?? null) {
            OfficeGuyApi::writeToLog(
                "FulfillmentDispatcher: Dispatching to {$handler} for type {$type->value}",
                'info'
            );

            app($handler)->handle($transaction);
            return;
        }

        // Priority 3: No handler registered - log warning
        OfficeGuyApi::writeToLog(
            "FulfillmentDispatcher: No handler registered for type {$type->value}, payable {$payable->id}. Consider registering a handler in ServiceProvider.",
            'warning'
        );

        // Optional: Dispatch generic event for backwards compatibility
        // event(new GenericFulfillmentRequested($transaction));
    }

    /**
     * Check if a Type has a registered handler
     *
     * @param PayableType $type
     * @return bool
     */
    public function hasHandler(PayableType $type): bool
    {
        return isset($this->handlers[$type->value]);
    }

    /**
     * Get all registered handlers (for debugging/testing)
     *
     * @return array<string, string>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Get handler class for a specific type (if registered)
     *
     * @param PayableType $type
     * @return string|null
     */
    public function getHandler(PayableType $type): ?string
    {
        return $this->handlers[$type->value] ?? null;
    }

    /**
     * Clear all registered handlers (for testing)
     *
     * @return void
     */
    public function clearHandlers(): void
    {
        $this->handlers = [];
    }

    /**
     * Register multiple handlers at once
     *
     * @param array<string, string> $mappings Array of PayableType value => Handler class
     * @return void
     */
    public function registerMany(array $mappings): void
    {
        foreach ($mappings as $type => $handler) {
            // Convert string value back to Enum
            $enum = PayableType::from($type);
            $this->register($enum, $handler);
        }
    }
}
