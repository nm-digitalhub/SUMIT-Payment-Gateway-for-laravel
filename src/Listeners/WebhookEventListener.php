<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\BitPaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Events\DocumentCreated;
use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Events\PaymentFailed;
use OfficeGuy\LaravelSumitGateway\Events\StockSynced;
use OfficeGuy\LaravelSumitGateway\Events\SubscriptionCharged;
use OfficeGuy\LaravelSumitGateway\Events\SubscriptionCreated;
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

/**
 * WebhookEventListener - Listens to all SUMIT events and sends webhooks
 *
 * This listener automatically sends webhook notifications for configured events.
 * Configure webhook URLs in the Admin Panel under "Custom Event Webhooks".
 */
class WebhookEventListener
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle PaymentCompleted event.
     */
    public function handlePaymentCompleted(PaymentCompleted $event): void
    {
        $this->webhookService->sendPaymentCompleted([
            'order_id' => $event->orderId ?? null,
            'transaction_id' => $event->transactionId ?? null,
            'amount' => $event->amount ?? null,
            'currency' => $event->currency ?? null,
            'customer_email' => $event->customerEmail ?? null,
        ]);
    }

    /**
     * Handle PaymentFailed event.
     */
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->webhookService->sendPaymentFailed([
            'order_id' => $event->orderId ?? null,
            'error_message' => $event->errorMessage ?? null,
            'error_code' => $event->errorCode ?? null,
        ]);
    }

    /**
     * Handle DocumentCreated event.
     */
    public function handleDocumentCreated(DocumentCreated $event): void
    {
        $this->webhookService->sendDocumentCreated([
            'document_id' => $event->documentId ?? null,
            'document_type' => $event->documentType ?? null,
            'order_id' => $event->orderId ?? null,
            'customer_email' => $event->customerEmail ?? null,
        ]);
    }

    /**
     * Handle SubscriptionCreated event.
     */
    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->webhookService->sendSubscriptionCreated([
            'subscription_id' => $event->subscriptionId ?? null,
            'customer_id' => $event->customerId ?? null,
            'amount' => $event->amount ?? null,
            'interval' => $event->interval ?? null,
        ]);
    }

    /**
     * Handle SubscriptionCharged event.
     */
    public function handleSubscriptionCharged(SubscriptionCharged $event): void
    {
        $this->webhookService->sendSubscriptionCharged([
            'subscription_id' => $event->subscriptionId ?? null,
            'charge_id' => $event->chargeId ?? null,
            'amount' => $event->amount ?? null,
            'next_charge_date' => $event->nextChargeDate ?? null,
        ]);
    }

    /**
     * Handle BitPaymentCompleted event.
     */
    public function handleBitPaymentCompleted(BitPaymentCompleted $event): void
    {
        $this->webhookService->sendBitPaymentCompleted([
            'order_id' => $event->orderId ?? null,
            'transaction_id' => $event->transactionId ?? null,
            'amount' => $event->amount ?? null,
        ]);
    }

    /**
     * Handle StockSynced event.
     */
    public function handleStockSynced(StockSynced $event): void
    {
        $this->webhookService->sendStockSynced([
            'items_synced' => $event->itemsSynced ?? null,
            'sync_type' => $event->syncType ?? null,
        ]);
    }

    /**
     * Subscribe to multiple events.
     *
     * @param \Illuminate\Events\Dispatcher $events
     */
    public function subscribe($events): void
    {
        $events->listen(
            PaymentCompleted::class,
            [self::class, 'handlePaymentCompleted']
        );

        $events->listen(
            PaymentFailed::class,
            [self::class, 'handlePaymentFailed']
        );

        $events->listen(
            DocumentCreated::class,
            [self::class, 'handleDocumentCreated']
        );

        $events->listen(
            SubscriptionCreated::class,
            [self::class, 'handleSubscriptionCreated']
        );

        $events->listen(
            SubscriptionCharged::class,
            [self::class, 'handleSubscriptionCharged']
        );

        $events->listen(
            BitPaymentCompleted::class,
            [self::class, 'handleBitPaymentCompleted']
        );

        $events->listen(
            StockSynced::class,
            [self::class, 'handleStockSynced']
        );
    }
}
