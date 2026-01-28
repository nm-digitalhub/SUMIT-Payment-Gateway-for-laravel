<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Bytexr\QueueableBulkActions\Filament\Actions\ActionResponse;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\TokenService;

/**
 * Bulk Token Sync Job
 *
 * Queueable bulk action for synchronizing payment tokens from SUMIT API asynchronously.
 * Processes each token through `TokenService::syncTokenFromSumit()` to update
 * local token status and metadata from the remote SUMIT system.
 *
 * ## Flow
 *
 * ```
 * User selects tokens in Filament → Clicks "Sync from SUMIT"
 *     ↓
 * QueueableBulkAction dispatches BulkTokenSyncJob
 *     ↓
 * For each selected token:
 *     1. Call TokenService::syncTokenFromSumit($token)
 *     2. Update local token with SUMIT API response
 *     3. Return ActionResponse::success() or ActionResponse::failure()
 *     ↓
 * User receives real-time progress updates via Livewire polling
 * User sees success/failure notification when complete
 * ```
 *
 * ## Use Cases
 *
 * - **Batch refresh**: Update multiple tokens after SUMIT system maintenance
 * - **Status reconciliation**: Sync local token status with SUMIT reality
 * - **Metadata refresh**: Update token details (expiry, last 4 digits, etc.)
 * - **Data repair**: Fix tokens with stale or corrupted data
 *
 * ## API Integration
 *
 * Calls `TokenService::syncTokenFromSumit()` which:
 * 1. Queries SUMIT API for token details
 * 2. Updates local OfficeGuyToken model
 * 3. Returns `['success' => bool, 'message' => string, 'code' => string]`
 *
 * ## Error Handling
 *
 * - **Token not found in SUMIT**: Returns failure with `code='token_not_found'`
 * - **API authentication error**: Returns failure with `code='auth_failed'`
 * - **Network timeout**: Retries via shouldRetryRecord (GuzzleException)
 * - **Invalid token format**: Returns failure without retry
 *
 * ## Response Metadata
 *
 * Success response includes:
 * ```php
 * [
 *     'token_id' => 456,
 *     'synced_at' => '2026-01-22T10:30:00+00:00',
 * ]
 * ```
 *
 * Failure response includes:
 * ```php
 * [
 *     'token_id' => 456,
 *     'error_code' => 'token_not_found', // or 'auth_failed', 'api_error'
 * ]
 * ```
 *
 * ## Filament Integration
 *
 * Used in `TokenResource`:
 * ```php
 * QueueableBulkAction::make('sync_all_from_sumit')
 *     ->label('Sync from SUMIT')
 *     ->job(BulkTokenSyncJob::class)
 *     ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
 *     ->successNotificationTitle(__('officeguy::messages.bulk_sync_success'))
 *     ->failureNotificationTitle(__('officeguy::messages.bulk_sync_partial'))
 *     ->modalDescription(__('officeguy::messages.bulk_sync_desc'))
 * ```
 *
 * ## Translation Keys
 *
 * - `officeguy::messages.bulk_sync_success` - "Token sync completed"
 * - `officeguy::messages.bulk_sync_partial` - "Some tokens failed to sync"
 * - `officeguy::messages.bulk_sync_desc` - Confirmation modal description
 *
 * ## Performance Considerations
 *
 * - Each token sync requires a SUMIT API call
 * - API has rate limits: sync in batches of 100 or fewer tokens
 * - Use dedicated queue to avoid blocking other jobs
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\TokenService::syncTokenFromSumit()
 * @see \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken
 * @see docs/QUEUEABLE_BULK_ACTIONS_INTEGRATION.md
 */
class BulkTokenSyncJob extends BaseBulkActionJob
{
    /**
     * Handle token synchronization from SUMIT.
     *
     * @param  OfficeGuyToken  $record
     */
    protected function handleRecord($record): ActionResponse
    {
        try {
            // קריאה ל-Service לסינכרון ה-token
            $result = TokenService::syncTokenFromSumit($record);

            if ($result['success'] ?? false) {
                return ActionResponse::success();
            }

            return ActionResponse::failure();
        } catch (\Throwable) {
            return ActionResponse::failure();
        }
    }

    /**
     * Control retry behavior per-record.
     *
     * @param  OfficeGuyToken  $record
     */
    protected function shouldRetryRecord($record, \Throwable $exception): bool
    {
        // Retry API failures, but not validation/business logic errors
        return $exception instanceof \GuzzleHttp\Exception\GuzzleException
            || $exception instanceof \Illuminate\Http\Client\ConnectionException;
    }
}
