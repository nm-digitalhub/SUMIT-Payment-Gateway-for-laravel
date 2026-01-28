<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Bytexr\QueueableBulkActions\Filament\Actions\ActionResponse;
use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

/**
 * Bulk Payable Mapping Activate Job
 *
 * Queueable bulk action for activating Payable field mappings asynchronously.
 * Sets `is_active = true` for selected mappings, enabling them for use in
 * payment processing and checkout forms.
 *
 * ## Flow
 *
 * ```
 * User selects mappings in Filament → Clicks "Activate"
 *     ↓
 * QueueableBulkAction dispatches BulkPayableMappingActivateJob
 *     ↓
 * For each selected mapping:
 *     1. Update is_active to true (idempotent)
 *     2. Return ActionResponse::success()
 *     ↓
 * User receives real-time progress updates via Livewire polling
 * User sees success/failure notification when complete
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
 * This job is idempotent - if a mapping is already active, it returns success
 * without throwing an exception. The Application Layer decides how to interpret
 * the result (success vs. no-op) based on its business logic.
 *
 * **Architectural Principle**: The Package reports what it did (or didn't do),
 * the Application decides what it means.
 *
 * ## Response Contract
 *
 * **Success**: `ActionResponse::success($record, null, [])`
 * - The package successfully updated the record OR it was already in the desired state
 * - Application queries the record to determine the actual state if needed
 *
 * **Failure**: `ActionResponse::failure($record, $message, ['exception' => $class])`
 * - Database error, connection error, or other technical failure
 * - Application handles error display and retry logic
 *
 * The response metadata contains only domain-agnostic technical information
 * (mapping_id for logging, exception class for error handling).
 *
 * Failure response:
 * ```php
 * [
 *     'mapping_id' => 1,
 *     'exception' => 'Illuminate\\Database\\QueryException',
 * ]
 * ```
 *
 * ## Architectural Principle
 *
 * **Package Role**: Execute database UPDATE, report domain-agnostic result.
 * **Application Role**: Interpret what the result means for the business.
 *
 * The Package does NOT embed application domain knowledge in responses:
 * - ❌ No `model_class` - Application knows its own models
 * - ❌ No `activated_at` timestamps - Application tracks business events
 * - ❌ No `skipped` flags - Application decides success vs. no-op
 *
 * ## Error Handling
 *
 * - **Already active**: Returns `ActionResponse::success()` (idempotent)
 * - **Database connection error**: Retries via shouldRetryRecord (QueryException)
 * - **Record not found**: Returns `ActionResponse::failure()` without retry
 *
 * ## Filament Integration
 *
 * Used in `PayableMappingsTableWidget`:
 * ```php
 * QueueableBulkAction::make('activate_mappings')
 *     ->label('Activate')
 *     ->job(BulkPayableMappingActivateJob::class)
 *     ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
 *     ->icon('heroicon-o-check-circle')
 *     ->color('success')
 *     ->requiresConfirmation(false) // Safe operation, no confirmation needed
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
 * @see docs/QUEUEABLE_BULK_ACTIONS_INTEGRATION.md
 */
class BulkPayableMappingActivateJob extends BaseBulkActionJob
{
    /**
     * Handle the activation of a single PayableFieldMapping record.
     *
     * @param  PayableFieldMapping  $record  The mapping to activate
     */
    protected function handleRecord($record): ActionResponse
    {
        try {
            // Activate the mapping (idempotent - no-op if already active)
            $record->update(['is_active' => true]);

            // Return domain-agnostic success
            // Application can query $record->is_active if it needs to know if state changed
            return ActionResponse::success();
        } catch (\Throwable) {
            return ActionResponse::failure();
        }
    }

    /**
     * Determine if the record should be retried on failure.
     * Retry on database connection errors, but not on validation errors.
     *
     * @param  PayableFieldMapping  $record
     */
    protected function shouldRetryRecord($record, \Throwable $exception): bool
    {
        // Retry database connection errors
        return $exception instanceof \Illuminate\Database\QueryException
            || $exception instanceof \PDOException;
    }
}
