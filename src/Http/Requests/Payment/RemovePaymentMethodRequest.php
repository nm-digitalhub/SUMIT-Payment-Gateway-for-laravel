<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Payment;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Remove Payment Method from Customer Request
 *
 * Removes the active payment method from a SUMIT customer.
 *
 * Payment Method Removal Flow:
 * 1. Have SUMIT CustomerID (from customer creation)
 * 2. Call this request to remove active payment method
 * 3. SUMIT removes the payment method from customer
 * 4. Customer will need to add new payment method for future charges
 *
 * Use Cases:
 * - Remove expired payment methods
 * - Clear payment method before account closure
 * - User requests payment method removal
 * - Security cleanup after fraud detection
 * - Subscription cancellation with method removal
 *
 * Request Parameters:
 * - Customer ID: SUMIT customer ID (required)
 *
 * Response Data (on success):
 * - Success status (Status === 0)
 * - Payment method removed from customer
 *
 * IMPORTANT:
 * - Removes ALL payment methods for customer (not selective)
 * - Customer cannot be charged after removal
 * - Subscriptions may fail if payment method removed
 * - Cannot be undone (customer must add new method)
 *
 * Error Handling:
 * - Invalid CustomerID → "Customer not found"
 * - Customer has no method → Success (idempotent)
 * - No permissions → "Access denied"
 *
 * Best Practices:
 * - Validate CustomerID exists before calling
 * - Cancel subscriptions before removing payment method
 * - Notify customer about removal
 * - Log all removal operations
 * - Check for active subscriptions first
 *
 * Integration:
 * - Used in profile management (remove card)
 * - Used before account deletion
 * - Used in fraud prevention flows
 * - Used in subscription cancellation
 */
class RemovePaymentMethodRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new remove payment method request
     *
     * @param  int  $customerId  SUMIT customer ID
     * @param  CredentialsData  $credentials  SUMIT API credentials
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly CredentialsData $credentials,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/billing/paymentmethods/remove/';
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
        ];
    }

    /**
     * Cast response to array
     *
     * Success response structure:
     * {
     *   "Status": 0,
     *   "Data": {
     *     "Success": true
     *   }
     * }
     *
     * Customer has no method (still success):
     * {
     *   "Status": 0,
     *   "Data": {
     *     "Success": true
     *   }
     * }
     *
     * Error response structure:
     * {
     *   "Status": 1,
     *   "UserErrorMessage": "Customer not found"
     * }
     *
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Check if operation was successful
     *
     * @return bool True if payment method was removed successfully
     */
    public function isSuccessful(Response $response): bool
    {
        $data = $response->json();

        return ($data['Status'] ?? 1) === 0;
    }

    /**
     * Get error message from failed request
     *
     * @return string|null Error message, or null if successful
     */
    public function getErrorMessage(Response $response): ?string
    {
        $data = $response->json();

        if (($data['Status'] ?? 1) === 0) {
            return null;
        }

        return $data['UserErrorMessage'] ?? 'Failed to remove payment method';
    }

    /**
     * Configure request timeout
     *
     * Payment method operations are fast
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
