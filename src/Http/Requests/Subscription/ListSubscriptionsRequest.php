<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Subscription;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;

/**
 * List Subscriptions Request
 *
 * Retrieve all subscriptions (recurring items) for a customer from SUMIT.
 *
 * Use Cases:
 * - Display customer's active subscriptions
 * - Sync subscriptions from SUMIT to local database
 * - Check subscription status
 * - Renewal management
 * - Subscription audit
 *
 * Request Parameters:
 * - Customer ID: SUMIT customer ID (required)
 * - IncludeInactive: Include cancelled/expired subscriptions
 *
 * Response Format:
 * {
 *   "Status": 0,
 *   "Data": {
 *     "Success": true,
 *     "RecurringItems": [
 *       {
 *         "ID": "123",
 *         "Status": 0,
 *         "Item": {
 *           "ID": "456",
 *           "Name": "Monthly Subscription",
 *           "SKU": "SUB-001",
 *           "Description": "Premium Package"
 *         },
 *         "UnitPrice": 99.00,
 *         "Quantity": 1,
 *         "Date_NextBilling": "2024-02-01",
 *         "Date_PreviousBilling": "2024-01-01",
 *         "Date_Start": "2023-01-01",
 *         "Date_Last": null
 *       }
 *     ]
 *   }
 * }
 *
 * Subscription Status Codes:
 * - 0: Active (charging regularly)
 * - 1: Paused (temporarily suspended)
 * - 2: Cancelled (permanently stopped)
 * - 3: Expired (ended naturally)
 *
 * What You Get:
 * - Array of recurring items with metadata
 * - Billing dates (next, previous, start, end)
 * - Product details (name, SKU, description)
 * - Pricing information (unit price, quantity)
 * - Status for each subscription
 *
 * IMPORTANT:
 * - Requires valid SUMIT customer ID
 * - IncludeInactive=false returns only active subscriptions
 * - Use for periodic sync with local database
 * - Does NOT include payment history (use transaction list for that)
 *
 * Sync Pattern:
 * 1. Call this request with customer ID
 * 2. Iterate over RecurringItems
 * 3. Map to local Subscription model
 * 4. Update or create local records
 * 5. Mark missing subscriptions as synced_at=null
 */
class ListSubscriptionsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new list subscriptions request
     *
     * @param int $customerId SUMIT customer ID
     * @param CredentialsData $credentials SUMIT API credentials
     * @param bool $includeInactive Include cancelled/expired subscriptions
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly CredentialsData $credentials,
        protected readonly bool $includeInactive = false,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/billing/recurring/listforcustomer/';
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
            'IncludeInactive' => $this->includeInactive ? 'true' : 'false',
        ];
    }

    /**
     * Cast response to array
     *
     * Returns raw response with RecurringItems array
     *
     * @param Response $response
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Configure request timeout
     *
     * List queries can take time with many subscriptions
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 60,
        ];
    }
}
