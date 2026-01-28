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
 * Validate Credentials Request
 *
 * Validate private API credentials (CompanyID + APIKey) with SUMIT.
 *
 * Authentication Flow:
 * 1. User enters CompanyID and APIKey in settings
 * 2. Call this request to verify credentials
 * 3. SUMIT validates and returns company details if valid
 * 4. Save credentials if validation succeeds
 *
 * Use Cases:
 * - Validate credentials during initial setup
 * - Test connection in Admin Settings Page
 * - Verify credentials before processing payments
 * - Troubleshoot API connection issues
 * - Rotate API keys (test new key before replacing)
 *
 * Request Parameters:
 * - Credentials: CompanyID + APIKey (required)
 *
 * Response Data (on success):
 * - Company details (name, status, settings)
 * - Account permissions
 * - Available features
 * - Rate limits
 *
 * IMPORTANT:
 * - This validates PRIVATE API key (server-side only)
 * - For public key validation, use ValidatePublicCredentialsRequest
 * - Always use HTTPS in production
 * - Never expose APIKey in client-side code
 * - Log validation attempts for security audit
 *
 * Error Handling:
 * - Invalid CompanyID → "Company not found"
 * - Invalid APIKey → "Authentication failed"
 * - Network error → null response
 * - Rate limited → "Too many requests"
 *
 * Best Practices:
 * - Validate before saving to database
 * - Show clear error messages to users
 * - Use in "Test Connection" button
 * - Don't retry failed validation too quickly
 * - Log all validation attempts
 *
 * Integration:
 * - Used in Admin Settings Page (Test Connection button)
 * - Used during initial package setup
 * - Used in credential rotation workflows
 * - Used in health check commands
 */
class ValidateCredentialsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new validate credentials request
     *
     * @param  CredentialsData  $credentials  SUMIT API credentials to validate
     */
    public function __construct(
        protected readonly CredentialsData $credentials,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/website/companies/getdetails/';
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
        ];
    }

    /**
     * Cast response to array
     *
     * Returns company details if credentials are valid
     *
     * Success response structure:
     * {
     *   "Status": "Success",
     *   "Data": {
     *     "CompanyName": "Example Ltd",
     *     "CompanyID": 1082100759,
     *     "Features": [...],
     *     "Settings": {...}
     *   }
     * }
     *
     * Error response structure:
     * {
     *   "Status": "Error",
     *   "UserErrorMessage": "Invalid credentials"
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
     * @return bool True if credentials are valid
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
     * Validation queries are fast
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
