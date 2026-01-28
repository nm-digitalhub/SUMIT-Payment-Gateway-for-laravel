<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

use Illuminate\Http\Request;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * CheckoutIntent DTO
 *
 * ⚠️ IMMUTABLE - Represents checkout context/intention.
 * Contains all data needed for payment processing WITHOUT business logic.
 *
 * CRITICAL RULES:
 * - All properties are readonly (PHP 8.1+) - cannot be modified after creation
 * - Does NOT store service-specific data (WHOIS, cPanel config, etc.)
 * - Service data is stored separately in PendingCheckout table
 * - This is CONTEXT only, not a mutable state container
 *
 * @since 1.2.0
 */
final readonly class CheckoutIntent
{
    public function __construct(
        public Payable $payable,
        public CustomerData $customer,
        public PaymentPreferences $payment,
    ) {}

    /**
     * Create from HTTP request and payable entity
     *
     * @param  Request  $request  Validated checkout request
     * @param  Payable  $payable  The entity being purchased
     */
    public static function fromRequest(Request $request, Payable $payable): self
    {
        return new self(
            payable: $payable,
            customer: CustomerData::fromRequest($request),
            payment: PaymentPreferences::fromRequest($request),
        );
    }

    /**
     * Create from array (for deserialization from DB/session)
     *
     * @param  array<string, mixed>  $data
     * @param  Payable  $payable  The payable entity (must be loaded separately)
     */
    public static function fromArray(array $data, Payable $payable): self
    {
        return new self(
            payable: $payable,
            customer: CustomerData::fromArray($data['customer'] ?? []),
            payment: PaymentPreferences::fromArray($data['payment'] ?? []),
        );
    }

    /**
     * Convert to array (for serialization to DB/session)
     *
     * ⚠️ Note: Payable is NOT serialized (only type + ID are stored)
     * Service-specific data is stored separately in PendingCheckout
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'payable_type' => $this->payable::class,
            'payable_id' => $this->payable->getPayableId(),
            'customer' => $this->customer->toArray(),
            'payment' => $this->payment->toArray(),
        ];
    }

    // ⚠️ Intent intentionally does not store service data
    // Service-specific data (WHOIS, cPanel config, etc.) is stored separately
    // in PendingCheckout table via TemporaryStorageService

    /**
     * Get transaction amount
     */
    public function getAmount(): float
    {
        return $this->payable->getPayableAmount();
    }

    /**
     * Get transaction currency
     */
    public function getCurrency(): string
    {
        return $this->payable->getPayableCurrency();
    }

    /**
     * Check if this purchase requires full address
     */
    public function requiresAddress(): bool
    {
        return $this->payable->getPayableType()->requiresAddress();
    }

    /**
     * Check if this purchase requires phone number
     */
    public function requiresPhone(): bool
    {
        return $this->payable->getPayableType()->requiresPhone();
    }

    /**
     * Check if customer has provided complete address
     */
    public function hasCompleteAddress(): bool
    {
        return $this->customer->hasAddress();
    }

    /**
     * Get PayableType enum
     */
    public function getPayableType(): \OfficeGuy\LaravelSumitGateway\Enums\PayableType
    {
        return $this->payable->getPayableType();
    }

    /**
     * Check if payment is using saved token
     */
    public function isUsingSavedToken(): bool
    {
        return $this->payment->isUsingSavedToken();
    }

    /**
     * Check if payment is card-based
     */
    public function isCardPayment(): bool
    {
        return $this->payment->isCardPayment();
    }

    /**
     * Check if payment is Bit
     */
    public function isBitPayment(): bool
    {
        return $this->payment->isBitPayment();
    }
}
