<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Token;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\TokenResponse;

/**
 * Validate Token Request
 *
 * Validates that a permanent token is still active and usable.
 * Performs a test charge of 1 ILS to verify token validity.
 *
 * Validation Reasons:
 * - Token expired (cards expire)
 * - Card was cancelled/replaced by issuer
 * - Card reached credit limit
 * - Fraud flag on card
 * - Token was deleted from SUMIT system
 *
 * Use Cases:
 * - Before processing recurring payment (avoid failed charges)
 * - After long period of inactivity (verify card still valid)
 * - Before high-value transaction (pre-validate)
 * - Maintenance: Clean up expired tokens from database
 *
 * IMPORTANT:
 * - Test charge of 1 ILS is processed then immediately refunded by SUMIT
 * - Customer MAY see temporary 1 ILS hold (depends on issuer)
 * - Some issuers decline test transactions ($0 or very small amounts)
 * - J2 tokens: CVV not required
 * - J5 tokens: CVV required (must be provided)
 *
 * Request Structure:
 * - Credentials: CompanyID + APIKey
 * - Token: Permanent token to validate (J2/J5)
 * - Amount: 1 (test amount)
 * - CVV: Required for J5 tokens
 * - CitizenID: Required if token was created with ID
 *
 * Response:
 * - Status: 0 = API success
 * - Data.Success: true = token valid and usable
 * - Data.Success: false = token invalid/expired/declined
 */
class ValidateTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new token validation request
     *
     * @param string $token Permanent token to validate (J2/J5 format)
     * @param CredentialsData $credentials SUMIT API credentials
     * @param string|null $cvv CVV code (required for J5 tokens)
     * @param string|null $citizenId Israeli ID (if token was created with ID)
     */
    public function __construct(
        protected readonly string $token,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $cvv = null,
        protected readonly ?string $citizenId = null,
    ) {}

    /**
     * Define the endpoint
     *
     * Uses standard transaction endpoint with test amount
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/creditguy/gateway/transaction/';
    }

    /**
     * Build request body
     *
     * Test transaction with 1 ILS to validate token
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'Credentials' => $this->credentials->toArray(),
            'Token' => $this->token,
            'Amount' => 1, // Test amount
            'Currency' => 'ILS',
            'NumPayments' => 1,
        ];

        // CVV required for J5 tokens
        if ($this->cvv) {
            $body['CVV'] = $this->cvv;
        }

        // CitizenID if token was created with it
        if ($this->citizenId) {
            $body['CitizenID'] = $this->citizenId;
        }

        return $body;
    }

    /**
     * Cast response to TokenResponse DTO
     *
     * Success indicates token is valid and usable
     *
     * @param Response $response
     * @return TokenResponse
     */
    public function createDtoFromResponse(Response $response): TokenResponse
    {
        return TokenResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Validation is usually fast
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
