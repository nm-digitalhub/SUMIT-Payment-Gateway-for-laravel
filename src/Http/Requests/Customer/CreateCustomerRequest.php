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
 * Create Customer Request
 *
 * Create a new customer in SUMIT accounting system.
 *
 * Customer Creation Flow:
 * 1. Collect customer details (name, email, phone, address)
 * 2. Call this request to create SUMIT customer
 * 3. Receive CustomerID from SUMIT
 * 4. Store CustomerID locally for future operations
 * 5. Use CustomerID for payments, documents, subscriptions
 *
 * Use Cases:
 * - Register new customer during checkout
 * - Create customer before processing payment
 * - Sync local customers to SUMIT
 * - Link payments to customer accounts
 * - Generate customer-specific documents
 *
 * Request Parameters:
 * - Details: Customer information (name, email, phone, etc.)
 * - Folder: Optional CRM folder ID for organization
 * - Properties: Custom fields (key-value pairs)
 * - ResponseLanguage: Response language (he/en/fr)
 *
 * Response:
 * - CustomerID: Unique SUMIT customer ID
 * - Success: Creation status
 *
 * Customer Details:
 * - Name: Full name or company name (required)
 * - EmailAddress: Customer email (required)
 * - Phone: Contact phone number
 * - Address: Street address
 * - City: City name
 * - ZipCode: Postal/ZIP code
 * - CompanyNumber: Tax ID / Company registration number
 *
 * IMPORTANT:
 * - Store returned CustomerID for future operations
 * - CustomerID required for payments and documents
 * - Email must be unique per customer
 * - Name is required (minimum field)
 * - Phone recommended for payment confirmations
 *
 * Best Practices:
 * - Validate email format before submission
 * - Check for duplicate customers by email
 * - Store CustomerID immediately after creation
 * - Link to local user/customer model
 * - Use Properties for custom fields
 *
 * Integration:
 * - Use with PaymentService (attach customer to payments)
 * - Use with DocumentService (customer on invoices)
 * - Use with SubscriptionService (recurring billing)
 */
class CreateCustomerRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new customer creation request
     *
     * @param  CustomerData  $customer  Customer details
     * @param  CredentialsData  $credentials  SUMIT API credentials
     * @param  int|null  $folderId  Optional CRM folder ID for organization
     * @param  array<string, mixed>|null  $properties  Optional custom fields
     * @param  string|null  $responseLanguage  Response language (he/en/fr)
     */
    public function __construct(
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
        return '/accounting/customers/create/';
    }

    /**
     * Build request body
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $details = [
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
     * Returns CustomerID and success status
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
     * Customer creation is fast
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
