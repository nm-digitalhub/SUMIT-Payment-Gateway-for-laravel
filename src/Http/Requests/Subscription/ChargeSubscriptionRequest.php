<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Subscription;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\PaymentResponse;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Charge Subscription Request
 *
 * Process recurring payment for an existing subscription.
 *
 * Recurring Payment Flow:
 * 1. Initial subscription created with RecurringPaymentID
 * 2. Call this request with RecurringPaymentID to charge
 * 3. SUMIT charges saved payment method
 * 4. Document generated automatically
 * 5. Email sent to customer (if enabled)
 *
 * Use Cases:
 * - Monthly subscription renewals
 * - Annual subscription charges
 * - Custom interval billing
 * - Automatic recurring payments
 *
 * Request Parameters:
 * - RecurringPaymentID: Subscription ID from SUMIT
 * - Items: Product/service line items with VAT
 * - VATIncluded: Always 'true' for subscriptions
 * - SendDocumentByEmail: Send invoice to customer
 * - DocumentDescription: Subscription description
 * - DocumentLanguage: Invoice language (he/en/fr)
 * - ExternalReference: Your internal reference
 *
 * Response:
 * - TransactionID: Payment transaction ID
 * - DocumentID: Generated invoice ID
 * - DocumentURL: Invoice PDF URL
 * - Success: Payment status
 *
 * IMPORTANT:
 * - RecurringPaymentID must exist in SUMIT system
 * - Payment method must be active
 * - Subscription must not be cancelled
 * - Document generated automatically
 * - Customer notified via email (if enabled)
 *
 * Error Handling:
 * - Invalid RecurringPaymentID → Status error
 * - Expired payment method → Payment failed
 * - Insufficient funds → Payment failed
 * - Cancelled subscription → Status error
 *
 * Testing:
 * - Use authorize-only mode for testing
 * - Draft documents in testing mode
 * - Real charges in production only
 */
class ChargeSubscriptionRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new charge subscription request
     *
     * @param  string  $recurringPaymentId  SUMIT subscription ID
     * @param  array<int, array<string, mixed>>  $items  Order items with VAT
     * @param  CredentialsData  $credentials  SUMIT API credentials
     * @param  string|null  $documentDescription  Order/subscription description
     * @param  string  $documentLanguage  Document language (he/en/fr)
     * @param  bool  $sendDocumentByEmail  Send invoice via email
     * @param  string|null  $externalReference  Your internal reference
     */
    public function __construct(
        protected readonly string $recurringPaymentId,
        protected readonly array $items,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $documentDescription = null,
        protected readonly string $documentLanguage = 'he',
        protected readonly bool $sendDocumentByEmail = true,
        protected readonly ?string $externalReference = null,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/billing/recurring/charge/';
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
            'RecurringPaymentID' => $this->recurringPaymentId,
            'Items' => $this->items,
            'VATIncluded' => 'true', // Always true for subscriptions
            'SendDocumentByEmail' => $this->sendDocumentByEmail ? 'true' : 'false',
            'DocumentLanguage' => $this->documentLanguage,
        ];

        // Optional document description
        if ($this->documentDescription) {
            $body['DocumentDescription'] = $this->documentDescription;
        }

        // Optional external reference (for tracking)
        if ($this->externalReference) {
            $body['ExternalReference'] = $this->externalReference;
        }

        return $body;
    }

    /**
     * Cast response to PaymentResponse DTO
     *
     * Returns transaction and document details
     */
    public function createDtoFromResponse(Response $response): PaymentResponse
    {
        return PaymentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Recurring charges can take time (payment processing, document generation, email)
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 180, // Longer timeout for recurring charge
        ];
    }
}
