<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

/**
 * AddressData DTO
 *
 * Immutable value object representing a physical address.
 * Used for billing/shipping addresses in checkout flow.
 *
 * CRITICAL: All properties are readonly - cannot be modified after creation.
 */
final readonly class AddressData
{
    public function __construct(
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $state = null,
        public string $country = 'IL',
        public ?string $postalCode = null,
    ) {}

    /**
     * Create from array (for deserialization from DB/session)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            line1: $data['line1'] ?? $data['address'] ?? '',
            line2: $data['line2'] ?? $data['address2'] ?? null,
            city: $data['city'] ?? '',
            state: $data['state'] ?? null,
            country: $data['country'] ?? 'IL',
            postalCode: $data['postal_code'] ?? $data['postalCode'] ?? null,
        );
    }

    /**
     * Convert to array (for serialization to DB/session)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
        ];
    }

    /**
     * Check if address is complete (all required fields present)
     */
    public function isComplete(): bool
    {
        return $this->line1 !== '' && $this->line1 !== '0'
            && ($this->city !== '' && $this->city !== '0')
            && ($this->country !== '' && $this->country !== '0');
    }

    /**
     * Get formatted address as single string
     */
    public function format(): string
    {
        $parts = array_filter([
            $this->line1,
            $this->line2,
            $this->city,
            $this->state,
            $this->postalCode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}
