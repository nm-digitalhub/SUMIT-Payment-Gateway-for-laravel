<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs\BulkActions;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

/**
 * Bulk Document Email Job
 *
 * Queueable bulk action for emailing documents to customers asynchronously.
 * Processes each document through `DocumentService::sendByEmail()` to deliver
 * invoices, receipts, and other documents via email.
 *
 * ## Filament v5 Migration (v2.4.0)
 *
 * Migrated from bytexr QueueableBulkAction to native Laravel Bus::batch().
 * Uses native Laravel queue with ShouldQueue interface.
 *
 * ## Flow
 *
 * ```
 * User selects documents in Filament → Clicks "Email to Customers"
 *     ↓
 * Bus::batch dispatches BulkDocumentEmailJob for each record
 *     ↓
 * For each selected document:
 *     1. Retrieve document (invoice/receipt/donation receipt)
 *     2. Get customer email from Payable model
 *     3. Generate PDF attachment
 *     4. Send email via DocumentService::sendByEmail()
 *     5. Exceptions are caught by BaseBulkActionJob and logged
 *     ↓
 * Batch completion notification shows success/failure count
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
 * - **Missing customer email**: Throws exception (no retry)
 * - **Invalid document**: Throws exception (no retry)
 * - **Email service failure**: Retries via shouldRetry (ConnectionException)
 * - **PDF generation failure**: Throws exception (no retry)
 *
 * ## Filament Integration
 *
 * Used in `DocumentResource`:
 * ```php
 * use Filament\Actions\BulkAction;
 * use Illuminate\Support\Facades\Bus;
 *
 * BulkAction::make('email_documents')
 *     ->label('Email to Customers')
 *     ->action(function ($records) {
 *         Bus::batch(
 *             $records->map(fn ($record) => new BulkDocumentEmailJob($record))
 *         )->dispatch();
 *     })
 *     ->requiresConfirmation();
 * ```
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
 * @see \OfficeGuy\LaravelSumitGateway\Jobs\BulkActions\BaseBulkActionJob
 */
class BulkDocumentEmailJob extends BaseBulkActionJob
{
    /**
     * Process document email sending.
     *
     * @param  OfficeGuyDocument  $record
     */
    protected function process(mixed $record): void
    {
        // קריאה ל-Service לשליחת המסמך באימייל
        $result = DocumentService::sendByEmail($record);

        if (!($result['success'] ?? false)) {
            throw new \RuntimeException($result['message'] ?? 'Failed to send document email');
        }
    }
}
