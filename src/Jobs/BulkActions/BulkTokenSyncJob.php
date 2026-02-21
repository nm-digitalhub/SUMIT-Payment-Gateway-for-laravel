<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\TokenService;

/**
 * Bulk Token Sync Job
 *
 * Queueable bulk action for synchronizing payment tokens from SUMIT API asynchronously.
 * Processes each token through `TokenService::syncTokenFromSumit()` to update
 * local token status and metadata from the remote SUMIT system.
 *
 * ## Filament v5 Migration (v2.4.0)
 *
 * Migrated from bytexr QueueableBulkAction to native Laravel Bus::batch().
 * Uses native Laravel queue with ShouldQueue interface.
 *
 * ## Flow
 *
 * ```
 * User selects tokens in Filament → Clicks "Sync from SUMIT"
 *     ↓
 * Bus::batch dispatches BulkTokenSyncJob for each record
 *     ↓
 * For each selected token:
 *     1. Call TokenService::syncTokenFromSumit($token)
 *     2. Update local token with SUMIT API response
 *     3. Exceptions are caught by BaseBulkActionJob and logged
 *     ↓
 * Batch completion notification shows success/failure count
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
 * - **Token not found in SUMIT**: Throws exception (no retry)
 * - **API authentication error**: Retries via shouldRetry (GuzzleException)
 * - **Network timeout**: Retries via shouldRetry (ConnectionException)
 * - **Invalid token format**: Throws exception (no retry)
 *
 * ## Filament Integration
 *
 * Used in `TokenResource`:
 * ```php
 * use Filament\Actions\BulkAction;
 * use Illuminate\Support\Facades\Bus;
 *
 * BulkAction::make('sync_all_from_sumit')
 *     ->label('Sync from SUMIT')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records->map(fn ($record) => new BulkTokenSyncJob($record))
 *         )->dispatch();
 *     })
 *     ->requiresConfirmation();
 * ```
 *
 * ## Performance Considerations
 *
 * - Each token sync requires a SUMIT API call
 * - API has rate limits: sync in batches of 100 or fewer tokens
 * - Use dedicated queue to avoid blocking other jobs
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\TokenService::syncTokenFromSumit()
 * @see \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken
 * @see \OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BaseBulkActionJob
 */
class BulkTokenSyncJob extends BaseBulkActionJob
{
    /**
     * Process token synchronization from SUMIT.
     *
     * @param  OfficeGuyToken  $record
     */
    protected function process(mixed $record): void
    {
        // קריאה ל-Service לסינכרון ה-token
        $result = TokenService::syncTokenFromSumit($record);

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'Failed to sync token from SUMIT');
        }
    }
}
