<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Enums;

/**
 * PayableType Enum
 *
 * Defines semantic categories for purchasable items to enable:
 * - Type-specific checkout templates
 * - Type-specific fulfillment logic
 * - Type-specific post-payment flows
 * - Type-specific required fields
 *
 * @since 1.10.0
 */
enum PayableType: string
{
    /**
     * Infrastructure Products
     *
     * Products requiring physical/legal provisioning:
     * - Domains (WHOIS, registrar provisioning)
     * - SSL Certificates (CSR, validation)
     * - Hosting (cPanel, server provisioning)
     * - VPS (server setup)
     */
    case INFRASTRUCTURE = 'infrastructure';

    /**
     * Digital Products
     *
     * Instantly deliverable digital goods:
     * - eSIM packages
     * - Software licenses
     * - Digital downloads
     * - API access tokens
     */
    case DIGITAL_PRODUCT = 'digital_product';

    /**
     * Subscription Services
     *
     * Recurring billing products:
     * - Business email (monthly/yearly)
     * - SaaS subscriptions
     * - Managed services
     * - Support plans
     */
    case SUBSCRIPTION = 'subscription';

    /**
     * One-Time Services
     *
     * Professional services delivered once:
     * - Website design
     * - SEO audit
     * - Technical support hours
     * - Consultation sessions
     */
    case SERVICE = 'service';

    /**
     * Generic/Fallback
     *
     * Default type for backward compatibility.
     * Used when Payable doesn't specify a type.
     */
    case GENERIC = 'generic';

    /**
     * Get human-readable label in Hebrew
     */
    public function label(): string
    {
        return match ($this) {
            self::INFRASTRUCTURE => 'תשתית',
            self::DIGITAL_PRODUCT => 'מוצר דיגיטלי',
            self::SUBSCRIPTION => 'מנוי',
            self::SERVICE => 'שירות',
            self::GENERIC => 'כללי',
        };
    }

    /**
     * Get human-readable label in English
     */
    public function labelEn(): string
    {
        return match ($this) {
            self::INFRASTRUCTURE => 'Infrastructure',
            self::DIGITAL_PRODUCT => 'Digital Product',
            self::SUBSCRIPTION => 'Subscription',
            self::SERVICE => 'Service',
            self::GENERIC => 'Generic',
        };
    }

    /**
     * Get checkout template name for this type
     *
     * Templates are loaded from:
     * resources/views/pages/checkout/{template}.blade.php
     */
    public function checkoutTemplate(): string
    {
        return match ($this) {
            self::INFRASTRUCTURE => 'infrastructure',
            self::DIGITAL_PRODUCT => 'digital',
            self::SUBSCRIPTION => 'subscription',
            self::SERVICE => 'service',
            self::GENERIC => 'checkout',
        };
    }

    /**
     * Check if this type requires full address details
     */
    public function requiresAddress(): bool
    {
        return match ($this) {
            self::INFRASTRUCTURE => true,
            default => false,
        };
    }

    /**
     * Check if this type requires phone number
     */
    public function requiresPhone(): bool
    {
        return match ($this) {
            self::INFRASTRUCTURE, self::SUBSCRIPTION, self::SERVICE => true,
            default => false,
        };
    }

    /**
     * Check if this type supports instant delivery
     */
    public function isInstantDelivery(): bool
    {
        return $this === self::DIGITAL_PRODUCT;
    }

    /**
     * Get estimated fulfillment time in minutes
     */
    public function estimatedFulfillmentMinutes(): int
    {
        return match ($this) {
            self::INFRASTRUCTURE => 30,
            self::DIGITAL_PRODUCT => 0,
            self::SUBSCRIPTION => 5,
            self::SERVICE => 1440,
            self::GENERIC => 60,
        };
    }

    /**
     * Get icon for this type (Heroicon name)
     */
    public function icon(): string
    {
        return match ($this) {
            self::INFRASTRUCTURE => 'heroicon-o-server',
            self::DIGITAL_PRODUCT => 'heroicon-o-device-phone-mobile',
            self::SUBSCRIPTION => 'heroicon-o-arrow-path',
            self::SERVICE => 'heroicon-o-briefcase',
            self::GENERIC => 'heroicon-o-shopping-cart',
        };
    }

    /**
     * Get color for this type (Filament color name)
     */
    public function color(): string
    {
        return match ($this) {
            self::INFRASTRUCTURE => 'primary',
            self::DIGITAL_PRODUCT => 'success',
            self::SUBSCRIPTION => 'warning',
            self::SERVICE => 'info',
            self::GENERIC => 'gray',
        };
    }
}
