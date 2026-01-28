<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Bytexr\QueueableBulkActions\Filament\Actions\ActionResponse;
use Bytexr\QueueableBulkActions\Jobs\BulkActionJob;
use Illuminate\Support\Facades\Log;

/**
 * Base Bulk Action Job
 *
 * Abstract base class for all queueable bulk action jobs in the SUMIT Gateway package.
 * Extends `Bytexr\QueueableBulkActions\Jobs\BulkActionJob` to provide:
 * - Dynamic queue configuration from `config/officeguy.php`
 * - Exponential backoff retry strategy
 * - Intelligent failure logging (logs only failures, not every record)
 * - Per-record retry control (API errors = retry, validation errors = no retry)
 *
 * ## Architecture
 *
 * This class implements the **Template Method Pattern**:
 * - `processRecord()` is the template method (handles logging, telemetry)
 * - `handleRecord()` is the abstract method (subclasses implement business logic)
 *
 * ## Queue Configuration
 *
 * Queue settings are loaded from `config/officeguy.php`:
 * ```php
 * 'bulk_actions' => [
 *     'queue' => 'officeguy-bulk-actions',  // Dedicated queue
 *     'connection' => 'redis',                // Queue connection
 *     'timeout' => 3600,                      // 1 hour max
 *     'tries' => 3,                           // 3 attempts
 * ]
 * ```
 *
 * ## Retry Strategy
 *
 * **Exponential Backoff**: `[60, 300, 900]` = 1min, 5min, 15min
 *
 * This gives external services (SUMIT API, email providers) time to recover
 * from transient failures without overwhelming them with immediate retries.
 *
 * ## Intelligent Retry Control
 *
 * `shouldRetryRecord()` distinguishes between:
 * - **Retryable errors**: API failures, network issues (GuzzleException, ConnectionException)
 * - **Non-retryable errors**: Validation errors, business logic failures
 *
 * Example: If a subscription is already cancelled, retrying won't help → don't retry.
 *
 * ## Telemetry & Logging
 *
 * Only failures are logged to reduce log volume on large batches:
 * ```php
 * Log::warning('Bulk action record failed', [
 *     'job' => 'BulkSubscriptionCancelJob',
 *     'record_id' => 123,
 *     'error' => 'Subscription already cancelled',
 * ]);
 * ```
 *
 * ## Subclass Implementation
 *
 * Subclasses only need to implement `handleRecord()`:
 * ```php
 * protected function handleRecord($record): ActionResponse
 * {
 *     try {
 *         $result = SubscriptionService::cancel($record);
 *         return ActionResponse::success($record, null, ['cancelled_at' => now()]);
 *     } catch (\Throwable $e) {
 *         return ActionResponse::failure($record, $e->getMessage());
 *     }
 * }
 * ```
 *
 * ## Integration with Filament
 *
 * Jobs are triggered from Filament resources using `QueueableBulkAction`:
 * ```php
 * QueueableBulkAction::make('cancel_selected')
 *     ->job(BulkSubscriptionCancelJob::class)
 *     ->visible(fn () => config('officeguy.bulk_actions.enabled'))
 * ```
 *
 * ## Supervisor Configuration
 *
 * Recommended supervisor configuration for production:
 * ```ini
 * [program:officeguy-bulk-actions]
 * process_name=%(program_name)s_%(process_num)02d
 * command=php /path/to/artisan queue:work redis --queue=officeguy-bulk-actions --sleep=3 --tries=3 --timeout=3600
 * numprocs=3
 * autostart=true
 * autorestart=true
 * ```
 *
 * @see \Bytexr\QueueableBulkActions\Jobs\BulkActionJob
 * @see docs/QUEUEABLE_BULK_ACTIONS_INTEGRATION.md
 */
abstract class BaseBulkActionJob extends BulkActionJob
{
    /**
     * Queue configuration from config (not hardcoded).
     */
    public string $queue;

    public string $connection;

    /**
     * Constructor - sets queue configuration dynamically.
     */
    public function __construct()
    {
        $this->queue = config('officeguy.bulk_actions.queue', 'officeguy-bulk-actions');
        $this->connection = config('officeguy.bulk_actions.connection');

        parent::__construct();
    }

    /**
     * Number of attempts for the job.
     */
    public int $tries = 3;

    /**
     * Exponential backoff strategy.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }

    /**
     * Maximum allowed time for the job (in seconds).
     */
    public int $timeout = 3600;

    /**
     * Enhanced processRecord with telemetry.
     *
     * Logs only failures to reduce log volume.
     *
     * @param  mixed  $record
     */
    protected function processRecord($record): ActionResponse
    {
        $result = $this->handleRecord($record);

        // Log only failures (reduce log volume)
        if (! $result->isSuccess()) {
            Log::warning('Bulk action record failed', [
                'job' => class_basename($this),
                'record_id' => $record->id,
                'record_type' => $record::class,
                'error' => $result->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Subclasses implement this instead of processRecord.
     *
     * @param  mixed  $record
     */
    abstract protected function handleRecord($record): ActionResponse;

    /**
     * Control retry behavior per-record.
     *
     * Retry API/network errors, but not validation/business logic errors.
     *
     * @param  mixed  $record
     */
    protected function shouldRetryRecord($record, \Throwable $exception): bool
    {
        // Retry API/network errors, but not validation/business logic errors
        return $exception instanceof \GuzzleHttp\Exception\GuzzleException
            || $exception instanceof \Illuminate\Http\Client\ConnectionException;
    }
}
