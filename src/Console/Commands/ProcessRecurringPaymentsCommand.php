<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Jobs\ProcessRecurringPaymentsJob;

/**
 * Process Recurring Payments Command
 *
 * Processes all due subscription charges.
 * Should be scheduled to run daily or as needed via Laravel's task scheduler.
 *
 * Example scheduling in app/Console/Kernel.php or routes/console.php:
 *
 * Schedule::command('sumit:process-recurring-payments')->daily();
 * // or
 * Schedule::command('sumit:process-recurring-payments')->hourly();
 */
class ProcessRecurringPaymentsCommand extends Command
{
    protected $signature = 'sumit:process-recurring-payments 
                            {--sync : Run synchronously instead of dispatching job}
                            {--subscription= : Process a specific subscription ID only}';

    protected $description = 'Process due recurring subscription payments via SUMIT gateway';

    public function handle(): int
    {
        $subscriptionId = $this->option('subscription');
        $sync = (bool) $this->option('sync');

        if ($sync) {
            $this->info('Processing recurring payments synchronously...');
            $results = \OfficeGuy\LaravelSumitGateway\Services\SubscriptionService::processDueSubscriptions();

            $total = count($results);
            $successful = count(array_filter($results, fn($r) => $r['success']));
            $failed = $total - $successful;

            $this->info("Processed {$total} subscriptions: {$successful} successful, {$failed} failed");

            foreach ($results as $id => $result) {
                if ($result['success']) {
                    $this->line("  ✓ Subscription #{$id}: charged successfully");
                } else {
                    $this->error("  ✗ Subscription #{$id}: " . ($result['message'] ?? 'Unknown error'));
                }
            }

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        // Dispatch job
        if ($subscriptionId) {
            dispatch(new ProcessRecurringPaymentsJob((int) $subscriptionId));
            $this->info("SUMIT recurring payment job dispatched for subscription #{$subscriptionId}");
        } else {
            dispatch(new ProcessRecurringPaymentsJob());
            $this->info('SUMIT recurring payments job dispatched');
        }

        return Command::SUCCESS;
    }
}
