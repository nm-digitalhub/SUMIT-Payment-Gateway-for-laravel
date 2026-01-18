<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Payment;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\PaymentResponse;

/**
 * Capture Payment Request
 *
 * Completes a previously authorized transaction (capture the held funds).
 *
 * Authorization Flow:
 * 1. AuthorizePaymentRequest creates hold on card (TransactionType=2)
 * 2. CapturePaymentRequest completes the charge (using TransactionID)
 * 3. Funds are transferred from hold to merchant account
 *
 * Use Cases:
 * - Hotel bookings (authorize on booking, capture on checkout)
 * - Pre-orders (authorize on order, capture on shipment)
 * - Partial captures (authorize $100, capture $80)
 *
 * Request Structure:
 * - Credentials: CompanyID + APIKey
 * - TransactionID: ID from authorization step
 * - Amount: Amount to capture (can be partial, <= authorized amount)
 * - Optional: OrderID for reference
 *
 * Response:
 * - Status: 0 = API success
 * - Data.Success: true = capture completed
 * - Data.TransactionID: New transaction ID for capture
 */
class CapturePaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new capture request
     *
     * @param string $authorizationId Transaction ID from previous authorization
     * @param float $amount Amount to capture (must be <= authorized amount)
     * @param CredentialsData $credentials SUMIT API credentials
     * @param string|null $orderId Optional order reference
     */
    public function __construct(
        protected readonly string $authorizationId,
        protected readonly float $amount,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $orderId = null,
    ) {}

    /**
     * Define the endpoint
     *
     * SUMIT uses the standard transaction endpoint for captures
     * with special parameters to indicate capture operation
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/creditguy/gateway/capture/';
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
            'TransactionID' => $this->authorizationId,
            'Amount' => $this->amount,
        ];

        if ($this->orderId) {
            $body['OrderID'] = $this->orderId;
        }

        return $body;
    }

    /**
     * Cast response to PaymentResponse DTO
     *
     * @param Response $response
     * @return PaymentResponse
     */
    public function createDtoFromResponse(Response $response): PaymentResponse
    {
        return PaymentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Capture operations are usually fast
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
