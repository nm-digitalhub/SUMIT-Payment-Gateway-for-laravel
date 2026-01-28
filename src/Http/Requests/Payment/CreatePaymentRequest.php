<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Payment;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\PaymentData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\PaymentResponse;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Create Payment Request
 *
 * Processes a payment transaction via SUMIT gateway.
 * Supports 3 PCI modes: 'no' (PaymentsJS), 'yes' (Direct), 'redirect'.
 *
 * Request Structure:
 * - Credentials: CompanyID + APIKey (in body, not headers!)
 * - Amount: Transaction amount
 * - Currency: ILS, USD, EUR (default: ILS)
 * - NumPayments: Installments (1-36)
 * - Payment Method: SingleUseToken OR Card Data OR Token
 * - Optional: ParamJ (J2/J5 for token storage), TransactionType (1=charge, 2=authorize)
 *
 * Response:
 * - Status: 0 = API success
 * - Data.Success: true = transaction approved
 * - Data.TransactionID: Confirmation code
 * - Data.Token: Permanent token (if ParamJ provided)
 */
class CreatePaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new payment request
     *
     * @param  PaymentData  $payment  Payment transaction data
     * @param  CredentialsData  $credentials  SUMIT API credentials
     */
    public function __construct(
        protected readonly PaymentData $payment,
        protected readonly CredentialsData $credentials
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/creditguy/gateway/transaction/';
    }

    /**
     * Build request body
     *
     * Combines credentials with payment data.
     * CRITICAL: Credentials go in body, not headers!
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        return [
            'Credentials' => $this->credentials->toArray(),
            ...$this->payment->toArray(),
        ];
    }

    /**
     * Cast response to PaymentResponse DTO
     *
     * This method is called by Saloon when using `$response->dto()`
     */
    public function createDtoFromResponse(Response $response): PaymentResponse
    {
        return PaymentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Payment requests can take up to 180 seconds (documents, subscription creation)
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 180,
        ];
    }
}
