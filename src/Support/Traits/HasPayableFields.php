<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;

/**
 * Trait HasPayableFields
 *
 * Provides automatic implementation of the Payable interface using field mapping.
 * This trait reads field mappings from OfficeGuy Settings and applies them dynamically.
 *
 * Usage:
 * ```php
 * use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
 * use OfficeGuy\LaravelSumitGateway\Support\Traits\HasPayableFields;
 *
 * class Order extends Model implements Payable
 * {
 *     use HasPayableFields;
 *
 *     // Optional: Override specific methods if needed
 *     public function getPayableCurrency(): string
 *     {
 *         return $this->currency_code ?? 'ILS';
 *     }
 * }
 * ```
 *
 * @package OfficeGuy\LaravelSumitGateway\Support\Traits
 */
trait HasPayableFields
{
    /**
     * Get the mapping service instance
     *
     * @return PayableMappingService
     */
    protected function getPayableMappingService(): PayableMappingService
    {
        return app(PayableMappingService::class);
    }

    /**
     * Get a field value using the mapping service
     *
     * @param string $key Field key (e.g., 'amount', 'customer_email')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    protected function getPayableField(string $key, mixed $default = null): mixed
    {
        $mapping = $this->getPayableMappingService()->getMapping($key);

        if ($mapping) {
            return $this->getAttribute($mapping) ?? $default;
        }

        return $default;
    }

    /**
     * Get the unique identifier for this payable entity
     *
     * @return string|int
     */
    public function getPayableId(): string|int
    {
        return $this->getKey();
    }

    /**
     * Get the total amount to be paid
     *
     * @return float
     */
    public function getPayableAmount(): float
    {
        $amount = $this->getPayableField('amount', 0);
        return (float) $amount;
    }

    /**
     * Get the currency code (e.g., 'ILS', 'USD', 'EUR')
     *
     * @return string
     */
    public function getPayableCurrency(): string
    {
        return $this->getPayableField('currency')
            ?? config('officeguy.currency', 'ILS');
    }

    /**
     * Get the customer's email address
     *
     * @return string|null
     */
    public function getCustomerEmail(): ?string
    {
        return $this->getPayableField('customer_email');
    }

    /**
     * Get the customer's phone number
     *
     * @return string|null
     */
    public function getCustomerPhone(): ?string
    {
        return $this->getPayableField('customer_phone');
    }

    /**
     * Get the customer's full name
     *
     * @return string
     */
    public function getCustomerName(): string
    {
        return $this->getPayableField('customer_name', 'Guest');
    }

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
    public function getCustomerAddress(): ?array
    {
        $address = $this->getPayableField('customer_address');

        if (is_string($address)) {
            // If stored as JSON string
            $decoded = json_decode($address, true);
            if ($decoded) {
                return $decoded;
            }
        }

        if (is_array($address)) {
            return $address;
        }

        // Try to build from individual fields
        $street = $this->getPayableField('address');
        $city = $this->getPayableField('city');
        $country = $this->getPayableField('country');

        if ($street || $city || $country) {
            return [
                'address' => $street,
                'city' => $city,
                'state' => $this->getPayableField('state'),
                'country' => $country ?? 'IL',
                'zip_code' => $this->getPayableField('zip_code'),
            ];
        }

        return null;
    }

    /**
     * Get the customer's company name (if applicable)
     *
     * @return string|null
     */
    public function getCustomerCompany(): ?string
    {
        return $this->getPayableField('customer_company');
    }

