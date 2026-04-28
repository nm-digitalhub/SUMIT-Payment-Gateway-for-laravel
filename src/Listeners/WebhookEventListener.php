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
 * Configure webhook URLs in the Admin Panel under "Custom Event Webhooks".
 * All events are logged to the database with connections to related resources.
 */
class WebhookEventListener
{
    public function __construct(protected WebhookService $webhookService) {}

    public function handlePaymentCompleted(PaymentCompleted $event): void
    {
        $tx = $event->transaction;
        $payable = $event->payable;

        $currencyMap = [0 => 'ILS', 1 => 'USD', 2 => 'EUR', 3 => 'GBP'];
        $currencyEnum = $event->payment['Currency'] ?? null;
        $currency = $tx?->currency
            ?? (is_int($currencyEnum) ? ($currencyMap[$currencyEnum] ?? null) : null);

        $amount = $event->payment['Amount'] ?? $tx?->amount;
        $customerEmail = method_exists($payable, 'getCustomerEmail') ? $payable->getCustomerEmail() : null;
        $orderType = $payable !== null ? get_class($payable) : null;

        $this->webhookService->sendPaymentCompleted([
            'order_id' => $event->orderId,
            'transaction_id' => $tx?->payment_id,
            'amount' => $amount,
            'currency' => $currency,
            'customer_email' => $customerEmail,
        ], [
            'transaction_id' => $tx?->id,
            'document_id' => $tx?->document_id,
            'order_type' => $orderType,
            'order_id' => $event->orderId,
            'customer_email' => $customerEmail,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $this->webhookService->sendPaymentFailed([
            'order_id' => $event->orderId,
            'error_message' => $event->message,
            'error_code' => null,
        ], [
            'order_id' => $event->orderId,
        ]);
    }

    public function handleDocumentCreated(DocumentCreated $event): void
    {
        $this->webhookService->sendDocumentCreated([
            'document_id' => $event->documentId,
            'document_type' => null,
            'order_id' => $event->orderId,
            'customer_id' => $event->customerId,
        ], [
            'order_id' => $event->orderId,
            'customer_id' => $event->customerId,
        ]);
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $sub = $event->subscription;

        $this->webhookService->sendSubscriptionCreated([
            'subscription_id' => $sub->id,
            'customer_id' => $sub->subscriber_id,
            'amount' => $sub->amount,
            'interval' => $sub->interval_months,
        ], [
            'subscription_id' => $sub->id,
            'customer_id' => $sub->subscriber_id,
            'amount' => $sub->amount,
        ]);
    }

    public function handleSubscriptionCharged(SubscriptionCharged $event): void
    {
        $sub = $event->subscription;
        $amount = $event->payment['Amount'] ?? $sub->amount;

        $this->webhookService->sendSubscriptionCharged([
            'subscription_id' => $sub->id,
            'charge_id' => $event->payment['ID'] ?? null,
            'amount' => $amount,
            'next_charge_date' => $sub->next_charge_at?->toIso8601String(),
        ], [
            'subscription_id' => $sub->id,
            'amount' => $amount,
        ]);
    }

    public function handleBitPaymentCompleted(BitPaymentCompleted $event): void
    {
        $this->webhookService->sendBitPaymentCompleted([
            'order_id' => $event->orderId,
            'document_id' => $event->documentId,
            'customer_id' => $event->customerId,
        ], [
            'order_id' => $event->orderId,
            'customer_id' => $event->customerId,
        ]);
    }

    public function handleStockSynced(StockSynced $event): void
    {
        $this->webhookService->sendStockSynced([
            'items_synced' => $event->synced,
            'items_skipped' => $event->skipped,
        ]);
    }

    public function subscribe($events): void
    {
        $events->listen(PaymentCompleted::class, [self::class, 'handlePaymentCompleted']);
        $events->listen(PaymentFailed::class, [self::class, 'handlePaymentFailed']);
        $events->listen(DocumentCreated::class, [self::class, 'handleDocumentCreated']);
        $events->listen(SubscriptionCreated::class, [self::class, 'handleSubscriptionCreated']);
        $events->listen(SubscriptionCharged::class, [self::class, 'handleSubscriptionCharged']);
        $events->listen(BitPaymentCompleted::class, [self::class, 'handleBitPaymentCompleted']);
        $events->listen(StockSynced::class, [self::class, 'handleStockSynced']);
    }
}
