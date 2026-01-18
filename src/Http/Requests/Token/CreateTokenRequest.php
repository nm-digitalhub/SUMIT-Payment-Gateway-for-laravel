<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Token;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\TokenData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\TokenResponse;

/**
 * Create Token Request
 *
 * Creates a permanent token from single-use token or card data.
 * Converts PaymentsJS single-use tokens to J2/J5 permanent tokens.
 *
 * Token Types (ParamJ):
 * - J2: CVV-less token (no CVV required for future charges)
 * - J5: CVV-required token (CVV required for future charges)
 * - J6: EMV token (for physical terminals)
 *
 * Request Structure:
 * - Credentials: CompanyID + APIPublicKey (public key for tokens!)
 * - ParamJ: J2/J5/J6 token type
 * - Amount: Always 1 (test charge to validate card)
 * - SingleUseToken: Token from PaymentsJS (PCI 'no' mode)
 * - OR Card Data: Direct card details (PCI 'yes' mode)
 *
 * Response:
 * - Status: 0 = API success
 * - Data.Success: true = token created
 * - Data.Token: Permanent token (J2/J5 format)
 * - Data.TransactionID: Confirmation code
 */
class CreateTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new token request
     *
     * @param TokenData $token Token creation data (single-use token or card data)
     * @param CredentialsData $credentials SUMIT API credentials (uses PUBLIC key!)
     */
    public function __construct(
        protected readonly TokenData $token,
        protected readonly CredentialsData $credentials
    ) {}

    /**
     * Define the endpoint
     *
     * IMPORTANT: Tokens use the SAME endpoint as payments!
     * ParamJ parameter determines token creation vs payment.
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
     * Combines credentials with token data.
     * CRITICAL: Use APIPublicKey (not APIKey) for token operations!
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'Credentials' => $this->credentials->toArray(),
            ...$this->token->toArray(),
        ];
    }

    /**
     * Cast response to TokenResponse DTO
     *
     * This method is called by Saloon when using `$response->dto()`
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
     * Token creation is usually fast (~2-5 seconds)
     * but allow 60 seconds for safety
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
