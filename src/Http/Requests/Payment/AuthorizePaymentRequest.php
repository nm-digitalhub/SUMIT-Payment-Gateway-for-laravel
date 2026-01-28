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
 * Authorize Payment Request
 *
 * Creates a hold on customer's card without capturing funds immediately.
 * Use CapturePaymentRequest later to complete the transaction.
 *
 * Authorization vs Charge:
 * - Authorization (TransactionType=2): Holds funds, no transfer
 * - Charge (TransactionType=1): Immediate fund transfer
 *
 * Use Cases:
 * - Hotels: Authorize on booking, capture on checkout
 * - Pre-orders: Authorize on order, capture on shipment
 * - Rentals: Authorize deposit, capture only if damage occurs
 * - Events: Authorize on registration, capture closer to event date
 *
 * Authorization Lifecycle:
 * 1. AuthorizePaymentRequest → Hold created (TransactionID returned)
 * 2. Wait for fulfillment/service delivery
 * 3. CapturePaymentRequest → Funds transferred
 * 4. OR: Void/cancel if service not provided
 *
 * IMPORTANT:
 * - Authorizations typically expire after 7-30 days (card issuer dependent)
 * - Capture amount can be less than authorized (partial capture)
 * - Capture amount CANNOT exceed authorized amount
 * - Multiple partial captures may be supported (check with SUMIT)
 *
 * Request Structure:
 * - Credentials: CompanyID + APIKey
 * - TransactionType: 2 (authorize-only, NOT charge)
 * - Amount: Amount to authorize
 * - Payment Method: SingleUseToken OR Card Data OR Token
 * - Optional: ParamJ for token storage, OrderID for reference
 */
class AuthorizePaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new authorization request
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
     *
     * IMPORTANT: Same endpoint as regular payments!
     * TransactionType=2 parameter determines authorization behavior
     */
    public function resolveEndpoint(): string
    {
        return '/creditguy/gateway/transaction/';
    }

    /**
     * Build request body
     *
     * Forces TransactionType=2 (authorize-only) regardless of PaymentData value.
     * This ensures explicit authorization behavior.
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $paymentArray = $this->payment->toArray();

        // CRITICAL: Force TransactionType=2 for authorization
        $paymentArray['TransactionType'] = 2;

        return [
            'Credentials' => $this->credentials->toArray(),
            ...$paymentArray,
        ];
    }

    /**
     * Cast response to PaymentResponse DTO
     *
     * Response contains TransactionID needed for future capture
     */
    public function createDtoFromResponse(Response $response): PaymentResponse
    {
        return PaymentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Authorization requests can take up to 180 seconds
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
