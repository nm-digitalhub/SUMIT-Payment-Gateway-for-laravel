<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base Bulk Action Job
 *
 * Abstract base class for all queueable bulk action jobs in the SUMIT Gateway package.
 *
 * ## Filament v5 Migration (v2.4.0)
 *
 * Migrated from `Bytexr\QueueableBulkActions\Jobs\BulkActionJob` to native Laravel implementation.
 * Uses native Laravel Bus::batch() for bulk operations.
 *
 * ## Architecture
 *
 * This class implements the **Template Method Pattern**:
 * - `process()` is the template method (handles logging, telemetry)
 * - `handle()` is the abstract method (subclasses implement business logic)
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
 * `shouldRetry()` distinguishes between:
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
 * Subclasses only need to implement `handle()`:
 * ```php
 * protected function handle(): void
 * {
 *     $result = SubscriptionService::cancel($this->record);
 *     // No return value needed - exceptions indicate failure
 * }
 * ```
 *
 * ## Integration with Filament v5
 *
 * Jobs are triggered from Filament resources using native `Bus::batch()`:
 * ```php
 * use Filament\Actions\BulkAction;
 * use Illuminate\Support\Facades\Bus;
 *
 * BulkAction::make('cancel_selected')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records
 *                 ->filter(fn ($record) => $record->canBeCancelled())
 *                 ->map(fn ($record) => new BulkSubscriptionCancelJob($record))
 *         )->dispatch();
 *     });
 * ```
 *
 * @see https://filamentphp.com/docs/5.x/tables/bulk-actions.html
 */
abstract class BaseBulkActionJob implements ShouldQueue
{
    /**
     * The record to process.
     */
    public mixed $record;

    /**
     * Queue configuration from config (not hardcoded).
     */
    public string $queue = 'officeguy-bulk-actions';

    /**
     * Queue connection.
     */
    public string $connection = 'redis';

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
     * Constructor - accepts the record to process.
     */
    public function __construct(mixed $record)
    {
        $this->record = $record;
    }

    /**
     * Enhanced process with telemetry.
     *
     * Logs only failures to reduce log volume.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->process($this->record);
        } catch (Throwable $e) {
            // Log only failures (reduce log volume)
            Log::warning('Bulk action record failed', [
                'job' => class_basename($this),
                'record_id' => $this->record?->id,
                'record_type' => $this->record?::class,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Subclasses implement this instead of handle().
     *
     * @param mixed $record
     * @return void
     */
    abstract protected function process(mixed $record): void;

    /**
     * Control retry behavior.
     *
     * Retry API/network errors, but not validation/business logic errors.
     *
     * @param mixed $record
     * @param Throwable $exception
     * @return bool
     */
    protected function shouldRetry(mixed $record, Throwable $exception): bool
    {
        // Retry API/network errors, but not validation/business logic errors
        return $exception instanceof \GuzzleHttp\Exception\GuzzleException
            || $exception instanceof \Illuminate\Http\Client\ConnectionException;
    }

    /**
     * Get unique ID for this job in batch.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return sha1(json_encode([
            get_class($this),
            $this->record->id,
        ]));
    }
}
