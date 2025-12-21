<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;

/**
 * ServiceDataFactory
 *
 * Prepares service-specific data based on CheckoutIntent.
 * Transforms purchase intention → domain-specific data ready for external APIs.
 *
 * CRITICAL RULES:
 * - Returns plain array (not DTO) - ready for external API consumption
 * - No ServiceType Enum - detection is done dynamically from Payable
 * - Service data is stored separately (not in CheckoutIntent)
 *
 * Examples:
 * - Domain: WHOIS data (registrant_contact, nameservers, etc.) for ResellerClub
 * - Hosting: cPanel account data (username, quota, plan, etc.)
 * - VPS: Server configuration (OS, RAM, CPU, disk, etc.)
 * - SSL: Certificate request data (domain, CSR, validation method, etc.)
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.2.0
 */
class ServiceDataFactory
{
    /**
     * Build service-specific data from checkout intent
     *
     * @param CheckoutIntent $intent Checkout context
     * @return array<string, mixed> Service-specific data ready for external API
     */
    public function build(CheckoutIntent $intent): array
    {
        // ⚠️ No ServiceType Enum - detect from payable dynamically
        $serviceType = $this->detectServiceType($intent->payable);

        return match ($serviceType) {
            'domain' => $this->buildDomainData($intent),
            'hosting' => $this->buildHostingData($intent),
            'vps' => $this->buildVpsData($intent),
            'ssl' => $this->buildSslData($intent),
            default => [],
        };
    }

    /**
     * Detect service type from payable (no Enum!)
     *
     * Priority:
     * 1. Payable::service_type property
     * 2. Payable::getServiceType() method
     * 3. Class name inference (DomainPackage → 'domain')
     * 4. PayableType fallback
     *
     * @param mixed $payable The payable entity
     * @return string Service type identifier ('domain', 'hosting', 'vps', 'ssl', etc.)
     */
    protected function detectServiceType($payable): string
    {
        // Priority 1: service_type property
        if (property_exists($payable, 'service_type') && !empty($payable->service_type)) {
            return $payable->service_type;
        }

        // Priority 2: getServiceType() method
        if (method_exists($payable, 'getServiceType')) {
            return $payable->getServiceType();
        }

        // Priority 3: Infer from class name
        $className = class_basename($payable);
        if (str_contains($className, 'Domain')) return 'domain';
        if (str_contains($className, 'Hosting')) return 'hosting';
        if (str_contains($className, 'Vps') || str_contains($className, 'VPS')) return 'vps';
        if (str_contains($className, 'Ssl') || str_contains($className, 'SSL')) return 'ssl';

        // Priority 4: Fallback to PayableType
        // ⚠️ Returns values that have handlers in match() above
        return match ($payable->getPayableType()) {
            PayableType::INFRASTRUCTURE => 'domain', // Default for infrastructure
            PayableType::DIGITAL_PRODUCT => 'digital',
            PayableType::SUBSCRIPTION => 'subscription',
            default => 'generic',
        };
    }

    /**
     * Build domain registration data (ResellerClub format)
     *
     * Prepares WHOIS contact data, nameservers, privacy protection settings
     * for domain registration API calls.
     *
     * @param CheckoutIntent $intent Checkout context
     * @return array<string, mixed> Domain registration data
     */
    protected function buildDomainData(CheckoutIntent $intent): array
    {
        return [
            'registrant_contact' => [
                'name' => $intent->customer->name,
                'company' => $intent->customer->company ?? '',
                'email' => $intent->customer->email,
                'address1' => $intent->customer->address?->line1 ?? '',
                'address2' => $intent->customer->address?->line2 ?? '',
                'city' => $intent->customer->address?->city ?? '',
                'state' => $intent->customer->address?->state ?? '',
                'country' => $intent->customer->address?->country ?? 'IL',
                'zipcode' => $intent->customer->address?->postalCode ?? '',
                'phone' => $this->formatPhoneForWhois($intent->customer->phone),
            ],
            'admin_contact' => 'same_as_registrant',
            'tech_contact' => 'same_as_registrant',
            'billing_contact' => 'same_as_registrant',
            'privacy_protection' => $this->shouldEnablePrivacy($intent),
            'nameservers' => $this->getDefaultNameservers(),
            'years' => $this->getDomainYears($intent->payable),
        ];
    }

    /**
     * Build hosting provisioning data (cPanel/WHM format)
     *
     * Placeholder - to be implemented with actual hosting integration
     *
     * @param CheckoutIntent $intent Checkout context
     * @return array<string, mixed> Hosting provisioning data
     */
    protected function buildHostingData(CheckoutIntent $intent): array
    {
        // TODO: Implement when hosting integration is ready
        return [];
    }

    /**
     * Build VPS configuration data
     *
     * Placeholder - to be implemented with actual VPS integration
     *
     * @param CheckoutIntent $intent Checkout context
     * @return array<string, mixed> VPS configuration data
     */
    protected function buildVpsData(CheckoutIntent $intent): array
    {
        // TODO: Implement when VPS integration is ready
        return [];
    }

    /**
     * Build SSL certificate request data
     *
     * Placeholder - to be implemented with actual SSL integration
     *
     * @param CheckoutIntent $intent Checkout context
     * @return array<string, mixed> SSL certificate data
     */
    protected function buildSslData(CheckoutIntent $intent): array
    {
        // TODO: Implement when SSL integration is ready
        return [];
    }

    /**
     * Format phone number for WHOIS (ResellerClub format)
     *
     * Converts: 0541234567 → +972.541234567
     *
     * @param string $phone Original phone number
     * @return string Formatted phone for WHOIS
     */
    protected function formatPhoneForWhois(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zero if present
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // Format: +972.XXXXXXXXX (ResellerClub requirement)
        return '+972.' . $phone;
    }

    /**
     * Check if domain privacy protection should be enabled
     *
     * @param CheckoutIntent $intent Checkout context
     * @return bool True if privacy should be enabled
     */
    protected function shouldEnablePrivacy(CheckoutIntent $intent): bool
    {
        // Check if privacy protection is enabled by default in settings
        return (bool) config('officeguy.domain_privacy_protection', true);
    }

    /**
     * Get default nameservers from configuration
     *
     * @return array<string> Nameserver list
     */
    protected function getDefaultNameservers(): array
    {
        return config('officeguy.default_nameservers', [
            'ns1.example.com',
            'ns2.example.com',
        ]);
    }

    /**
     * Get domain registration years
     *
     * @param mixed $payable The payable entity
     * @return int Number of years (default: 1)
     */
    protected function getDomainYears($payable): int
    {
        // Try to get years from payable
        if (method_exists($payable, 'getYears')) {
            return max(1, (int) $payable->getYears());
        }

        if (property_exists($payable, 'years') && !empty($payable->years)) {
            return max(1, (int) $payable->years);
        }

        // Default: 1 year
        return 1;
    }
}
