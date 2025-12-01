<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

/**
 * Trait providing default implementation for Invoiceable interface.
 *
 * This trait provides sensible defaults for working with SUMIT invoices.
 * Models using this trait should have the following attributes:
 * - sumit_document_id (int)
 * - sumit_document_type (int)
 * - invoice_number (string)
 * - total_amount (float)
 * - currency (string)
 * - sumit_download_url (string)
 * - sumit_payment_url (string)
 * - sumit_is_closed (bool)
 */
trait HasSumitInvoice
{
    /**
     * Check if this invoice is a SUMIT invoice.
     */
    public function isSumitInvoice(): bool
    {
        return ! empty($this->sumit_document_id);
    }

    /**
     * Get the SUMIT document type name in Hebrew.
     */
    public function getSumitDocumentTypeName(): string
    {
        if (! $this->isSumitInvoice()) {
            return 'לא מסומית';
        }

        return match ($this->sumit_document_type) {
            1 => 'חשבונית מס',
            2 => 'חשבונית מס/קבלה',
            3 => 'תעודת זיכוי',
            4 => 'חשבונית עסקה',
            5 => 'קבלה',
            6 => 'תעודת משלוח',
            default => 'לא ידוע',
        };
    }

    /**
     * Check if this document is a credit note.
     */
    public function isCreditNote(): bool
    {
        return $this->sumit_document_type === 3;
    }

    /**
     * Get the SUMIT document ID.
     */
    public function getSumitDocumentId(): ?int
    {
        return $this->sumit_document_id ?? null;
    }

    /**
     * Get the invoice number.
     */
    public function getInvoiceNumber(): string
    {
        return $this->invoice_number ?? '';
    }

    /**
     * Get the total amount.
     */
    public function getTotalAmount(): float
    {
        return (float) ($this->total_amount ?? 0);
    }

    /**
     * Get the currency code.
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'ILS';
    }

    /**
     * Get the SUMIT download URL for the PDF.
     */
    public function getSumitDownloadUrl(): ?string
    {
        return $this->sumit_download_url ?? null;
    }

    /**
     * Get the SUMIT payment URL.
     */
    public function getSumitPaymentUrl(): ?string
    {
        return $this->sumit_payment_url ?? null;
    }

    /**
     * Check if the invoice is closed (paid).
     */
    public function isClosed(): bool
    {
        return (bool) ($this->sumit_is_closed ?? false);
    }

    /**
     * Get the associated client.
     *
     * This method should be overridden by the implementing model
     * to return the correct relationship.
     */
    public function getClient(): ?\OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer
    {
        // Try to get from 'client' relationship
        if (method_exists($this, 'client') && $this->relationLoaded('client')) {
            return $this->client;
        }

        // Try to load relationship
        if (method_exists($this, 'client')) {
            return $this->client()->first();
        }

        return null;
    }
}
