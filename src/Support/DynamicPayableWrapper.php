<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support;

use Illuminate\Database\Eloquent\Model;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;

/**
 * DynamicPayableWrapper
 *
 * Wraps any Eloquent model to implement the Payable interface using custom field mappings.
 * Automatically loads mappings from the database (payable_field_mappings table).
 *
 * This allows models like MayaNetEsimProduct to be used as Payable objects
 * without modifying the model class or implementing the interface directly.
 *
 * @example
 * $esim = MayaNetEsimProduct::find(1);
 * $payable = new DynamicPayableWrapper($esim);
 * $amount = $payable->getPayableAmount(); // Returns $esim->final_price_ils (if mapped)
 */
class DynamicPayableWrapper implements Payable
{
    /**
     * Field mappings loaded from database or defaults.
     *
     * @var array<string, string|null>
     */
    protected array $fieldMap;

    /**
     * PayableMappingService instance.
     */
    protected PayableMappingService $mappingService;

    /**
     * Create a new DynamicPayableWrapper instance.
     *
     * @param  Model  $model  The model to wrap
     */
    public function __construct(protected Model $model)
    {
        $this->mappingService = app(PayableMappingService::class);

        // Load mapping from database or use defaults
        $this->fieldMap = $this->mappingService->getMappingForModel($this->model)
            ?? $this->getDefaultFieldMap();
    }

    /**
     * Get field value using the configured mapping.
     *
     * Supports multiple value types:
     * - Dot notation: "order.client.name" → navigates relationships
     * - Constant strings: "ILS", "true", "false"
     * - Numeric values: "0", "0.17", "100"
     * - JSON arrays: "[]", "[1,2,3]"
     * - Direct field: "final_price_ils" → model field
     * - null: field not mapped
     *
     * @param  string  $mapKey  The Payable field key
     * @param  mixed  $default  Default value if field is not mapped or null
     * @return mixed The resolved field value
     */
    protected function getField(string $mapKey, mixed $default = null): mixed
    {
        $mapping = $this->fieldMap[$mapKey] ?? null;

        // No mapping configured - return default
        if ($mapping === null || $mapping === '') {
            return $default;
        }

        // Boolean string literals
        if ($mapping === 'true') {
            return true;
        }
        if ($mapping === 'false') {
            return false;
        }

        // Numeric values (int or float)
        if (is_numeric($mapping)) {
            return str_contains($mapping, '.') ? (float) $mapping : (int) $mapping;
        }

        // JSON array literal (starts with '[')
        if (str_starts_with($mapping, '[')) {
            $decoded = json_decode($mapping, true);

            return is_array($decoded) ? $decoded : [];
        }

        // Quoted string constant (remove quotes)
        if ((str_starts_with($mapping, '"') && str_ends_with($mapping, '"')) ||
            (str_starts_with($mapping, "'") && str_ends_with($mapping, "'"))) {
            return trim($mapping, '"\'');
        }

        // Dot notation - navigate relationships
        if (str_contains($mapping, '.')) {
            return data_get($this->model, $mapping, $default);
        }

        // Direct model field
        return $this->model->{$mapping} ?? $default;
    }

    /**
     * Get default field mappings (fallback).
     *
     * These are used when no custom mapping exists in the database.
     *
     * @return array<string, string|null>
     */
    protected function getDefaultFieldMap(): array
    {
        return [
            'payable_id' => 'id',
            'amount' => 'total',
            'currency' => 'ILS',
            'customer_name' => 'customer_name',
            'customer_email' => 'email',
            'customer_phone' => 'phone',
            'customer_id' => null,
            'customer_address' => null,
            'customer_company' => null,
            'customer_note' => null,
            'line_items' => '[]',
            'shipping_amount' => '0',
            'shipping_method' => null,
            'fees' => '[]',
            'vat_rate' => '0.17',
            'tax_enabled' => 'true',
        ];
    }

    // ===========================
    // Payable Interface Implementation
    // ===========================

    /**
     * Get unique identifier of the payable item.
     */
    public function getPayableId(): string | int
    {
        return $this->getField('payable_id') ?? $this->model->id;
    }

    /**
     * Get the total amount to be charged (including tax if enabled).
     */
    public function getPayableAmount(): float
    {
        return (float) $this->getField('amount', 0);
    }

    /**
     * Get currency code (ISO 4217: ILS, USD, EUR, etc.).
     */
    public function getPayableCurrency(): string
    {
        return $this->getField('currency', 'ILS');
    }

    /**
     * Get customer's full name.
     */
    public function getCustomerName(): string
    {
        return $this->getField('customer_name', '');
    }

    /**
     * Get customer's email address.
     */
    public function getCustomerEmail(): ?string
    {
        return $this->getField('customer_email');
    }

    /**
     * Get customer's phone number.
     */
    public function getCustomerPhone(): ?string
    {
        return $this->getField('customer_phone');
    }

    /**
     * Get customer ID in your system (not SUMIT customer ID).
     */
    public function getCustomerId(): string | int | null
    {
        return $this->getField('customer_id');
    }

    /**
     * Get customer's address as an array.
     *
     * Expected format: ['street' => '...', 'city' => '...', 'postal_code' => '...', 'country' => '...']
     */
    public function getCustomerAddress(): ?array
    {
        $address = $this->getField('customer_address');

        return is_array($address) ? $address : null;
    }

    /**
     * Get customer's company name (for business invoices).
     */
    public function getCustomerCompany(): ?string
    {
        return $this->getField('customer_company');
    }

    /**
     * Get customer note or description.
     */
    public function getCustomerNote(): ?string
    {
        return $this->getField('customer_note');
    }

    /**
     * Get line items for detailed invoice.
     *
     * Expected format: [['name' => '...', 'quantity' => 1, 'price' => 100], ...]
     */
    public function getLineItems(): array
    {
        $items = $this->getField('line_items', []);

        return is_array($items) ? $items : [];
    }

    /**
     * Get shipping cost (0 if no shipping).
     */
    public function getShippingAmount(): float
    {
        return (float) $this->getField('shipping_amount', 0);
    }

    /**
     * Get shipping method name.
     */
    public function getShippingMethod(): ?string
    {
        return $this->getField('shipping_method');
    }

    /**
     * Get additional fees.
     *
     * Expected format: [['type' => 'processing', 'amount' => 5], ...]
     */
    public function getFees(): array
    {
        $fees = $this->getField('fees', []);

        return is_array($fees) ? $fees : [];
    }

    /**
     * Get VAT rate as decimal (0.17 for 17%).
     */
    public function getVatRate(): ?float
    {
        $rate = $this->getField('vat_rate');

        return $rate !== null ? (float) $rate : null;
    }

    /**
     * Check if tax calculation is enabled.
     */
    public function isTaxEnabled(): bool
    {
        return (bool) $this->getField('tax_enabled', false);
    }
}
