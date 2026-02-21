<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

/**
 * Bulk Payable Mapping Activate Job
 *
 * Queueable bulk action for activating Payable field mappings asynchronously.
 * Sets `is_active = true` for selected mappings, enabling them for use in
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
 * User selects mappings in Filament → Clicks "Activate"
 *     ↓
 * Bus::batch dispatches BulkPayableMappingActivateJob for each record
 *     ↓
 * For each selected mapping:
 *     1. Update is_active to true (idempotent)
 *     2. Exceptions are caught by BaseBulkActionJob and logged
 *     ↓
 * Batch completion notification shows success/failure count
 * ```
 *
 * ## Use Cases
 *
 * - **Bulk activation**: Enable multiple mappings after testing
 * - **Feature toggle**: Activate mappings for new Payable types
 * - **Seasonal changes**: Activate mappings for seasonal products
 * - **Testing**: Re-activate mappings after maintenance
 *
 * ## Idempotency
 *
 * This job is idempotent - if a mapping is already active, the update succeeds
 * without throwing an exception.
 *
 * ## Error Handling
 *
 * - **Already active**: Succeeds silently (idempotent)
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
 * BulkAction::make('activate_mappings')
 *     ->label('Activate')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records->map(fn ($record) => new BulkPayableMappingActivateJob($record))
 *         )->dispatch();
 *     })
 *     ->icon('heroicon-o-check-circle')
 *     ->color('success')
 *     ->requiresConfirmation(false); // Safe operation
 * ```
 *
 * ## Database Impact
 *
 * - Direct UPDATE query on `payable_field_mappings` table
 * - No cascading effects (only boolean flag change)
 * - Takes effect immediately (no cache invalidation needed)
 *
 * @see \OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping
 * @see \OfficeGuy\LaravelSumitGateway\Filament\Widgets\PayableMappingsTableWidget
 * @see \OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BaseBulkActionJob
 */
class BulkPayableMappingActivateJob extends BaseBulkActionJob
{
    /**
     * Process the activation of a single PayableFieldMapping record.
     *
     * @param  PayableFieldMapping  $record  The mapping to activate
     */
    protected function process(mixed $record): void
    {
        // Activate the mapping (idempotent - no-op if already active)
        $record->update(['is_active' => true]);
    }
}
