<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Contracts;

/**
 * Interface for any billable entity (order, invoice, etc.) that can be processed through SUMIT
 *
 * Any model that needs to process payments via the SUMIT gateway must implement this interface.
 * This provides a standardized way to extract payment-related information regardless of the
 * underlying order/invoice implementation.
 */
interface Payable
{
    /**
     * Get the unique identifier for this payable entity
     *
     * @return string|int
     */
    public function getPayableId(): string|int;

    /**
     * Get the total amount to be paid
     *
     * @return float
     */
    public function getPayableAmount(): float;

    /**
     * Get the currency code (e.g., 'ILS', 'USD', 'EUR')
     *
     * @return string
     */
    public function getPayableCurrency(): string;

    /**
     * Get the customer's email address
     *
     * @return string|null
     */
    public function getCustomerEmail(): ?string;

    /**
     * Get the customer's phone number
     *
     * @return string|null
     */
    public function getCustomerPhone(): ?string;

    /**
     * Get the customer's full name
     *
     * @return string
     */
    public function getCustomerName(): string;

    /**
     * Get the customer's billing address
     *
     * Returns an array with keys:
     * - address: string (street address)
     * - city: string
     * - state: string|null
     * - country: string (country code)
     * - zip_code: string|null
     *
     * @return array|null
     */
    public function getCustomerAddress(): ?array;

    /**
     * Get the customer's company name (if applicable)
     *
     * @return string|null
     */
    public function getCustomerCompany(): ?string;

    /**
     * Get the customer ID from the system
     *
     * @return string|int|null
     */
    public function getCustomerId(): string|int|null;

    /**
     * Get line items for this payable
     *
     * Returns an array of items, each with:
     * - name: string
     * - sku: string|null
     * - quantity: int|float
     * - unit_price: float
     * - product_id: string|int|null
     * - variation_id: string|int|null
     *
     * @return array
     */
    public function getLineItems(): array;

    /**
     * Get shipping amount
     *
     * @return float
     */
    public function getShippingAmount(): float;

    /**
     * Get shipping method name
     *
     * @return string|null
     */
    public function getShippingMethod(): ?string;

    /**
     * Get any additional fees
     *
     * Returns an array of fees, each with:
     * - name: string
     * - amount: float
     *
     * @return array
     */
    public function getFees(): array;

    /**
     * Get VAT/Tax rate percentage
     *
     * @return float|null
     */
    public function getVatRate(): ?float;

    /**
     * Check if VAT/Tax is enabled
     *
     * @return bool
     */
    public function isTaxEnabled(): bool;

    /**
     * Get customer note/description
     *
     * @return string|null
     */
    public function getCustomerNote(): ?string;

    /**
     * Get a unique security key for webhook validation.
     *
     * This key is used to validate webhook authenticity and prevent fraud.
     * It should be:
     * - Unique per order
     * - Hard to guess (random or hashed)
     * - Stored securely
     *
     * WooCommerce equivalent: $order->get_order_key()
     *
     * Example implementations:
     * - Pre-generated: bin2hex(random_bytes(16))
     * - Hashed: hash('sha256', $id . $created_at . APP_KEY)
     *
     * @return string|null Order security key for webhook validation
     */
    public function getOrderKey(): ?string;

    /**
     * Get the payable type for checkout customization
     *
     * Determines which checkout template to use and what fields are required.
     * Use HasPayableType trait for default implementation.
     *
     * @return \OfficeGuy\LaravelSumitGateway\Enums\PayableType
     * @since 1.10.0
     */
    public function getPayableType(): \OfficeGuy\LaravelSumitGateway\Enums\PayableType;
}
