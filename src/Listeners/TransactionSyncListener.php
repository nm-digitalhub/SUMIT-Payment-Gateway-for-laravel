<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Transaction Sync Listener
 *
 * Implements ADR-004: Handling Card Payments via SUMIT CRM Webhooks
 *
 * Listens for SUMIT CRM Transaction webhooks and confirms card payments.
 * This is the ONLY way to confirm card (token) payments, as SUMIT does not
 * provide a dedicated card payment webhook like it does for Bit.
 *
 * @see docs/ADR-004-CARD-PAYMENT-WEBHOOKS.md
 */
class TransactionSyncListener
{
    /**
     * SUMIT Transactions folder ID (CRM).
     * This folder contains all payment transaction cards created by SUMIT.
     */
    private const TRANSACTIONS_FOLDER_ID = 1076735286;

    /**
     * Handle the SUMIT webhook received event.
     *
     * Guard Conditions (ALL must be true):
     * 1. event_type = 'crm'
     * 2. folder_id = TRANSACTIONS_FOLDER_ID
     * 3. Type = 'CreateOrUpdate'
     * 4. Status = 'מאושר' (approved)
     * 5. Transaction not already webhook-confirmed (idempotency)
     *
     * @param SumitWebhookReceived $event
     * @return void
     */
    public function handle(SumitWebhookReceived $event): void
    {
        $webhook = $event->webhook;

        // Guard: CRM webhooks only
        if (($webhook->event_type ?? null) !== 'crm') {
            return;
        }

        // Guard: Transactions folder only
        $folderId = $webhook->getCrmFolderId();
        if ($folderId !== self::TRANSACTIONS_FOLDER_ID) {
            return;
        }

        $payload = $webhook->payload;

        // Guard: Create or CreateOrUpdate only (not Delete)
        $type = $payload['Type'] ?? null;
        if (!in_array($type, ['Create', 'CreateOrUpdate'], true)) {
            Log::debug('TransactionSyncListener: Ignoring non-Create/CreateOrUpdate webhook', [
                'webhook_id' => $webhook->id,
                'type' => $type,
            ]);
            return;
        }

        // Extract transaction data from webhook
        // CRITICAL: SUMIT uses 2 different webhook structures!
        // Structure 1 (old): Property_4=amount, Property_6=status, Property_7=payment_method
        // Structure 2 (new): Billing_Amount=amount, Billing_PaymentMethod=payment_method (no status field)
        $entityId = $payload['EntityID'] ?? null;

        // Try new structure first (Billing_* fields), then fallback to old structure (Property_* fields)
        $amount = $payload['Properties']['Billing_Amount'][0] ?? $payload['Properties']['Property_4'][0] ?? null;
        $paymentMethodObj = $payload['Properties']['Billing_PaymentMethod'][0] ?? $payload['Properties']['Property_7'][0] ?? null;
        $status = $payload['Properties']['Property_6'][0] ?? null;  // Only exists in old structure

        // Guard: If old structure (has status field), check for "מאושר"
        // If new structure (no status field), assume approved if has Billing_* fields
        if ($status !== null && $status !== 'מאושר') {
            Log::debug('TransactionSyncListener: Ignoring non-approved transaction', [
                'webhook_id' => $webhook->id,
                'entity_id' => $entityId,
                'status' => $status,
            ]);
            return;
        }

        // Extract payment method name from object structure
        // SUMIT sends: {"ID": 1095061476, "Name": "כרטיס אשראי (9429)"}
        $paymentMethodName = null;
        if (is_array($paymentMethodObj) && isset($paymentMethodObj['Name'])) {
            $paymentMethodName = $paymentMethodObj['Name'];
        } elseif (is_string($paymentMethodObj)) {
            // Backward compatibility if SUMIT changes format to string
            $paymentMethodName = $paymentMethodObj;
        }

        // Guard: Card payments only (not Bit, not refunds, not accounting operations)
        // This is critical to prevent calling Order::onPaymentConfirmed() for non-card payments
        if (!$paymentMethodName || !str_contains($paymentMethodName, 'כרטיס')) {
            Log::debug('TransactionSyncListener: Ignoring non-card payment', [
                'webhook_id' => $webhook->id,
                'entity_id' => $entityId,
                'payment_method_obj' => $paymentMethodObj,
                'payment_method_name' => $paymentMethodName,
            ]);
            return;
        }

        // Guard: Non-zero amount
        // CRITICAL: Amount can be object (document) in old structure, or float in new structure
        $amountValue = is_array($amount) ? null : (float)($amount ?? 0);
        if (!$amountValue || $amountValue === 0.0) {
            Log::debug('TransactionSyncListener: Ignoring zero-amount transaction', [
                'webhook_id' => $webhook->id,
                'entity_id' => $entityId,
                'amount' => $amount,
                'amount_value' => $amountValue,
            ]);
            return;
        }

        // Find existing transaction by SUMIT entity ID
        // Note: We need to add sumit_entity_id field to officeguy_transactions table
        // For now, we'll try to find by amount + timestamp proximity
        $transaction = $this->findOrCreateTransaction($payload, $entityId);

        if (!$transaction) {
            Log::warning('TransactionSyncListener: Could not find/create transaction', [
                'webhook_id' => $webhook->id,
                'entity_id' => $entityId,
            ]);
            return;
        }

        // Guard: Idempotency - already webhook-confirmed
        if ($transaction->is_webhook_confirmed) {
            Log::debug('TransactionSyncListener: Transaction already webhook-confirmed', [
                'webhook_id' => $webhook->id,
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        // Mark transaction as webhook-confirmed
        $transaction->update([
            'is_webhook_confirmed' => true,
            'confirmed_at' => now(),
            'confirmed_by' => 'webhook_crm',
        ]);

        Log::info('TransactionSyncListener: Transaction confirmed from CRM webhook', [
            'webhook_id' => $webhook->id,
            'transaction_id' => $transaction->id,
            'entity_id' => $entityId,
            'amount' => $amount,
        ]);

        // 🔥 Call Order::onPaymentConfirmed()
        $payable = $transaction->payable;

        if ($payable && method_exists($payable, 'onPaymentConfirmed')) {
            try {
                $payable->onPaymentConfirmed();

                Log::info('TransactionSyncListener: Order payment confirmed', [
                    'webhook_id' => $webhook->id,
                    'transaction_id' => $transaction->id,
                    'payable_type' => get_class($payable),
                    'payable_id' => $payable->getKey(),
                ]);
            } catch (\Exception $e) {
                Log::error('TransactionSyncListener: Failed to call onPaymentConfirmed', [
                    'webhook_id' => $webhook->id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } else {
            Log::warning('TransactionSyncListener: No payable or onPaymentConfirmed method', [
                'webhook_id' => $webhook->id,
                'transaction_id' => $transaction->id,
                'has_payable' => (bool)$payable,
                'payable_type' => $payable ? get_class($payable) : null,
            ]);
        }

        // Mark webhook as processed
        // This provides:
        // - Clear audit trail
        // - Retry protection (webhook won't be reprocessed)
        // - Monitoring capability (can track processing failures)
        $webhook->markAsProcessed();

        Log::info('TransactionSyncListener: Webhook marked as processed', [
            'webhook_id' => $webhook->id,
            'entity_id' => $entityId,
        ]);
    }

    /**
     * Find OfficeGuyTransaction by SUMIT Entity ID.
     *
     * This is the ONLY safe way to match SUMIT CRM Transaction cards
     * to local OfficeGuyTransaction records. Matching by amount+timestamp
     * is unsafe due to duplicate amounts, retries, recurring payments, etc.
     *
     * @param array $payload Webhook payload
     * @param int|null $entityId SUMIT CRM Entity ID
     * @return OfficeGuyTransaction|null
     *
     * @see ADR-004: Handling Card Payments via SUMIT CRM Webhooks
     */
    private function findOrCreateTransaction(array $payload, ?int $entityId): ?OfficeGuyTransaction
    {
        if (!$entityId) {
            return null;
        }

        // Try to find existing transaction by SUMIT Entity ID
        $transaction = OfficeGuyTransaction::where('sumit_entity_id', $entityId)->first();

        if ($transaction) {
            return $transaction;
        }

        // Transaction not found by sumit_entity_id
        // This means either:
        // 1. The transaction was created before sumit_entity_id field existed
        // 2. PaymentService didn't populate sumit_entity_id
        // 3. This is a webhook for a transaction we don't have locally

        Log::warning('TransactionSyncListener: Transaction not found by sumit_entity_id', [
            'entity_id' => $entityId,
            'payload' => $payload,
        ]);

        return null;
    }
}
