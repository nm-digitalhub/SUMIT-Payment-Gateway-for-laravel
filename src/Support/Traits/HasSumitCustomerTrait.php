<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

/**
 * Trait for implementing HasSumitCustomer interface
 *
 * This trait provides a default implementation for the HasSumitCustomer interface.
 * It assumes your model has the following attributes:
 * - sumit_customer_id (int)
 * - email (string)
 * - name or full_name (string)
 * - phone or mobile (string)
 * - citizen_id or business_id or id_number (string)
 *
 * If your model has different attribute names, you can override these methods.
 *
 * Example usage:
 * ```php
 * use OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer;
 * use OfficeGuy\LaravelSumitGateway\Support\Traits\HasSumitCustomerTrait;
 *
 * class Client extends Model implements HasSumitCustomer
 * {
 *     use HasSumitCustomerTrait;
 *
 *     // Optional: Override if your attribute names are different
 *     public function getSumitCustomerName(): ?string
 *     {
 *         return $this->first_name . ' ' . $this->last_name;
 *     }
 * }
 * ```
 */
trait HasSumitCustomerTrait
{
    /**
     * Get the SUMIT customer ID
     *
     * Default implementation assumes attribute name: sumit_customer_id
     */
    public function getSumitCustomerId(): ?int
    {
        return $this->sumit_customer_id;
    }

    /**
     * Get the customer's email address
     *
     * Default implementation assumes attribute name: email
     */
    public function getSumitCustomerEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Get the customer's name
     *
     * Default implementation tries these attributes in order:
     * 1. full_name
     * 2. name
     * 3. first_name + last_name
     */
    public function getSumitCustomerName(): ?string
    {
        // Try full_name first
        if (isset($this->full_name) && ! empty($this->full_name)) {
            return $this->full_name;
        }

        // Try name
        if (isset($this->name) && ! empty($this->name)) {
            return $this->name;
        }

        // Try combining first_name + last_name
        if (isset($this->first_name) || isset($this->last_name)) {
            $parts = array_filter([
                $this->first_name ?? null,
                $this->last_name ?? null,
            ]);

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        return null;
    }

    /**
     * Get the customer's phone number
     *
     * Default implementation tries these attributes in order:
     * 1. phone
     * 2. mobile
     * 3. telephone
     */
    public function getSumitCustomerPhone(): ?string
    {
        return $this->phone
            ?? $this->mobile
            ?? $this->telephone
            ?? null;
    }

    /**
     * Get the customer's business ID (Israeli HP/ת.ז)
     *
     * Default implementation tries these attributes in order:
     * 1. citizen_id
     * 2. business_id
     * 3. id_number
     * 4. hp
     */
    public function getSumitCustomerBusinessId(): ?string
    {
        return $this->citizen_id
            ?? $this->business_id
            ?? $this->id_number
            ?? $this->hp
            ?? null;
    }
}