    /**
     * Get the customer ID from the system
     *
     * @return string|int|null
     */
    public function getCustomerId(): string|int|null
    {
        // Try mapped field first
        $customerId = $this->getPayableField('customer_id');

        if ($customerId) {
            return $customerId;
        }

        // Try common relationship patterns
        if (method_exists($this, 'customer')) {
            return $this->customer?->id;
        }

        if (method_exists($this, 'user')) {
            return $this->user?->id;
        }

        if (method_exists($this, 'client')) {
            return $this->client?->id;
        }

        return null;
    }

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
    public function getLineItems(): array
    {
        $items = $this->getPayableField('line_items');

        if (is_string($items)) {
            $decoded = json_decode($items, true);
            if ($decoded && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($items)) {
            return $items;
        }

        // Try common relationship patterns
        if (method_exists($this, 'items')) {
            return $this->items->map(function ($item) {
                return [
                    'name' => $item->name ?? $item->product_name ?? 'Item',
                    'sku' => $item->sku ?? null,
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => (float) ($item->price ?? $item->unit_price ?? 0),
                    'product_id' => $item->product_id ?? null,
                    'variation_id' => $item->variation_id ?? null,
                ];
            })->toArray();
        }

        if (method_exists($this, 'orderItems')) {
            return $this->orderItems->map(function ($item) {
                return [
                    'name' => $item->name ?? $item->product_name ?? 'Item',
                    'sku' => $item->sku ?? null,
                    'quantity' => $item->quantity ?? 1,
                    'unit_price' => (float) ($item->price ?? $item->unit_price ?? 0),
                    'product_id' => $item->product_id ?? null,
                    'variation_id' => $item->variation_id ?? null,
                ];
            })->toArray();
        }

        // Return single item if no line items exist
        return [
            [
                'name' => $this->getPayableField('description', 'Payment'),
                'sku' => null,
                'quantity' => 1,
                'unit_price' => $this->getPayableAmount(),
                'product_id' => null,
                'variation_id' => null,
            ],
        ];
    }

    /**
     * Get shipping amount
     *
     * @return float
     */
    public function getShippingAmount(): float
    {
        $shipping = $this->getPayableField('shipping_amount', 0);
        return (float) $shipping;
    }

    /**
     * Get shipping method name
     *
     * @return string|null
     */
    public function getShippingMethod(): ?string
    {
        return $this->getPayableField('shipping_method');
    }

    /**
     * Get any additional fees
     *
     * Returns an array of fees, each with:
     * - name: string
     * - amount: float
     *
     * @return array
     */
    public function getFees(): array
    {
        $fees = $this->getPayableField('fees');

        if (is_string($fees)) {
            $decoded = json_decode($fees, true);
            if ($decoded && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($fees)) {
            return $fees;
        }

        return [];
    }

    /**
     * Get VAT/Tax rate percentage
     *
     * @return float|null
     */
    public function getVatRate(): ?float
    {
        $rate = $this->getPayableField('vat_rate');
        return $rate !== null ? (float) $rate : null;
    }

    /**
     * Check if VAT/Tax is enabled
     *
     * @return bool
     */
    public function isTaxEnabled(): bool
    {
        $enabled = $this->getPayableField('tax_enabled');

        if ($enabled !== null) {
            return (bool) $enabled;
        }

        // If not explicitly set, check if VAT rate exists
        return $this->getVatRate() !== null && $this->getVatRate() > 0;
    }

    /**
     * Get customer note/description
     *
     * @return string|null
     */
    public function getCustomerNote(): ?string
    {
        return $this->getPayableField('description')
            ?? $this->getPayableField('notes')
            ?? $this->getPayableField('customer_note');
    }

    /**
     * Get order security key for webhook validation.
     *
     * Default implementation: Returns order_key field if exists,
     * otherwise generates hash from id + created_at + app key.
     *
     * WooCommerce equivalent: $order->get_order_key()
     *
     * Override this method in your model for custom logic.
     *
     * @return string|null
     */
    public function getOrderKey(): ?string
    {
        // Option 1: Use order_key column if exists (recommended)
        if (isset($this->order_key) && ! empty($this->order_key)) {
            return $this->order_key;
        }

        // Option 2: Generate on-the-fly (fallback, less secure)
        // This ensures backward compatibility for models without order_key column
        if (isset($this->id) && isset($this->created_at)) {
            return hash('sha256', $this->id.$this->created_at->timestamp.config('app.key'));
        }

        // No order_key and no id/created_at - return null
        return null;
    }
}
