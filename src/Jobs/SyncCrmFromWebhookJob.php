<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

/**
 * Queue job to sync CRM data from a SUMIT webhook without blocking the request.
 */
class SyncCrmFromWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ?int $entityId,
        public readonly ?int $folderId,
        public readonly array $payload = []
    ) {}

    public function handle(): void
    {
        try {
            // Always prefer full folder sync when folder id is present.
            if ($this->folderId) {
                CrmDataService::syncAllEntities($this->folderId);

                return;
            }

            if ($this->entityId) {
                CrmDataService::syncEntityFromSumit($this->entityId);
            }
        } catch (\Throwable $e) {
            Log::error('SyncCrmFromWebhookJob failed', [
                'message' => $e->getMessage(),
                'entityId' => $this->entityId,
                'folderId' => $this->folderId,
                'payload' => $this->payload,
            ]);
        }
    }
}
