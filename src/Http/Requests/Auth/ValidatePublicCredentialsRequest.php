<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Auth;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Validate Public Credentials Request
 *
 * Validate public API credentials (CompanyID + APIPublicKey) with SUMIT.
 *
 * Public Key Authentication Flow:
 * 1. User enters CompanyID and APIPublicKey in settings
 * 2. Call this request to verify public key
 * 3. SUMIT attempts tokenization with fake card data
 * 4. Success indicates valid public key
 * 5. Save credentials if validation succeeds
 *
 * Use Cases:
 * - Validate public key during initial setup
 * - Test PaymentsJS/Hosted Fields integration
 * - Verify public key before enabling PCI mode 'no'
 * - Troubleshoot tokenization issues
 * - Rotate public keys (test new key before replacing)
 *
 * Request Parameters:
 * - Credentials: CompanyID + APIPublicKey (required)
 * - Fake card data: Used to test tokenization endpoint
 *   - CardNumber: "12345678" (test number)
 *   - ExpirationMonth: "01"
 *   - ExpirationYear: "2030"
 *   - CVV: "123"
 *   - CitizenID: "123456789"
 *
 * Response Data (on success):
 * - Single-use token (test token, not saved)
 * - Validation status
 *
 * IMPORTANT:
 * - This validates PUBLIC API key (client-side safe)
 * - For private key validation, use ValidateCredentialsRequest
 * - Public key is exposed in JavaScript (PaymentsJS SDK)
 * - Fake card data is for testing only (not a real transaction)
 * - No actual payment is processed
 *
 * Difference from ValidateCredentialsRequest:
 * - ValidateCredentialsRequest: Tests private APIKey (server-side)
 * - ValidatePublicCredentialsRequest: Tests public APIPublicKey (client-side)
 * - Different endpoints and authentication methods
 * - Public key used in PCI mode 'no' (PaymentsJS/Hosted Fields)
 *
 * Error Handling:
 * - Invalid CompanyID → "Company not found"
 * - Invalid APIPublicKey → "Authentication failed"
 * - Network error → null response
 * - Rate limited → "Too many requests"
 *
 * Best Practices:
 * - Validate before enabling PaymentsJS integration
 * - Show clear error messages to users
 * - Use in "Test Public Key" button
 * - Don't retry failed validation too quickly
 * - Log all validation attempts
 *
 * Integration:
 * - Used in Admin Settings Page (Test Public Key button)
 * - Used during PCI mode 'no' setup
 * - Used in PaymentsJS integration tests
 * - Used in health check commands
 */
class ValidatePublicCredentialsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new validate public credentials request
     *
     * @param  CredentialsData  $credentials  SUMIT public API credentials to validate
     */
    public function __construct(
        protected readonly CredentialsData $credentials,
    ) {}

    /**
     * Define the endpoint
     *
     * Uses tokenization endpoint to test public key
     */
    public function resolveEndpoint(): string
    {
        return '/creditguy/vault/tokenizesingleusejson/';
    }

    /**
     * Build request body
     *
     * Includes fake card data to test tokenization
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'Credentials' => $this->credentials->toArray(),
            // Fake card data for testing tokenization
            'CardNumber' => '12345678',
            'ExpirationMonth' => '01',
            'ExpirationYear' => '2030',
            'CVV' => '123',
            'CitizenID' => '123456789',
        ];
    }

    /**
     * Cast response to array
     *
     * Returns tokenization result if public key is valid
     *
     * Success response structure:
     * {
     *   "Status": "Success",
     *   "Token": "test_tok_123456789",
     *   "Data": {...}
     * }
     *
     * Error response structure:
     * {
     *   "Status": "Error",
     *   "UserErrorMessage": "Invalid public key"
     * }
     *
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Check if validation was successful
     *
     * @return bool True if public key is valid
     */
    public function isValid(Response $response): bool
    {
        $data = $response->json();

        return ($data['Status'] ?? '') === 'Success';
    }

    /**
     * Get error message from failed validation
     *
     * @return string|null Error message, or null if successful
     */
    public function getErrorMessage(Response $response): ?string
    {
        $data = $response->json();

        if (($data['Status'] ?? '') === 'Success') {
            return null;
        }

        return $data['UserErrorMessage'] ?? 'Unknown error';
    }

    /**
     * Configure request timeout
     *
     * Tokenization queries are fast
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
