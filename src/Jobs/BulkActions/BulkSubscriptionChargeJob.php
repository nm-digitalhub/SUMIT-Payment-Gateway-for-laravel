<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

/**
 * Bulk Subscription Charge Job
 *
 * Queueable bulk action for charging subscriptions immediately (early charge).
 * Processes each subscription through `SubscriptionService::processRecurringCharge()`
 * to trigger payment collection before the scheduled billing date.
 *
 * ## Filament v5 Migration (v2.4.0)
 *
 * Migrated from bytexr QueueableBulkAction to native Laravel Bus::batch().
 * Uses native Laravel queue with ShouldQueue interface.
 *
 * ## Flow
 *
 * ```
 * User selects subscriptions in Filament → Clicks "Charge Now"
 *     ↓
 * Bus::batch dispatches BulkSubscriptionChargeJob for each record
 *     ↓
 * For each selected subscription:
 *     1. Check if subscription can be charged (canBeCharged())
 *     2. Verify recurring_id exists (required for SUMIT API)
 *     3. Call SubscriptionService::processRecurringCharge($subscription)
 *     4. Exceptions are caught by BaseBulkActionJob and logged
 *     ↓
 * Batch completion notification shows success/failure count
 * ```
 *
 * ## Use Cases
 *
 * - **Early renewal**: Charge subscriptions before scheduled date (customer request)
 * - **Payment method update**: Recharge after updating payment details
 * - **Retry failed charges**: Re-attempt failed recurring charges
 * - **Manual billing**: Trigger billing for special circumstances
 *
 * ## Validation
 *
 * Each subscription is validated before charging:
 * - Checks `canBeCharged()` method on Subscription model
 * - Verifies `recurring_id` is set (required for SUMIT recurring charges)
 * - Throws exception if validation fails (no retry)
 *
 * ## SUMIT API Integration
 *
 * Calls `SubscriptionService::processRecurringCharge()` which:
 * 1. Queries SUMIT API with `recurring_id`
 * 2. Triggers immediate charge using saved token
 * 3. Updates subscription status based on response
 * 4. Creates OfficeGuyTransaction record for the charge
 * 5. Returns `['success' => bool, 'message' => string, 'code' => string]`
 *
 * ## Error Handling
 *
 * - **Cannot be charged**: Throws exception (no retry)
 * - **No recurring_id**: Throws exception (no retry)
 * - **Insufficient funds**: Throws exception (API response)
 * - **Token expired**: Throws exception (API response)
 * - **API timeout**: Retries via shouldRetry (GuzzleException)
 *
 * ## Filament Integration
 *
 * Used in `SubscriptionResource`:
 * ```php
 * use Filament\Actions\BulkAction;
 * use Illuminate\Support\Facades\Bus;
 *
 * BulkAction::make('charge_now')
 *     ->label('Charge Now')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records
 *                 ->filter(fn ($record) => $record->canBeCharged() && $record->recurring_id)
 *                 ->map(fn ($record) => new BulkSubscriptionChargeJob($record))
 *         )->dispatch();
 *     })
 *     ->requiresConfirmation()
 *     ->color('danger'); // Warning color for financial action
 * ```
 *
 * ## Security Considerations
 *
 * - **Requires confirmation**: Modal confirmation prevents accidental charges
 * - **Audit trail**: All charges logged in OfficeGuyTransaction table
 * - **Permission check**: Requires `charge_subscriptions` permission
 * - **Notifications**: Customer receives email confirmation of charge
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\SubscriptionService::processRecurringCharge()
 * @see \OfficeGuy\LaravelSumitGateway\Models\Subscription::canBeCharged()
 * @see \OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BaseBulkActionJob
 */
class BulkSubscriptionChargeJob extends BaseBulkActionJob
{
    /**
     * Process subscription charge.
     *
     * @param  Subscription  $record
     */
    protected function process(mixed $record): void
    {
        // בדיקה אם המנוי יכול להיות מחויב
        if (! $record->canBeCharged()) {
            throw new \RuntimeException('Subscription cannot be charged');
        }

        // בדיקה שיש recurring_id
        if (! $record->recurring_id) {
            throw new \RuntimeException('Subscription has no recurring_id');
        }

        // קריאה ל-Service לחיוב המנוי
        $result = SubscriptionService::processRecurringCharge($record);

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'Failed to charge subscription');
        }
    }
}
