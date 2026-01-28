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
 * Get Payment Methods for Customer Request
 *
 * Retrieve all saved payment methods for a SUMIT customer.
 *
 * Payment Methods Retrieval Flow:
 * 1. Have SUMIT CustomerID (from customer creation)
 * 2. Call this request to get all saved payment methods
 * 3. SUMIT returns list of credit cards with masked numbers
 * 4. Each method includes token, last 4 digits, expiry, default flag
 * 5. Use for display in profile or checkout selection
 *
 * Use Cases:
 * - Display saved cards in customer profile
 * - Show payment method selector at checkout
 * - Verify customer has payment method before subscription
 * - Audit customer payment methods
 * - Pre-select default payment method
 *
 * Request Parameters:
 * - Customer ID: SUMIT customer ID (required)
 *
 * Response Data (on success):
 * - Array of payment methods
 * - Each method includes:
 *   - Token (for charging)
 *   - CardNumber_Masked (last 4 digits)
 *   - ExpirationMonth/Year
 *   - IsDefault flag
 *   - Type (CreditCard, etc.)
 *
 * Payment Method Structure:
 * {
 *   "Type": 1,
 *   "CreditCard_Token": "12345678-1234-1234-1234-123456789012",
 *   "CreditCard_Number_Masked": "****1234",
 *   "CreditCard_ExpirationMonth": 12,
 *   "CreditCard_ExpirationYear": 2025,
 *   "IsDefault": true
 * }
 *
 * IMPORTANT:
 * - Only returns active (non-expired) payment methods
 * - Tokens can be used directly for charging
 * - IsDefault indicates current default method
 * - Customer may have 0 payment methods (handle empty list)
 *
 * Error Handling:
 * - Invalid CustomerID → "Customer not found"
 * - No permissions → "Access denied"
 * - Customer has no methods → Returns empty array (not error)
 *
 * Best Practices:
 * - Cache results if displaying frequently
 * - Check expiry dates before using
 * - Highlight default payment method in UI
 * - Handle empty list gracefully (prompt to add)
 * - Filter out expired cards (already done by API)
 *
 * Integration:
 * - Used in checkout page (method selector)
 * - Used in customer profile page
 * - Used before subscription renewal
 * - Used in admin customer management
 */
class GetPaymentMethodsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new get payment methods request
     *
     * @param  int  $customerId  SUMIT customer ID
     * @param  CredentialsData  $credentials  SUMIT API credentials
     * @param  bool  $includeInactive  Include inactive/expired payment methods (default: false)
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly CredentialsData $credentials,
        protected readonly bool $includeInactive = false,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/billing/paymentmethods/getforcustomer/';
    }

    /**
     * Build request body
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'Credentials' => $this->credentials->toArray(),
            'Customer' => [
                'ID' => $this->customerId,
            ],
        ];

        // Include inactive methods if requested
        if ($this->includeInactive) {
            $body['IncludeInactive'] = true;
        }

        return $body;
    }

    /**
     * Cast response to array
     *
     * Success response structure:
     * {
     *   "Status": 0,
     *   "Data": {
     *     "PaymentMethods": [
     *       {
     *         "Type": 1,
     *         "CreditCard_Token": "uuid",
     *         "CreditCard_Number_Masked": "****1234",
     *         "CreditCard_ExpirationMonth": 12,
     *         "CreditCard_ExpirationYear": 2025,
     *         "IsDefault": true
     *       }
     *     ]
     *   }
     * }
     *
     * Empty list response (customer has no methods):
     * {
     *   "Status": 0,
     *   "Data": {
     *     "PaymentMethods": []
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
     * Get payment methods array from response
     *
     * @return array<int, array<string, mixed>> Array of payment methods
     */
    public function getPaymentMethods(Response $response): array
    {
        $data = $response->json();

        return $data['Data']['PaymentMethods'] ?? [];
    }

    /**
     * Check if customer has any payment methods
     *
     * @return bool True if customer has at least one payment method
     */
    public function hasPaymentMethods(Response $response): bool
    {
        return count($this->getPaymentMethods($response)) > 0;
    }

    /**
     * Get default payment method from response
     *
     * @return array<string, mixed>|null Default payment method, or null if none
     */
    public function getDefaultPaymentMethod(Response $response): ?array
    {
        $methods = $this->getPaymentMethods($response);

        foreach ($methods as $method) {
            if (($method['IsDefault'] ?? false) === true) {
                return $method;
            }
        }

        // If no method marked as default, return first method
        return $methods[0] ?? null;
    }

    /**
     * Configure request timeout
     *
     * Payment method queries are fast
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
