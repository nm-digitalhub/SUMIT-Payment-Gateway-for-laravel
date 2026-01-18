<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Payment;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;

/**
 * Set Payment Method for Customer Request
 *
 * Sets or updates a payment method for a SUMIT customer (also sets as default).
 *
 * Payment Method Flow:
 * 1. Have SUMIT CustomerID (from customer creation)
 * 2. Have either permanent token or single-use token
 * 3. Call this request to attach payment method to customer
 * 4. SUMIT sets this as the default payment method
 * 5. Customer can now be charged without entering card again
 *
 * Use Cases:
 * - Attach saved card to customer profile
 * - Update customer's default payment method
 * - Enable one-click checkout
 * - Set up recurring billing
 * - Replace expired payment method
 *
 * Request Parameters:
 * - Customer ID: SUMIT customer ID (required)
 * - Token: Either permanent token (UUID) or single-use token (required)
 * - PaymentMethod: Additional fields like expiry dates (optional)
 *
 * Token Types:
 * - Permanent Token: UUID format (e.g., "12345678-1234-1234-1234-123456789012")
 *   - Sent as PaymentMethod.CreditCard_Token
 *   - Can be reused for future charges
 *   - Stored in officeguy_tokens table
 * - Single-Use Token: From PaymentsJS SDK
 *   - Sent as SingleUseToken field
 *   - Converted to permanent token by SUMIT
 *   - Only valid for one operation
 *
 * Response Data (on success):
 * - Success status
 * - Payment method attached to customer
 * - Set as default payment method
 *
 * IMPORTANT:
 * - Endpoint automatically sets as DEFAULT payment method
 * - Existing default will be replaced
 * - Use for both initial setup and updates
 * - Token format determines which field to use
 *
 * Error Handling:
 * - Invalid CustomerID → "Customer not found"
 * - Invalid Token → "Token invalid or expired"
 * - Expired Card → "Card expired"
 * - Insufficient permissions → "Access denied"
 *
 * Best Practices:
 * - Validate CustomerID exists before calling
 * - Check token expiry for permanent tokens
 * - Use single-use tokens from PaymentsJS for PCI compliance
 * - Log all payment method changes
 * - Notify customer when default changes
 *
 * Integration:
 * - Called after token creation (TokenService)
 * - Used in subscription setup
 * - Used in profile management
 * - Required for recurring billing
 */
class SetPaymentMethodRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new set payment method request
     *
     * @param int $customerId SUMIT customer ID
     * @param string $token Either permanent token (UUID) or single-use token
     * @param CredentialsData $credentials SUMIT API credentials
     * @param array<string, mixed> $additionalFields Optional PaymentMethod fields
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly string $token,
        protected readonly CredentialsData $credentials,
        protected readonly array $additionalFields = [],
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/billing/paymentmethods/setforcustomer/';
    }

    /**
     * Build request body
     *
     * Token format determines body structure:
     * - UUID format → PaymentMethod.CreditCard_Token
     * - Other → SingleUseToken
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

        // Detect token type by format
        // Permanent tokens are UUIDs: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $this->token)) {
            // Permanent token - use PaymentMethod structure
            $body['PaymentMethod'] = array_merge([
                'Type' => 1, // CreditCard type
                'CreditCard_Token' => $this->token,
            ], $this->additionalFields);
        } else {
            // Single-use token - use SingleUseToken field
            $body['SingleUseToken'] = $this->token;
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
     *     "Success": true
     *   }
     * }
     *
     * Error response structure:
     * {
     *   "Status": 1,
     *   "UserErrorMessage": "Token invalid or expired"
     * }
     *
     * @param Response $response
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Check if operation was successful
     *
     * @param Response $response
     * @return bool True if payment method was set successfully
     */
    public function isSuccessful(Response $response): bool
    {
        $data = $response->json();
        return ($data['Status'] ?? 1) === 0;
    }

    /**
     * Get error message from failed request
     *
     * @param Response $response
     * @return string|null Error message, or null if successful
     */
    public function getErrorMessage(Response $response): ?string
    {
        $data = $response->json();

        if (($data['Status'] ?? 1) === 0) {
            return null;
        }

        return $data['UserErrorMessage'] ?? 'Failed to set payment method';
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
