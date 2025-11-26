<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Services\CustomerMergeService;

/**
 * Customer Sync Listener
 *
 * Automatically syncs customers from SUMIT webhooks to the local customer model.
 * Works with customer card events from SUMIT triggers.
 */
class CustomerSyncListener
{
    protected CustomerMergeService $customerMergeService;

    public function __construct(CustomerMergeService $customerMergeService)
    {
        $this->customerMergeService = $customerMergeService;
    }

    /**
     * Handle the event.
     */
    public function handle(SumitWebhookReceived $event): void
    {
        $webhook = $event->webhook;

        // Only process customer-related webhooks
        if (!$this->isCustomerWebhook($webhook)) {
            return;
        }

        $payload = $webhook->payload ?? [];

        // Extract customer data from webhook
        $customerData = $this->extractCustomerData($payload);

        if (empty($customerData)) {
            Log::debug('CustomerSyncListener: No customer data found in webhook', [
                'webhook_id' => $webhook->id,
                'card_type' => $webhook->card_type ?? null,
            ]);
            return;
        }

        // Sync customer
        $localCustomer = $this->customerMergeService->syncFromSumit($customerData);

        if ($localCustomer) {
            Log::info('CustomerSyncListener: Customer synced successfully', [
                'webhook_id' => $webhook->id,
                'local_customer_id' => $localCustomer->getKey(),
                'sumit_customer_id' => $customerData['ID'] ?? $customerData['id'] ?? null,
            ]);

            // Mark webhook as processed if it has that status
            if (method_exists($webhook, 'markAsProcessed')) {
                $webhook->markAsProcessed();
            }
        }
    }

    /**
     * Check if this webhook is customer-related.
     */
    protected function isCustomerWebhook($webhook): bool
    {
        // Check card type
        $cardType = $webhook->card_type ?? null;
        if ($cardType === 'customer') {
            return true;
        }

        // Check event type
        $eventType = $webhook->event_type ?? null;
        if (in_array($eventType, ['card_created', 'card_updated']) && $cardType === 'customer') {
            return true;
        }

        // Check payload for customer data indicators
        $payload = $webhook->payload ?? [];
        if (isset($payload['CustomerID']) || isset($payload['customer_id'])) {
            return true;
        }

        // Check if this is a payment webhook that contains customer info
        if (isset($payload['Customer']) || isset($payload['customer'])) {
            return true;
        }

        return false;
    }

    /**
     * Extract customer data from webhook payload.
     */
    protected function extractCustomerData(array $payload): array
    {
        // Direct customer data (from customer card webhooks)
        if (isset($payload['Email']) || isset($payload['email'])) {
            return $payload;
        }

        // Nested customer object (from payment webhooks)
        if (isset($payload['Customer']) && is_array($payload['Customer'])) {
            return $payload['Customer'];
        }

        if (isset($payload['customer']) && is_array($payload['customer'])) {
            return $payload['customer'];
        }

        // Try to construct customer data from payment-related fields
        if (isset($payload['CustomerEmail']) || isset($payload['customer_email'])) {
            return [
                'Email' => $payload['CustomerEmail'] ?? $payload['customer_email'] ?? null,
                'Name' => $payload['CustomerName'] ?? $payload['customer_name'] ?? null,
                'Phone' => $payload['CustomerPhone'] ?? $payload['customer_phone'] ?? null,
                'ID' => $payload['CustomerID'] ?? $payload['customer_id'] ?? null,
            ];
        }

        return [];
    }
}
