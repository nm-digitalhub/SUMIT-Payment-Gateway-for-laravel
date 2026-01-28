<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Customer;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Get Customer Details Request
 *
 * Retrieve full customer details from SUMIT accounting system by CustomerID.
 *
 * Customer Retrieval Flow:
 * 1. Have CustomerID stored locally (from create or previous sync)
 * 2. Call this request with CustomerID
 * 3. SUMIT returns complete customer profile
 * 4. Use data for display, validation, or sync
 *
 * Use Cases:
 * - Display customer profile in admin panel
 * - Validate customer data before payment
 * - Sync SUMIT changes to local database
 * - Verify customer account status
 * - Audit customer information
 * - Populate forms with existing data
 *
 * Request Parameters:
 * - Customer ID: SUMIT customer ID (required)
 * - ResponseLanguage: Response language (he/en/fr)
 *
 * Response Data:
 * - Customer details (name, email, phone, address)
 * - Account status and creation date
 * - Custom properties if set
 * - CRM folder assignment
 * - Company information (tax ID, etc.)
 * - Linked documents count
 * - Payment history summary
 *
 * IMPORTANT:
 * - CustomerID must exist in SUMIT system
 * - Returns full customer profile (not partial)
 * - Use for verification before operations
 * - Ideal for periodic sync checks
 *
 * Best Practices:
 * - Cache results if querying frequently
 * - Verify CustomerID exists before calling
 * - Use for validation before creating documents
 * - Sync regularly to keep local data fresh
 * - Log retrieval for audit trail
 *
 * Integration:
 * - Use before processing payments (verify customer)
 * - Use in customer edit forms (prepopulate)
 * - Use in CRM sync workflows
 * - Use for customer service lookups
 */
class GetCustomerDetailsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new get customer details request
     *
     * @param  int  $customerId  SUMIT customer ID
     * @param  CredentialsData  $credentials  SUMIT API credentials
     * @param  string|null  $responseLanguage  Response language (he/en/fr)
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $responseLanguage = null,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/customers/getdetailsurl/';
    }

    /**
     * Build request body
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'Credentials' => $this->credentials->toArray(),
            'Customer' => [
                'ID' => $this->customerId,
            ],
            'ResponseLanguage' => $this->responseLanguage,
        ];
    }

    /**
     * Cast response to array
     *
     * Returns raw response with full customer details
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
     * Customer detail queries are fast
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
