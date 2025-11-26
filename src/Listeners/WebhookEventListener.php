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
 * All events are logged to the database with connections to related resources.
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
        $payload = [
            'order_id' => $event->orderId ?? null,
            'transaction_id' => $event->transactionId ?? null,
            'amount' => $event->amount ?? null,
            'currency' => $event->currency ?? null,
            'customer_email' => $event->customerEmail ?? null,
        ];

        $options = [
            'transaction_id' => $event->transactionModelId ?? null,
            'document_id' => $event->documentModelId ?? null,
            'token_id' => $event->tokenModelId ?? null,
            'order_type' => $event->orderType ?? null,
            'order_id' => $event->orderId ?? null,
            'customer_email' => $event->customerEmail ?? null,
            'amount' => $event->amount ?? null,
            'currency' => $event->currency ?? null,
        ];

        $this->webhookService->sendPaymentCompleted($payload, $options);
    }

    /**
     * Handle PaymentFailed event.
     */
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $payload = [
            'order_id' => $event->orderId ?? null,
            'error_message' => $event->errorMessage ?? null,
            'error_code' => $event->errorCode ?? null,
        ];

        $options = [
            'transaction_id' => $event->transactionModelId ?? null,
            'order_type' => $event->orderType ?? null,
            'order_id' => $event->orderId ?? null,
        ];

        $this->webhookService->sendPaymentFailed($payload, $options);
    }

    /**
     * Handle DocumentCreated event.
     */
    public function handleDocumentCreated(DocumentCreated $event): void
    {
        $payload = [
            'document_id' => $event->documentId ?? null,
            'document_type' => $event->documentType ?? null,
            'order_id' => $event->orderId ?? null,
            'customer_email' => $event->customerEmail ?? null,
        ];

        $options = [
            'document_id' => $event->documentModelId ?? null,
            'transaction_id' => $event->transactionModelId ?? null,
            'order_type' => $event->orderType ?? null,
            'order_id' => $event->orderId ?? null,
            'customer_email' => $event->customerEmail ?? null,
        ];

        $this->webhookService->sendDocumentCreated($payload, $options);
    }

    /**
     * Handle SubscriptionCreated event.
     */
    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $payload = [
            'subscription_id' => $event->subscriptionId ?? null,
            'customer_id' => $event->customerId ?? null,
            'amount' => $event->amount ?? null,
            'interval' => $event->interval ?? null,
        ];

        $options = [
            'subscription_id' => $event->subscriptionModelId ?? $event->subscriptionId ?? null,
            'token_id' => $event->tokenModelId ?? null,
            'customer_id' => $event->customerId ?? null,
            'amount' => $event->amount ?? null,
        ];

        $this->webhookService->sendSubscriptionCreated($payload, $options);
    }

    /**
     * Handle SubscriptionCharged event.
     */
    public function handleSubscriptionCharged(SubscriptionCharged $event): void
    {
        $payload = [
            'subscription_id' => $event->subscriptionId ?? null,
            'charge_id' => $event->chargeId ?? null,
            'amount' => $event->amount ?? null,
            'next_charge_date' => $event->nextChargeDate ?? null,
        ];

        $options = [
            'subscription_id' => $event->subscriptionModelId ?? $event->subscriptionId ?? null,
            'transaction_id' => $event->transactionModelId ?? null,
            'amount' => $event->amount ?? null,
        ];

        $this->webhookService->sendSubscriptionCharged($payload, $options);
    }

    /**
     * Handle BitPaymentCompleted event.
     */
    public function handleBitPaymentCompleted(BitPaymentCompleted $event): void
    {
        $payload = [
            'order_id' => $event->orderId ?? null,
            'transaction_id' => $event->transactionId ?? null,
            'amount' => $event->amount ?? null,
        ];

        $options = [
            'transaction_id' => $event->transactionModelId ?? null,
            'document_id' => $event->documentModelId ?? null,
            'order_type' => $event->orderType ?? null,
            'order_id' => $event->orderId ?? null,
            'amount' => $event->amount ?? null,
        ];

        $this->webhookService->sendBitPaymentCompleted($payload, $options);
    }

    /**
     * Handle StockSynced event.
     */
    public function handleStockSynced(StockSynced $event): void
    {
        $payload = [
            'items_synced' => $event->itemsSynced ?? null,
            'sync_type' => $event->syncType ?? null,
        ];

        $this->webhookService->sendStockSynced($payload);
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
