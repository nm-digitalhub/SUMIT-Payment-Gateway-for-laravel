<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

/**
 * Process Recurring Payments Job
 *
 * Processes due subscription charges via SUMIT recurring billing API.
 * Can process all due subscriptions or a specific subscription ID.
 */
class ProcessRecurringPaymentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        /**
         * Specific subscription ID to process (null = all due subscriptions)
         */
        public ?int $subscriptionId = null
    ) {}

    public function handle(): void
    {
        if ($this->subscriptionId) {
            $subscription = Subscription::find($this->subscriptionId);

            if (! $subscription) {
                Log::warning("ProcessRecurringPaymentsJob: Subscription #{$this->subscriptionId} not found");

                return;
            }

            if (! $subscription->canBeCharged()) {
                Log::info("ProcessRecurringPaymentsJob: Subscription #{$this->subscriptionId} cannot be charged");

                return;
            }

            $result = SubscriptionService::processRecurringCharge($subscription);

            if ($result['success']) {
                Log::info("ProcessRecurringPaymentsJob: Subscription #{$this->subscriptionId} charged successfully");
            } else {
                Log::error("ProcessRecurringPaymentsJob: Subscription #{$this->subscriptionId} failed: " . ($result['message'] ?? 'Unknown error'));
            }
        } else {
            $results = SubscriptionService::processDueSubscriptions();

            $total = count($results);
            $successful = count(array_filter($results, fn (array $r) => $r['success']));
            $failed = $total - $successful;

            Log::info("ProcessRecurringPaymentsJob: Processed {$total} subscriptions: {$successful} successful, {$failed} failed");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessRecurringPaymentsJob failed: ' . $exception->getMessage(), [
            'subscription_id' => $this->subscriptionId,
            'exception' => $exception,
        ]);
    }
}
