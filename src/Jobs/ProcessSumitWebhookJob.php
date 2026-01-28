<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

/**
 * Asynchronously process a SUMIT webhook that was already persisted.
 */
class ProcessSumitWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $webhookId) {}

    public function handle(): void
    {
        $webhook = SumitWebhook::find($this->webhookId);

        if (! $webhook) {
            return;
        }

        try {
            event(new SumitWebhookReceived($webhook));
        } catch (\Throwable $e) {
            Log::error('ProcessSumitWebhookJob failed', [
                'message' => $e->getMessage(),
                'webhook_id' => $this->webhookId,
            ]);
        }
    }
}
