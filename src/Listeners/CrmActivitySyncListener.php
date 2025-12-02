<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Jobs\SyncCrmFromWebhookJob;

/**
 * Listens for SUMIT CRM webhooks and queues a sync for the related activities folder.
 */
class CrmActivitySyncListener
{
    /**
     * SUMIT folder ID whose changes should refresh related entities/activities.
     * TODO: consider moving to config if more folders are needed.
     */
    private const ACTIVITIES_FOLDER_ID = 1076734599;

    public function handle(SumitWebhookReceived $event): void
    {
        $webhook = $event->webhook;

        if (($webhook->event_type ?? null) !== 'crm') {
            return;
        }

        $folderId = $webhook->getCrmFolderId();

        if ($folderId !== self::ACTIVITIES_FOLDER_ID) {
            return;
        }

        // Always perform full folder sync for activities folder.
        SyncCrmFromWebhookJob::dispatch(null, $folderId, $webhook->payload ?? []);

        Log::info('CrmActivitySyncListener: queued CRM sync from webhook', [
            'webhook_id' => $webhook->id,
            'folder_id' => $folderId,
        ]);
    }
}
