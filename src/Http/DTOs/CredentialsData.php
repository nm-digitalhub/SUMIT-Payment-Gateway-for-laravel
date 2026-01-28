<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\DTOs;

use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

/**
 * Credentials Data Transfer Object
 *
 * Represents SUMIT API authentication credentials.
 * Used in request body (not headers) for all API calls.
 */
class CredentialsData
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?string $apiKey = null, // Private key (server-side)
        public readonly ?string $apiPublicKey = null, // Public key (client-side)
    ) {}

    /**
     * Convert to SUMIT API request array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'CompanyID' => $this->companyId,
        ];

        // Private key (server-side operations)
        if ($this->apiKey) {
            $data['APIKey'] = $this->apiKey;
        }

        // Public key (client-side operations like tokenization)
        if ($this->apiPublicKey) {
            $data['APIPublicKey'] = $this->apiPublicKey;
        }

        return $data;
    }

    /**
     * Create from configuration (private key)
     */
    public static function fromConfig(?SettingsService $settings = null): self
    {
        $settings ??= app(SettingsService::class);

        return new self(
            companyId: (int) $settings->get('company_id'),
            apiKey: $settings->get('private_key'),
        );
    }

    /**
     * Create with public key (client-side operations)
     */
    public static function fromConfigPublic(?SettingsService $settings = null): self
    {
        $settings ??= app(SettingsService::class);

        return new self(
            companyId: (int) $settings->get('company_id'),
            apiPublicKey: $settings->get('public_key'),
        );
    }

    /**
     * Create from array (for backward compatibility)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (int) $data['CompanyID'],
            apiKey: $data['APIKey'] ?? null,
            apiPublicKey: $data['APIPublicKey'] ?? null,
        );
    }

    /**
     * Create from vendor credentials (multi-vendor support)
     */
    public static function fromVendor(int $companyId, string $apiKey): self
    {
        return new self(
            companyId: $companyId,
            apiKey: $apiKey,
        );
    }
}
