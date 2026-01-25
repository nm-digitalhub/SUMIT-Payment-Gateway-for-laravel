<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Bytexr\QueueableBulkActions\Filament\Actions\ActionResponse;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

/**
 * Bulk Subscription Charge Job
 *
 * Queueable bulk action for charging subscriptions immediately (early charge).
 * Processes each subscription through `SubscriptionService::processRecurringCharge()`
 * to trigger payment collection before the scheduled billing date.
 *
 * ## Flow
 *
 * ```
 * User selects subscriptions in Filament → Clicks "Charge Now"
 *     ↓
 * QueueableBulkAction dispatches BulkSubscriptionChargeJob
 *     ↓
 * For each selected subscription:
 *     1. Check if subscription can be charged (canBeCharged())
 *     2. Verify recurring_id exists (required for SUMIT API)
 *     3. Call SubscriptionService::processRecurringCharge($subscription)
 *     4. Return ActionResponse::success() or ActionResponse::failure()
 *     ↓
 * User receives real-time progress updates via Livewire polling
 * User sees success/failure notification when complete
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
 * - Returns failure with descriptive message if validation fails
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
 * - **Cannot be charged**: Returns failure with `reason='cannot_be_charged'`
 * - **No recurring_id**: Returns failure with `reason='no_recurring_id'`
 * - **Insufficient funds**: Returns failure with `code='insufficient_funds'`
 * - **Token expired**: Returns failure with `code='token_expired'`
 * - **API timeout**: Retries via shouldRetryRecord (GuzzleException)
 *
 * ## Response Metadata
 *
 * Success response includes:
 * ```php
 * [
 *     'subscription_id' => 123,
 *     'charged_at' => '2026-01-22T10:30:00+00:00',
 * ]
 * ```
 *
 * Failure response includes:
 * ```php
 * [
 *     'subscription_id' => 123,
 *     'error_code' => 'insufficient_funds', // or other codes
 * ]
 * ```
 *
 * ## Filament Integration
 *
 * Used in `SubscriptionResource`:
 * ```php
 * QueueableBulkAction::make('charge_now')
 *     ->label('Charge Now')
 *     ->job(BulkSubscriptionChargeJob::class)
 *     ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
 *     ->successNotificationTitle(__('officeguy::messages.bulk_charge_success'))
 *     ->failureNotificationTitle(__('officeguy::messages.bulk_charge_partial'))
 *     ->modalDescription(__('officeguy::messages.bulk_charge_desc'))
 *     ->color('danger') // Warning color for financial action
 *     ->requiresConfirmation(true)
 * ```
 *
 * ## Translation Keys
 *
 * - `officeguy::messages.subscription_cannot_be_charged` - Validation failure
 * - `officeguy::messages.bulk_charge_success` - "Subscriptions charged successfully"
 * - `officeguy::messages.bulk_charge_partial` - "Some charges failed"
 * - `officeguy::messages.bulk_charge_desc` - Confirmation modal description
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
 * @see docs/QUEUEABLE_BULK_ACTIONS_INTEGRATION.md
 */
class BulkSubscriptionChargeJob extends BaseBulkActionJob
{
    /**
     * Handle subscription charge.
     *
     * @param  Subscription  $record
     */
    protected function handleRecord($record): ActionResponse
    {
        // בדיקה אם המנוי יכול להיות מחויב
        if (!$record->canBeCharged()) {
            return ActionResponse::failure(
                $record,
                __('officeguy::messages.subscription_cannot_be_charged'),
                ['subscription_id' => $record->id, 'reason' => 'cannot_be_charged']
            );
        }

        // בדיקה שיש recurring_id
        if (!$record->recurring_id) {
            return ActionResponse::failure(
                $record,
                __('No recurring ID found for subscription'),
                ['subscription_id' => $record->id, 'reason' => 'no_recurring_id']
            );
        }

        try {
            // קריאה ל-Service לחיוב המנוי
            $result = SubscriptionService::processRecurringCharge($record);

            if ($result['success'] ?? false) {
                return ActionResponse::success(
                    $record,
                    null,
                    ['subscription_id' => $record->id, 'charged_at' => now()->toIso8601String()]
                );
            }

            return ActionResponse::failure(
                $record,
                $result['message'] ?? 'Unknown error',
                ['subscription_id' => $record->id, 'error_code' => $result['code'] ?? 'unknown']
            );
        } catch (\Throwable $e) {
            return ActionResponse::failure(
                $record,
                $e->getMessage(),
                ['subscription_id' => $record->id, 'exception' => get_class($e)]
            );
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
