<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

/**
 * Bulk Subscription Cancel Job
 *
 * Queueable bulk action for cancelling multiple subscriptions asynchronously.
 * Processes each subscription through `SubscriptionService::cancel()` with validation
 * and error handling for individual records.
 *
 * ## Filament v5 Migration (v2.4.0)
 *
 * Migrated from bytexr QueueableBulkAction to native Laravel Bus::batch().
 * Uses native Laravel queue with ShouldQueue interface.
 *
 * ## Flow
 *
 * ```
 * User selects subscriptions in Filament → Clicks "Cancel Selected"
 *     ↓
 * Bus::batch dispatches BulkSubscriptionCancelJob for each record
 *     ↓
 * For each selected subscription:
 *     1. Check if subscription can be cancelled (canBeCancelled())
 *     2. Call SubscriptionService::cancel($subscription, 'Bulk cancellation')
 *     3. Exceptions are caught by BaseBulkActionJob and logged
 *     ↓
 * Batch completion notification shows success/failure count
 * ```
 *
 * ## Validation
 *
 * Each subscription is validated before cancellation:
 * - Checks `canBeCancelled()` method on Subscription model
 * - Throws exception if validation fails (no retry)
 * - Non-cancellable subscriptions don't block the batch (individual record failure)
 *
 * ## Error Handling
 *
 * - **Validation errors**: "Subscription cannot be cancelled" → no retry (throws exception)
 * - **API errors**: SUMIT API failure → retry (via shouldRetry)
 * - **Network errors**: Connection timeout → retry
 *
 * ## Filament Integration
 *
 * Used in `SubscriptionResource`:
 * ```php
 * use Filament\Actions\BulkAction;
 * use Illuminate\Support\Facades\Bus;
 *
 * BulkAction::make('cancel_selected')
 *     ->label('Cancel Selected')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records
 *                 ->filter(fn ($record) => $record->canBeCancelled())
 *                 ->map(fn ($record) => new BulkSubscriptionCancelJob($record))
 *         )->dispatch();
 *     })
 *     ->requiresConfirmation()
 *     ->color('danger');
 * ```
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\SubscriptionService::cancel()
 * @see \OfficeGuy\LaravelSumitGateway\Models\Subscription::canBeCancelled()
 * @see \OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BaseBulkActionJob
 */
class BulkSubscriptionCancelJob extends BaseBulkActionJob
{
    /**
     * Process subscription cancellation.
     *
     * @param  Subscription  $record
     */
    protected function process(mixed $record): void
    {
        // בדיקה אם המנוי יכול להיות מבוטל
        if (! $record->canBeCancelled()) {
            throw new \RuntimeException('Subscription cannot be cancelled');
        }

        // קריאה ל-Service לביטול המנוי
        SubscriptionService::cancel($record, 'Bulk cancellation');
    }
}
