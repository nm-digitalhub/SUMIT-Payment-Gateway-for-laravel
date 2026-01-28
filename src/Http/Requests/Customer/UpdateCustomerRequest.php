<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Customer;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CustomerData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Update Customer Request
 *
 * Update an existing customer in SUMIT accounting system.
 *
 * Customer Update Flow:
 * 1. Retrieve CustomerID from local storage
 * 2. Prepare updated customer details
 * 3. Call this request with CustomerID + changes
 * 4. SUMIT updates customer record
 * 5. Changes reflected in all future operations
 *
 * Use Cases:
 * - Update customer contact information
 * - Change customer email or phone
 * - Update billing/shipping address
 * - Correct customer details
 * - Add company tax ID
 * - Sync local customer changes to SUMIT
 *
 * Request Parameters:
 * - Details: Customer information including ID (required)
 * - Folder: Optional CRM folder ID for organization
 * - Properties: Custom fields (key-value pairs)
 * - ResponseLanguage: Response language (he/en/fr)
 *
 * Customer Details:
 * - ID: SUMIT CustomerID (REQUIRED for update)
 * - Name: Full name or company name
 * - EmailAddress: Customer email
 * - Phone: Contact phone number
 * - Address: Street address
 * - City: City name
 * - ZipCode: Postal/ZIP code
 * - CompanyNumber: Tax ID / Company registration number
 *
 * IMPORTANT:
 * - CustomerID is REQUIRED for updates
 * - Only changed fields need to be provided
 * - Email changes must be unique
 * - Updates affect all linked records (payments, documents, subscriptions)
 * - Cannot change CustomerID (use merge for that)
 *
 * Partial Updates:
 * SUMIT supports partial updates - only provide fields that changed:
 * - If email unchanged → omit EmailAddress
 * - If phone unchanged → omit Phone
 * - Only ID is required, rest is optional
 *
 * Best Practices:
 * - Always verify CustomerID exists before update
 * - Validate email format if changing
 * - Check email uniqueness if changing
 * - Use Properties for custom fields
 * - Log updates for audit trail
 *
 * Integration:
 * - Sync local customer changes automatically
 * - Update before processing new payment
 * - Refresh before generating documents
 * - Keep contact info current for notifications
 */
class UpdateCustomerRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new customer update request
     *
     * @param  int  $customerId  SUMIT customer ID (required)
     * @param  CustomerData  $customer  Updated customer details
     * @param  CredentialsData  $credentials  SUMIT API credentials
     * @param  int|null  $folderId  Optional CRM folder ID for organization
     * @param  array<string, mixed>|null  $properties  Optional custom fields
     * @param  string|null  $responseLanguage  Response language (he/en/fr)
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly CustomerData $customer,
        protected readonly CredentialsData $credentials,
        protected readonly ?int $folderId = null,
        protected readonly ?array $properties = null,
        protected readonly ?string $responseLanguage = null,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/customers/update/';
    }

    /**
     * Build request body
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $details = [
            'ID' => $this->customerId, // REQUIRED for update
            'Name' => $this->customer->name,
            'EmailAddress' => $this->customer->email,
            'Phone' => $this->customer->phone,
            'Address' => $this->customer->address,
            'City' => $this->customer->city,
            'ZipCode' => $this->customer->zipCode,
            'CompanyNumber' => $this->customer->companyNumber,
        ];

        // Optional CRM folder
        if ($this->folderId) {
            $details['Folder'] = $this->folderId;
        }

        // Optional custom properties
        if ($this->properties) {
            $details['Properties'] = $this->properties;
        }

        return [
            'Credentials' => $this->credentials->toArray(),
            'Details' => $details,
            'ResponseLanguage' => $this->responseLanguage,
        ];
    }

    /**
     * Cast response to array
     *
     * Returns success status and updated customer details
     *
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Configure request timeout
     *
     * Customer updates are fast
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }
}
