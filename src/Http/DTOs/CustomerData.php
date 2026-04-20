<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\DTOs;

/**
 * CustomerData DTO for SUMIT API requests.
 *
 * Flat representation matching the fields CreateCustomerRequest sends to the API.
 * The DataTransferObjects\CustomerData uses a nested AddressData; this class
 * uses flat fields matching the SUMIT /accounting/customers/create/ endpoint schema.
 */
final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $zipCode = null,
        public ?string $companyNumber = null,
    ) {}
}
