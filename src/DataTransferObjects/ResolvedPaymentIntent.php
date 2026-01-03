<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * ResolvedPaymentIntent DTO
 *
 * Immutable value object representing fully resolved payment intent.
 * This is the final DTO passed to PaymentService::processResolvedIntent().
 *
 * Contains all data needed for payment processing after resolving:
 * - PCI mode and redirect configuration
 * - Payment method details (token, single-use token, card data)
 * - Customer identification
 * - Redirect URLs (for PCI redirect mode)
 * - Installments and recurring settings
 *
 * CRITICAL: All properties are readonly - cannot be modified after creation.
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.18.0
 */
final readonly class ResolvedPaymentIntent
{
    public function __construct(
        public Payable $payable,
        public int $paymentsCount,
        public bool $recurring,
        public bool $redirectMode,
        public ?string $token,
        public array $paymentMethodPayload,
        public ?string $singleUseToken,
        public ?string $customerCitizenId,
        public ?array $redirectUrls,
        public string $pciMode,
    ) {}

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
     * Check if using saved payment token
     */
    public function isUsingSavedToken(): bool
    {
        return !empty($this->token);
    }

    /**
     * Check if using single-use token (PaymentsJS SDK)
     */
    public function isUsingSingleUseToken(): bool
    {
        return !empty($this->singleUseToken);
    }

    /**
     * Check if payment is in redirect mode (PCI redirect)
     */
    public function isRedirectMode(): bool
    {
        return $this->redirectMode;
    }

    /**
     * Check if payment has installments
     */
    public function hasInstallments(): bool
    {
        return $this->paymentsCount > 1;
    }

    /**
     * Check if payment is recurring/subscription
     */
    public function isRecurring(): bool
    {
        return $this->recurring;
    }
}
