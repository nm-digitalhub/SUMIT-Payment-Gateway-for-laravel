<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Payment Completed Event
 *
 * Dispatched when a payment is completed and confirmed.
 * This event triggers provisioning and other post-payment actions.
 *
 * Version History:
 * - v1.x: orderId, payment array, response array
 * - v2.0: Added transaction object and payable object (backward compatible)
 *
 * CRITICAL: This event should only be dispatched when payment is CONFIRMED
 * (either via webhook callback or redirect callback from SUMIT).
 */
class PaymentCompleted
{
    /**
     * @param string|int $orderId Order ID (for backward compatibility)
     * @param array $payment Payment data array
     * @param array $response SUMIT API response
     * @param OfficeGuyTransaction|null $transaction Transaction object (v2.0+)
     * @param object|null $payable Payable entity (Order, Invoice, etc.) (v2.0+)
     */
    public function __construct(
        public string|int $orderId,
        public array $payment,
        public array $response,
        public ?OfficeGuyTransaction $transaction = null,
        public ?object $payable = null
    ) {}

    /**
     * Check if transaction is webhook-confirmed
     *
     * @return bool
     */
    public function isWebhookConfirmed(): bool
    {
        return $this->transaction?->is_webhook_confirmed ?? false;
    }
}
