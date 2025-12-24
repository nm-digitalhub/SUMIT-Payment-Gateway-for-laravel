<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * RefundWebhookListener - Updates transaction status when refund webhooks are received
 *
 * This listener processes incoming SUMIT webhooks and detects refunds by:
 * 1. Checking for negative amounts (Billing_CurrencyEnum < 0)
 * 2. Finding the original transaction via status_description matching
 * 3. Updating the transaction status to 'refunded'
 *
 * Architecture:
 * - Primary Layer: SUMIT webhooks (System of Record)
 * - This listener ensures local database stays in sync with SUMIT
 */
class RefundWebhookListener
{
    /**
     * Handle the SumitWebhookReceived event.
     */
    public function handle(SumitWebhookReceived $event): void
    {
        $webhook = $event->webhook;
        $payload = $webhook->payload;

        // Only process CRM webhooks with Properties
        if ($webhook->event_type !== 'crm' || ! isset($payload['Properties'])) {
            return;
        }

        // Check if this is a refund (negative amount)
        if (! $this->isRefund($payload)) {
            return;
        }

        try {
            $this->processRefundWebhook($webhook, $payload);
        } catch (\Throwable $e) {
            Log::error('RefundWebhookListener failed', [
                'webhook_id' => $webhook->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if the webhook represents a refund.
     */
    protected function isRefund(array $payload): bool
    {
        if (! isset($payload['Properties']['Billing_CurrencyEnum'])) {
            return false;
        }

        $amount = $payload['Properties']['Billing_CurrencyEnum'][0] ?? 0;

        return $amount < 0;
    }

    /**
     * Process a refund webhook and update the original transaction.
     */
    protected function processRefundWebhook($webhook, array $payload): void
    {
        $refundAmount = abs($payload['Properties']['Billing_CurrencyEnum'][0] ?? 0);
        $paymentSourceName = $payload['Properties']['Billing_PaymentSource'][0]['Name'] ?? null;

        if (! $paymentSourceName) {
            OfficeGuyApi::writeToLog(
                "Refund webhook #{$webhook->id} missing Billing_PaymentSource.Name",
                'warning'
            );
            return;
        }

        // Find the original transaction by matching status_description
        $transaction = OfficeGuyTransaction::where('status_description', $paymentSourceName)
            ->where('status', '!=', 'refunded') // Only update if not already refunded
            ->first();

        if (! $transaction) {
            OfficeGuyApi::writeToLog(
                "Refund webhook #{$webhook->id}: No transaction found with status_description='{$paymentSourceName}'",
                'info'
            );
            return;
        }

        // Update transaction status to refunded
        $transaction->update([
            'status' => 'refunded',
            // Keep the existing status_description (it contains the refund reason)
        ]);

        // Mark webhook as processed
        $webhook->update([
            'processed' => true,
            'processed_at' => now(),
        ]);

        OfficeGuyApi::writeToLog(
            "✅ Refund webhook processed: Transaction #{$transaction->id} (payment_id: {$transaction->payment_id}) marked as refunded via webhook #{$webhook->id}",
            'info'
        );

        Log::info('Refund webhook processed successfully', [
            'webhook_id' => $webhook->id,
            'transaction_id' => $transaction->id,
            'payment_id' => $transaction->payment_id,
            'refund_amount' => $refundAmount,
            'refund_reason' => $paymentSourceName,
        ]);
    }
}
