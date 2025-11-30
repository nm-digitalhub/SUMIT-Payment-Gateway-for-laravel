<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Events\DocumentCreated;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Jobs\SyncDocumentsJob;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;

/**
 * Document Sync Listener
 *
 * Automatically syncs documents when:
 * 1. Webhook received from SUMIT (subscription charge, invoice created)
 * 2. Document created event fired
 *
 * Triggers background job to sync documents without blocking the webhook response.
 */
class DocumentSyncListener implements ShouldQueue
{
    /**
     * The name of the queue on which to place the job.
     *
     * @var string
     */
    public $queue = 'default';

    /**
     * Handle SUMIT webhook received event
     *
     * @param SumitWebhookReceived $event
     * @return void
     */
    public function handleWebhook(SumitWebhookReceived $event): void
    {
        $payload = $event->payload;
        $type = $payload['Type'] ?? null;

        // Only sync for subscription-related webhooks
        $subscriptionTypes = [
            'RecurringCharge',      // Subscription charged
            'RecurringCreated',     // New subscription created
            'RecurringUpdated',     // Subscription updated
            'RecurringCancelled',   // Subscription cancelled
            'InvoiceCreated',       // Invoice created (might contain multiple subscriptions)
        ];

        if (!in_array($type, $subscriptionTypes)) {
            return;
        }

        Log::info('Document sync triggered by webhook', [
            'webhook_type' => $type,
            'payload_id' => $payload['ID'] ?? null,
        ]);

        // Extract user/customer info from webhook
        $customerId = $payload['CustomerID'] ?? null;
        $userId = $this->findUserByCustomerId($customerId);

        if ($userId) {
            // Dispatch background job to sync documents for this user
            // Only look back 7 days for webhook-triggered syncs (recent documents)
            SyncDocumentsJob::dispatch($userId, 7, false);
        } else {
            // If we can't identify user, sync all users (rare case)
            // This ensures we don't miss any documents
            SyncDocumentsJob::dispatch(null, 7, false);
        }
    }

    /**
     * Handle document created event
     *
     * @param DocumentCreated $event
     * @return void
     */
    public function handleDocumentCreated(DocumentCreated $event): void
    {
        Log::info('Document sync triggered by DocumentCreated event', [
            'document_id' => $event->documentId,
            'customer_id' => $event->customerId,
        ]);

        // Find user by customer ID
        $userId = $this->findUserByCustomerId($event->customerId);

        if ($userId) {
            // Sync only for this user, only recent 7 days
            SyncDocumentsJob::dispatch($userId, 7, false);
        }
    }

    /**
     * Find user ID by SUMIT customer ID
     *
     * @param string|int|null $customerId
     * @return int|null
     */
    protected function findUserByCustomerId($customerId): ?int
    {
        if (!$customerId) {
            return null;
        }

        $user = \App\Models\User::where('sumit_customer_id', $customerId)->first();

        return $user?->id;
    }

    /**
     * Subscribe to events
     *
     * @param \Illuminate\Events\Dispatcher $events
     * @return array
     */
    public function subscribe($events): array
    {
        return [
            SumitWebhookReceived::class => 'handleWebhook',
            DocumentCreated::class => 'handleDocumentCreated',
        ];
    }
}
