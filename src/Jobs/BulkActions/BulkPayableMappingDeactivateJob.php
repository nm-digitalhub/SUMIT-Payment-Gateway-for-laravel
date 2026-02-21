<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

/**
 * Bulk Payable Mapping Deactivate Job
 *
 * Queueable bulk action for deactivating Payable field mappings asynchronously.
 * Sets `is_active = false` for selected mappings, disabling them from use in
 * payment processing and checkout forms.
 *
 * ## Filament v5 Migration (v2.4.0)
 *
 * Migrated from bytexr QueueableBulkAction to native Laravel Bus::batch().
 * Uses native Laravel queue with ShouldQueue interface.
 *
 * ## Flow
 *
 * ```
 * User selects mappings in Filament → Clicks "Deactivate"
 *     ↓
 * Bus::batch dispatches BulkPayableMappingDeactivateJob for each record
 *     ↓
 * For each selected mapping:
 *     1. Update is_active to false (idempotent)
 *     2. Exceptions are caught by BaseBulkActionJob and logged
 *     ↓
 * Batch completion notification shows success/failure count
 * ```
 *
 * ## Use Cases
 *
 * - **Bulk deactivation**: Disable multiple mappings for maintenance
 * - **Bug response**: Disable problematic mappings after bug reports
 * - **Feature rollback**: Temporarily disable mappings for deprecated features
 * - **Testing**: Deactivate mappings for testing payment flow without them
 *
 * ## Idempotency
 *
 * This job is idempotent - if a mapping is already inactive, the update succeeds
 * without throwing an exception.
 *
 * ## Error Handling
 *
 * - **Already inactive**: Succeeds silently (idempotent)
 * - **Database connection error**: Retries via shouldRetry (QueryException)
 * - **Record not found**: Throws exception (no retry)
 *
 * ## Filament Integration
 *
 * Used in `PayableMappingsTableWidget`:
 * ```php
 * use Filament\Actions\BulkAction;
 * use Illuminate\Support\Facades\Bus;
 *
 * BulkAction::make('deactivate_mappings')
 *     ->label('Deactivate')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records->map(fn ($record) => new BulkPayableMappingDeactivateJob($record))
 *         )->dispatch();
 *     })
 *     ->icon('heroicon-o-x-circle')
 *     ->color('warning')
 *     ->requiresConfirmation(false); // Safe operation
 * ```
 *
 * ## Database Impact
 *
 * - Direct UPDATE query on `payable_field_mappings` table
 * - No cascading effects (only boolean flag change)
 * - Takes effect immediately (no cache invalidation needed)
 *
 * ## Active Payments Consideration
 *
 * Deactivating a mapping does NOT affect:
 * - Existing transactions (already processed)
 * - Active subscriptions (use original mapping snapshot)
 * - In-progress checkouts (use mapping from checkout creation)
 *
 * Only NEW checkouts are affected by deactivated mappings.
 *
 * @see \OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping
 * @see \OfficeGuy\LaravelSumitGateway\Filament\Widgets\PayableMappingsTableWidget
 * @see \OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BaseBulkActionJob
 */
class BulkPayableMappingDeactivateJob extends BaseBulkActionJob
{
    /**
     * Process the deactivation of a single PayableFieldMapping record.
     *
     * @param  PayableFieldMapping  $record  The mapping to deactivate
     */
    protected function process(mixed $record): void
    {
        // Deactivate the mapping (idempotent - no-op if already inactive)
        $record->update(['is_active' => false]);
    }
}
