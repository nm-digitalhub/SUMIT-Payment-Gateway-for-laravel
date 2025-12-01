<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Contracts;

/**
 * Interface for models that represent invoices/documents from SUMIT.
 *
 * Invoices are associated with customers but are not customers themselves.
 * Use getClient() to access the related customer.
 */
interface Invoiceable
{
    /**
     * Check if this invoice is a SUMIT invoice.
     */
    public function isSumitInvoice(): bool;

    /**
     * Get the SUMIT document type name in Hebrew.
     */
    public function getSumitDocumentTypeName(): string;

    /**
     * Check if this document is a credit note.
     */
    public function isCreditNote(): bool;

    /**
     * Get the SUMIT document ID.
     */
    public function getSumitDocumentId(): ?int;

    /**
     * Get the invoice number.
     */
    public function getInvoiceNumber(): string;

    /**
     * Get the total amount.
     */
    public function getTotalAmount(): float;

    /**
     * Get the currency code (ILS, USD, EUR, GBP).
     */
    public function getCurrency(): string;

    /**
     * Get the SUMIT download URL for the PDF.
     */
    public function getSumitDownloadUrl(): ?string;

    /**
     * Get the SUMIT payment URL.
     */
    public function getSumitPaymentUrl(): ?string;

    /**
     * Check if the invoice is closed (paid).
     */
    public function isClosed(): bool;

    /**
     * Get the associated client (must implement HasSumitCustomer).
     */
    public function getClient(): ?HasSumitCustomer;
}
