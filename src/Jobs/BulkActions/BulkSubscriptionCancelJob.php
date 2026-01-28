<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Bytexr\QueueableBulkActions\Filament\Actions\ActionResponse;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

/**
 * Bulk Subscription Cancel Job
 *
 * Queueable bulk action for cancelling multiple subscriptions asynchronously.
 * Processes each subscription through `SubscriptionService::cancel()` with validation
 * and error handling for individual records.
 *
 * ## Flow
 *
 * ```
 * User selects subscriptions in Filament → Clicks "Cancel Selected"
 *     ↓
 * QueueableBulkAction dispatches BulkSubscriptionCancelJob
 *     ↓
 * For each selected subscription:
 *     1. Check if subscription can be cancelled (canBeCancelled())
 *     2. Call SubscriptionService::cancel($subscription, 'Bulk cancellation')
 *     3. Return ActionResponse::success() or ActionResponse::failure()
 *     ↓
 * User receives real-time progress updates via Livewire polling
 * User sees success/failure notification when complete
 * ```
 *
 * ## Validation
 *
 * Each subscription is validated before cancellation:
 * - Checks `canBeCancelled()` method on Subscription model
 * - Returns failure with descriptive message if validation fails
 * - Non-cancellable subscriptions don't block the batch (individual record failure)
 *
 * ## Error Handling
 *
 * - **Validation errors**: "Subscription cannot be cancelled" → no retry
 * - **API errors**: SUMIT API failure → retry (via shouldRetryRecord)
 * - **Network errors**: Connection timeout → retry
 *
 * ## Response Metadata
 *
 * Success response includes:
 * ```php
 * [
 *     'subscription_id' => 123,
 *     'cancelled_at' => '2026-01-22T10:30:00+00:00',
 * ]
 * ```
 *
 * Failure response includes:
 * ```php
 * [
 *     'subscription_id' => 123,
 *     'reason' => 'invalid_status', // or 'exception' class name
 * ]
 * ```
 *
 * ## Filament Integration
 *
 * Used in `SubscriptionResource`:
 * ```php
 * QueueableBulkAction::make('cancel_selected')
 *     ->label('Cancel Selected')
 *     ->job(BulkSubscriptionCancelJob::class)
 *     ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
 *     ->successNotificationTitle(__('officeguy::messages.bulk_cancel_success'))
 *     ->failureNotificationTitle(__('officeguy::messages.bulk_cancel_partial'))
 * ```
 *
 * ## Translation Keys
 *
 * - `officeguy::messages.subscription_cannot_be_cancelled` - Shown for validation failures
 * - `officeguy::messages.bulk_cancel_success` - Shown when all subscriptions cancelled
 * - `officeguy::messages.bulk_cancel_partial` - Shown when some subscriptions failed
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\SubscriptionService::cancel()
 * @see \OfficeGuy\LaravelSumitGateway\Models\Subscription::canBeCancelled()
 * @see docs/QUEUEABLE_BULK_ACTIONS_INTEGRATION.md
 */
class BulkSubscriptionCancelJob extends BaseBulkActionJob
{
    /**
     * Handle subscription cancellation.
     *
     * @param  Subscription  $record
     */
    protected function handleRecord($record): ActionResponse
    {
        // בדיקה אם המנוי יכול להיות מבוטל
        if (! $record->canBeCancelled()) {
            return ActionResponse::failure();
        }

        try {
            // קריאה ל-Service לביטול המנוי
            SubscriptionService::cancel($record, 'Bulk cancellation');

            return ActionResponse::success();
        } catch (\Throwable) {
            return ActionResponse::failure();
        }
    }

    /**
     * Control retry behavior per-record.
     *
     * @param  Subscription  $record
     */
    protected function shouldRetryRecord($record, \Throwable $exception): bool
    {
        // Retry API failures, but not validation/business logic errors
        return $exception instanceof \GuzzleHttp\Exception\GuzzleException
            || $exception instanceof \Illuminate\Http\Client\ConnectionException;
    }
}
