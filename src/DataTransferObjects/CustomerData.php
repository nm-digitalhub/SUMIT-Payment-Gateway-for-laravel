<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

use Illuminate\Http\Request;

/**
 * CustomerData DTO
 *
 * Immutable value object representing customer information.
 * Used throughout checkout flow for billing/shipping/contact details.
 *
 * CRITICAL: All properties are readonly (PHP 8.1+) - cannot be modified after creation.
 */
final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $company = null,
        public ?string $vatNumber = null,
        public ?string $citizenId = null,
        public ?AddressData $address = null,
    ) {}

    /**
     * Create from HTTP request
     */
    public static function fromRequest(Request $request): self
    {
        // Build address if present
        $address = null;
        if ($request->filled('customer_address') || $request->filled('customer_city')) {
            $address = new AddressData(
                line1: $request->input('customer_address', ''),
                line2: $request->input('customer_address2'),
                city: $request->input('customer_city', ''),
                state: $request->input('customer_state'),
                country: $request->input('customer_country', 'IL'),
                postalCode: $request->input('customer_postal'),
            );
        }

        return new self(
            name: $request->input('customer_name', ''),
            email: $request->input('customer_email', ''),
            phone: $request->input('customer_phone', ''),
            company: $request->input('customer_company'),
            vatNumber: $request->input('customer_vat'),
            citizenId: $request->input('citizen_id'),
            address: $address,
        );
    }

    /**
     * Create from array (for deserialization from DB/session)
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $address = null;
        if (isset($data['address']) && is_array($data['address'])) {
            $address = AddressData::fromArray($data['address']);
        }

        return new self(
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? '',
            company: $data['company'] ?? null,
            vatNumber: $data['vat_number'] ?? null,
            citizenId: $data['citizen_id'] ?? null,
            address: $address,
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'vat_number' => $this->vatNumber,
            'citizen_id' => $this->citizenId,
            'address' => $this->address?->toArray(),
        ];
    }

    /**
     * Check if customer has complete address
     */
    public function hasAddress(): bool
    {
        return $this->address instanceof \OfficeGuy\LaravelSumitGateway\DataTransferObjects\AddressData && $this->address->isComplete();
    }

    /**
     * Check if customer is a business (has company or VAT)
     */
    public function isBusiness(): bool
    {
        return ! in_array($this->company, [null, '', '0'], true) || ! in_array($this->vatNumber, [null, '', '0'], true);
    }

    /**
     * Get first name (best effort)
     */
    public function getFirstName(): string
    {
        $parts = explode(' ', $this->name, 2);

        return $parts[0] ?? '';
    }

    /**
     * Get last name (best effort)
     */
    public function getLastName(): string
    {
        $parts = explode(' ', $this->name, 2);

        return $parts[1] ?? '';
    }
}
