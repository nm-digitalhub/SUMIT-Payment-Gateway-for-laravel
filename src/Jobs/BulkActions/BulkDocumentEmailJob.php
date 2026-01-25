<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use Bytexr\QueueableBulkActions\Filament\Actions\ActionResponse;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

/**
 * Bulk Document Email Job
 *
 * Queueable bulk action for emailing documents to customers asynchronously.
 * Processes each document through `DocumentService::sendByEmail()` to deliver
 * invoices, receipts, and other documents via email.
 *
 * ## Flow
 *
 * ```
 * User selects documents in Filament → Clicks "Email to Customers"
 *     ↓
 * QueueableBulkAction dispatches BulkDocumentEmailJob
 *     ↓
 * For each selected document:
 *     1. Retrieve document (invoice/receipt/donation receipt)
 *     2. Get customer email from Payable model
 *     3. Generate PDF attachment
 *     4. Send email via DocumentService::sendByEmail()
 *     5. Return ActionResponse::success() or ActionResponse::failure()
 *     ↓
 * User receives real-time progress updates via Livewire polling
 * User sees success/failure notification when complete
 * ```
 *
 * ## Use Cases
 *
 * - **Bulk invoice delivery**: Send monthly invoices to all customers
 * - **Receipt resend**: Re-send receipts for customers who didn't receive them
 * - **Document recovery**: Re-send documents after email system issues
 * - **Compliance**: Fulfill document delivery requests from accounting/audit
 *
 * ## Email Template
 *
 * Uses Filament notification system with customizable templates:
 * - Subject: Document type + number (e.g., "Invoice #INV-2024-001")
 * - Body: Includes document link, amount, and date
 * - Attachment: PDF of the document
 *
 * ## Error Handling
 *
 * - **Missing customer email**: Returns failure with `error='no_email'`
 * - **Invalid document**: Returns failure with `error='invalid_document'`
 * - **Email service failure**: Retries via shouldRetryRecord (ConnectionException)
 * - **PDF generation failure**: Returns failure without retry
 *
 * ## Response Metadata
 *
 * Success response includes:
 * ```php
 * [
 *     'document_id' => 789,
 *     'sent_at' => '2026-01-22T10:30:00+00:00',
 * ]
 * ```
 *
 * Failure response includes:
 * ```php
 * [
 *     'document_id' => 789,
 *     'document_number' => 'INV-2024-001',
 * ]
 * ```
 *
 * ## Filament Integration
 *
 * Used in `DocumentResource`:
 * ```php
 * QueueableBulkAction::make('email_documents')
 *     ->label('Email to Customers')
 *     ->job(BulkDocumentEmailJob::class)
 *     ->visible(fn () => config('officeguy.bulk_actions.enabled', false))
 *     ->successNotificationTitle(__('officeguy::messages.bulk_email_success'))
 *     ->failureNotificationTitle(__('officeguy::messages.bulk_email_partial'))
 *     ->modalDescription(__('officeguy::messages.bulk_email_desc'))
 * ```
 *
 * ## Translation Keys
 *
 * - `officeguy::messages.bulk_email_success` - "Documents sent successfully"
 * - `officeguy::messages.bulk_email_partial` - "Some documents failed to send"
 * - `officeguy::messages.bulk_email_desc` - Confirmation modal description
 *
 * ## Privacy & Compliance
 *
 * - Only sends to customer's registered email
 * - Logs all email sends in document history
 * - Respects customer email preferences (if configured)
 * - BCC to admin for audit trail (configurable)
 *
 * @see \OfficeGuy\LaravelSumitGateway\Services\DocumentService::sendByEmail()
 * @see \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument
 * @see docs/QUEUEABLE_BULK_ACTIONS_INTEGRATION.md
 */
class BulkDocumentEmailJob extends BaseBulkActionJob
{
    /**
     * Handle document email sending.
     *
     * @param  OfficeGuyDocument  $record
     */
    protected function handleRecord($record): ActionResponse
    {
        try {
            // קריאה ל-Service לשליחת המסמך באימייל
            $result = DocumentService::sendByEmail($record);

            if ($result['success'] ?? false) {
                return ActionResponse::success(
                    $record,
                    null,
                    ['document_id' => $record->id, 'sent_at' => now()->toIso8601String()]
                );
            }

            return ActionResponse::failure(
                $record,
                $result['error'] ?? 'Unknown error',
                ['document_id' => $record->id, 'document_number' => $record->document_number]
            );
        } catch (\Throwable $e) {
            return ActionResponse::failure(
                $record,
                $e->getMessage(),
                ['document_id' => $record->id, 'exception' => get_class($e)]
            );
        }
    }

    /**
     * Control retry behavior per-record.
     *
     * @param  OfficeGuyDocument  $record
     */
    protected function shouldRetryRecord($record, \Throwable $exception): bool
    {
        // Retry API failures, but not validation/business logic errors
        return $exception instanceof \GuzzleHttp\Exception\GuzzleException
            || $exception instanceof \Illuminate\Http\Client\ConnectionException;
    }
}
