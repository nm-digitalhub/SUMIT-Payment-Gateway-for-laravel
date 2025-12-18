<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

use OfficeGuy\LaravelSumitGateway\Enums\PayableType;

/**
 * HasPayableType Trait
 *
 * Provides default implementation for Payable::getPayableType()
 * and helper methods for type-based logic.
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.10.0
 */
trait HasPayableType
{
    /**
     * Default implementation - returns GENERIC
     *
     * Override this method in your model for specific types:
     *
     * public function getPayableType(): PayableType
     * {
     *     return match($this->service_type) {
     *         'domain' => PayableType::INFRASTRUCTURE,
     *         'esim' => PayableType::DIGITAL_PRODUCT,
     *         default => PayableType::GENERIC,
     *     };
     * }
     */
    public function getPayableType(): PayableType
    {
        return PayableType::GENERIC;
    }

    /**
     * Get checkout template for this payable
     *
     * Returns the template name without path/extension:
     * - 'digital' → resources/views/pages/checkout/digital.blade.php
     * - 'infrastructure' → resources/views/pages/checkout/infrastructure.blade.php
     *
     * @return string
     */
    public function getCheckoutTemplate(): string
    {
        return $this->getPayableType()->checkoutTemplate();
    }

    /**
     * Get required fields based on payable type
     *
     * Returns array of field names that must be collected during checkout.
     *
     * @return array<string>
     */
    public function getRequiredFields(): array
    {
        $base = ['customer_name', 'email'];

        $type = $this->getPayableType();

        if ($type->requiresPhone()) {
            $base[] = 'phone';
        }

        if ($type->requiresAddress()) {
            $base = array_merge($base, [
                'address',
                'city',
                'postal_code',
                'country',
            ]);
        }

        return $base;
    }

    /**
     * Get validation rules based on payable type
     *
     * @return array<string, string|array>
     */
    public function getValidationRules(): array
    {
        $rules = [
            'customer_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ];

        $type = $this->getPayableType();

        if ($type->requiresPhone()) {
            $rules['phone'] = 'required|string|max:20';
        }

        if ($type->requiresAddress()) {
            $rules += [
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:255',
                'country' => 'required|string|size:2',
                'postal_code' => 'nullable|string|max:20',
            ];
        }

        return $rules;
    }

    /**
     * Get success message based on payable type
     *
     * @return string
     */
    public function getSuccessMessage(): string
    {
        return match($this->getPayableType()) {
            PayableType::INFRASTRUCTURE => __('Your service has been provisioned successfully! Setup may take up to 60 minutes.'),
            PayableType::DIGITAL_PRODUCT => __('Your purchase has been completed! Check your email for delivery.'),
            PayableType::SUBSCRIPTION => __('Your subscription is now active! You will be charged automatically.'),
            PayableType::SERVICE => __('Your service order has been received! We will contact you shortly.'),
            PayableType::GENERIC => __('Payment completed successfully!'),
        };
    }

    /**
     * Check if this payable requires address information
     *
     * @return bool
     */
    public function requiresAddress(): bool
    {
        return $this->getPayableType()->requiresAddress();
    }

    /**
     * Check if this payable requires phone number
     *
     * @return bool
     */
    public function requiresPhone(): bool
    {
        return $this->getPayableType()->requiresPhone();
    }

    /**
     * Check if this payable supports instant delivery
     *
     * @return bool
     */
    public function isInstantDelivery(): bool
    {
        return $this->getPayableType()->isInstantDelivery();
    }

    /**
     * Get estimated fulfillment time in minutes
     *
     * @return int
     */
    public function getEstimatedFulfillmentMinutes(): int
    {
        return $this->getPayableType()->estimatedFulfillmentMinutes();
    }

    /**
     * Get human-readable type label
     *
     * @return string
     */
    public function getTypeLabel(): string
    {
        return $this->getPayableType()->label();
    }

    /**
     * Get icon name for this payable type
     *
     * @return string
     */
    public function getTypeIcon(): string
    {
        return $this->getPayableType()->icon();
    }

    /**
     * Get color for this payable type (Filament color name)
     *
     * @return string
     */
    public function getTypeColor(): string
    {
        return $this->getPayableType()->color();
    }
}
