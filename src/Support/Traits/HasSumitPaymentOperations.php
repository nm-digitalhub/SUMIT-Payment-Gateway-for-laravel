<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Trait HasSumitPaymentOperations
 *
 * Provides SUMIT payment-related helper methods for Payable models.
 * Includes methods for accessing payment documents, tokens, transactions,
 * and determining payment operations.
 *
 * @property int|null $officeguy_transaction_id
 * @property int|null $officeguy_document_id
 * @property int|null $officeguy_token_id
 *
 * @package OfficeGuy\LaravelSumitGateway\Support\Traits
 * @version 1.10.0
 */
trait HasSumitPaymentOperations
{
    /**
     * Get the OfficeGuy transaction relationship.
     */
    public function officeGuyTransaction(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyTransaction::class, 'officeguy_transaction_id');
    }

    /**
     * Get the OfficeGuy document relationship.
     */
    public function officeGuyDocument(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyDocument::class, 'officeguy_document_id');
    }

    /**
     * Get the OfficeGuy payment token relationship.
     */
    public function officeGuyToken(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyToken::class, 'officeguy_token_id');
    }

    /**
     * Get the document URL for a specific document type.
     *
     * @param string $documentType The type of document (default: 'invoice')
     * @return string|null The document URL or null if not found
     */
    public function getDocumentUrl(string $documentType = 'invoice'): ?string
    {
        if (! $this->officeguy_document_id) {
            return null;
        }

        $document = $this->officeGuyDocument;

        if (! $document) {
            return null;
        }

        return $document->document_download_url ?? $document->document_payment_url;
    }

    /**
     * Check if the model has an invoice document.
     */
    public function hasInvoiceDocument(): bool
    {
        return $this->officeguy_document_id !== null;
    }

    /**
     * Get the transaction reference (auth number).
     */
    public function getTransactionReference(): ?string
    {
        if (! $this->officeguy_transaction_id) {
            return null;
        }

        $transaction = $this->officeGuyTransaction;

        return $transaction?->auth_number;
    }

    /**
     * Get the last 4 digits of the payment method.
     *
     * Checks token first, then transaction.
     */
    public function getPaymentLast4(): ?string
    {
        // Try token first (for recurring payments)
        if ($this->officeguy_token_id) {
            $token = $this->officeGuyToken;

            if ($token && $token->last_four) {
                return $token->last_four;
            }
        }

        // Fallback to transaction
        if ($this->officeguy_transaction_id) {
            $transaction = $this->officeGuyTransaction;

            return $transaction?->last_digits;
        }

        return null;
    }

    /**
     * Get the payment brand/card type.
     *
     * Checks token first, then transaction.
     */
    public function getPaymentBrand(): ?string
    {
        // Try token first (for recurring payments)
        if ($this->officeguy_token_id) {
            $token = $this->officeGuyToken;

            if ($token) {
                return $token->getCardTypeName();
            }
        }

        // Fallback to transaction
        if ($this->officeguy_transaction_id) {
            $transaction = $this->officeGuyTransaction;

            return $transaction?->card_type;
        }

        return null;
    }

    /**
     * Get the SUMIT payment operation type.
     *
     * Returns 'ChargeAndCreateToken' if the model creates a payment token,
     * otherwise returns 'Charge' for one-time payments.
     *
     * Override createsPaymentToken() in your model for custom logic.
     */
    public function getPaymentOperation(): string
    {
        if (method_exists($this, 'createsPaymentToken') && $this->createsPaymentToken()) {
            return 'ChargeAndCreateToken';
        }

        return 'Charge';
    }

    /**
     * Determine if this payment should create a token for future charges.
     *
     * Default: false (one-time payment)
     * Override this method in your model for project-specific logic.
     *
     * Example:
     * ```php
     * public function createsPaymentToken(): bool
     * {
     *     return $this->payment_type === 'subscription_initial'
     *         || $this->is_recurring === true;
     * }
     * ```
     */
    public function createsPaymentToken(): bool
    {
        return false;
    }

    /**
     * Check if this is a one-time payment (inverse of createsPaymentToken).
     */
    public function isOneTimePayment(): bool
    {
        return ! $this->createsPaymentToken();
    }
}
