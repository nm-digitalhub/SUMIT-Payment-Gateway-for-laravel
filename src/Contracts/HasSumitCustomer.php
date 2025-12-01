<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Contracts;

/**
 * Interface for models that have a SUMIT customer account
 *
 * Implement this interface in your User/Client model to enable SUMIT integration.
 *
 * Example:
 * ```php
 * use OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer;
 * use OfficeGuy\LaravelSumitGateway\Support\Traits\HasSumitCustomerTrait;
 *
 * class Client extends Model implements HasSumitCustomer
 * {
 *     use HasSumitCustomerTrait;
 * }
 * ```
 */
interface HasSumitCustomer
{
    /**
     * Get the SUMIT customer ID
     *
     * This is the customer ID in SUMIT's system, required for all API operations
     * related to this customer (documents, payments, debt, etc.)
     *
     * @return int|null The SUMIT customer ID, or null if not yet created
     */
    public function getSumitCustomerId(): ?int;

    /**
     * Get the customer's email address for SUMIT documents
     *
     * Used when sending documents via email through SUMIT's API
     *
     * @return string|null The customer's email address
     */
    public function getSumitCustomerEmail(): ?string;

    /**
     * Get the customer's name for SUMIT documents
     *
     * Used for displaying customer name in invoices, receipts, and other documents
     *
     * @return string|null The customer's full name
     */
    public function getSumitCustomerName(): ?string;

    /**
     * Get the customer's phone number for SUMIT documents
     *
     * Optional but recommended for better customer communication
     *
     * @return string|null The customer's phone number
     */
    public function getSumitCustomerPhone(): ?string;

    /**
     * Get the customer's business ID (Israeli HP/ת.ז) for SUMIT documents
     *
     * Required for creating tax invoices (חשבונית מס)
     * Can be either:
     * - Israeli ID number (ת.ז - 9 digits)
     * - Business number (ח.ע - 9 digits)
     *
     * @return string|null The customer's ID/HP number
     */
    public function getSumitCustomerBusinessId(): ?string;
}
