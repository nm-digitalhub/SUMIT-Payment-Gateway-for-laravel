<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Contracts;

/**
 * Interface for customer repository operations
 *
 * Provides a standardized way to query and manage customer records,
 * regardless of the underlying model implementation (User, Client, etc.)
 *
 * Example implementation:
 * ```php
 * use OfficeGuy\LaravelSumitGateway\Contracts\CustomerRepository;
 *
 * class EloquentCustomerRepository implements CustomerRepository
 * {
 *     public function findBySumitCustomerId(int $sumitCustomerId): ?HasSumitCustomer
 *     {
 *         return Client::where('sumit_customer_id', $sumitCustomerId)->first();
 *     }
 *     // ... other methods
 * }
 * ```
 */
interface CustomerRepository
{
    /**
     * Find a customer by their SUMIT customer ID
     *
     * @param int $sumitCustomerId The SUMIT customer ID
     * @return HasSumitCustomer|null The customer model or null if not found
     */
    public function findBySumitCustomerId(int $sumitCustomerId): ?HasSumitCustomer;

    /**
     * Find a customer by their email address
     *
     * @param string $email The customer's email address
     * @return HasSumitCustomer|null The customer model or null if not found
     */
    public function findByEmail(string $email): ?HasSumitCustomer;

    /**
     * Find a customer by their phone number
     *
     * @param string $phone The customer's phone number
     * @return HasSumitCustomer|null The customer model or null if not found
     */
    public function findByPhone(string $phone): ?HasSumitCustomer;

    /**
     * Find a customer by their VAT/tax number
     *
     * @param string $vatNumber The customer's VAT or tax identification number
     * @return HasSumitCustomer|null The customer model or null if not found
     */
    public function findByVatNumber(string $vatNumber): ?HasSumitCustomer;

    /**
     * Create a new customer record
     *
     * @param array<string, mixed> $data Customer data
     * @return HasSumitCustomer The created customer model
     */
    public function create(array $data): HasSumitCustomer;

    /**
     * Update an existing customer record
     *
     * @param HasSumitCustomer $customer The customer to update
     * @param array<string, mixed> $data Updated customer data
     * @return HasSumitCustomer The updated customer model
     */
    public function update(HasSumitCustomer $customer, array $data): HasSumitCustomer;
}
