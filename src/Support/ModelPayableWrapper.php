<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support;

use Illuminate\Database\Eloquent\Model;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

/**
 * Model Wrapper - Wraps any Eloquent model to implement Payable interface
 * using field mapping configured in Admin Panel settings.
 *
 * This allows developers to use their existing models without modifying them.
 */
class ModelPayableWrapper implements Payable
{
    protected array $fieldMap;

    public function __construct(protected Model $model, ?array $fieldMap = null)
    {
        $this->fieldMap = $fieldMap ?? $this->getFieldMapFromSettings();
    }

    /**
     * Get field mapping from Admin Panel settings.
     */
    protected function getFieldMapFromSettings(): array
    {
        $settings = app(SettingsService::class);

        return [
            'amount' => $settings->get('field_map_amount', 'total'),
            'currency' => $settings->get('field_map_currency', 'currency'),
            'customer_name' => $settings->get('field_map_customer_name', 'customer_name'),
            'customer_email' => $settings->get('field_map_customer_email', 'email'),
            'customer_phone' => $settings->get('field_map_customer_phone', 'phone'),
            'description' => $settings->get('field_map_description', 'description'),
        ];
    }

    /**
     * Get a field value from the model using the field map.
     */
    protected function getField(string $mapKey, mixed $default = null): mixed
    {
        $fieldName = $this->fieldMap[$mapKey] ?? null;

        if (! $fieldName) {
            return $default;
        }

        // Support dot notation for nested fields
        if (str_contains((string) $fieldName, '.')) {
            return data_get($this->model, $fieldName, $default);
        }

        return $this->model->{$fieldName} ?? $default;
    }

    /**
     * Get the underlying model.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    // Payable Interface Implementation

    public function getPayableId(): string | int
    {
        return $this->model->getKey();
    }

    public function getPayableAmount(): float
    {
        return (float) ($this->getField('amount', 0));
    }

    public function getPayableCurrency(): string
    {
        return $this->getField('currency', 'ILS') ?: 'ILS';
    }

    public function getCustomerEmail(): ?string
    {
        return $this->getField('customer_email');
    }

    public function getCustomerPhone(): ?string
    {
        return $this->getField('customer_phone');
    }

    public function getCustomerName(): string
    {
        return $this->getField('customer_name', '') ?: '';
    }

    public function getCustomerAddress(): ?array
    {
        return null;
    }

    public function getCustomerCompany(): ?string
    {
        return null;
    }

    public function getCustomerId(): string | int | null
    {
        return null;
    }

    public function getLineItems(): array
    {
        $description = $this->getField('description');

        if (! $description) {
            return [];
        }

        return [
            [
                'name' => $description,
                'sku' => null,
                'quantity' => 1,
                'unit_price' => $this->getPayableAmount(),
                'product_id' => $this->getPayableId(),
                'variation_id' => null,
            ],
        ];
    }

    public function getShippingAmount(): float
    {
        return 0;
    }

    public function getShippingMethod(): ?string
    {
        return null;
    }

    public function getFees(): array
    {
        return [];
    }

    public function getVatRate(): ?float
    {
        return null;
    }

    public function isTaxEnabled(): bool
    {
        return false;
    }

    public function getCustomerNote(): ?string
    {
        return null;
    }

    /**
     * Create a wrapper for a model if it doesn't implement Payable.
     * Returns the model itself if it already implements Payable.
     */
    public static function wrap(Model $model, ?array $fieldMap = null): Payable
    {
        if ($model instanceof Payable) {
            return $model;
        }

        return new static($model, $fieldMap);
    }
}
