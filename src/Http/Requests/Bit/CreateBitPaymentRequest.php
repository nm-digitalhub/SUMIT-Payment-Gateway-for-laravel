<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Bit;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;

/**
 * Create Bit Payment Request
 *
 * Initiates Bit payment flow via SUMIT gateway.
 *
 * Bit Payment Flow:
 * 1. Call this request → Get RedirectURL
 * 2. Redirect customer to RedirectURL (Bit payment page)
 * 3. Customer completes payment in Bit app
 * 4. Bit redirects back to success/cancel URL
 * 5. SUMIT sends webhook/IPN with payment confirmation
 * 6. Process webhook via BitWebhookController
 *
 * What Is Bit:
 * - Israeli payment app (mobile wallet)
 * - No credit card needed
 * - Direct bank transfer
 * - Popular in Israel for online payments
 *
 * Use Cases:
 * - Accept Bit payments in Israel
 * - Provide alternative to credit cards
 * - Lower fees than credit cards
 * - Instant payment confirmation
 *
 * Request Parameters:
 * - Items: Product/service line items with VAT
 * - Customer: Name, email, phone, address
 * - RedirectURL: Success page after payment
 * - CancelRedirectURL: Cancel page if customer cancels
 * - IPNURL: Webhook URL for payment confirmation
 * - AutomaticallyRedirectToProviderPaymentPage: 'UpayBit'
 *
 * Response:
 * - RedirectURL: Bit payment page URL
 * - Customer redirects to this URL immediately
 *
 * IMPORTANT:
 * - Requires merchant_number configuration
 * - IPN URL must include orderid and orderkey for security
 * - Create pending transaction before redirect
 * - Webhook confirms payment asynchronously
 * - Support both success and cancel flows
 *
 * Security:
 * - IPN URL must validate order key
 * - Implement idempotency for webhook retries
 * - SUMIT retries webhook up to 5 times
 *
 * Testing:
 * - Use testing mode for authorize-only
 * - Draft documents in testing
 * - Real payments in production only
 */
class CreateBitPaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new Bit payment request
     *
     * @param array<int, array<string, mixed>> $items Order items with VAT
     * @param array<string, mixed> $customer Customer data
     * @param string $redirectUrl Success redirect URL
     * @param string $cancelRedirectUrl Cancel redirect URL
     * @param string $ipnUrl Webhook/IPN URL for payment confirmation
     * @param CredentialsData $credentials SUMIT API credentials
     * @param string|null $documentDescription Optional order description
     * @param int $paymentsCount Number of payments (default: 1)
     * @param int $maximumPayments Maximum payments allowed (default: 1)
     * @param string $documentLanguage Document language (he/en/fr)
     * @param bool $authorizeOnly Test mode (default: false)
     * @param bool $draftDocument Draft document mode (default: false)
     * @param bool $sendDocumentByEmail Send document via email (default: true)
     */
    public function __construct(
        protected readonly array $items,
        protected readonly array $customer,
        protected readonly string $redirectUrl,
        protected readonly string $cancelRedirectUrl,
        protected readonly string $ipnUrl,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $documentDescription = null,
        protected readonly int $paymentsCount = 1,
        protected readonly int $maximumPayments = 1,
        protected readonly string $documentLanguage = 'he',
        protected readonly bool $authorizeOnly = false,
        protected readonly bool $draftDocument = false,
        protected readonly bool $sendDocumentByEmail = true,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/billing/payments/beginredirect/';
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
            'Items' => $this->items,
            'VATIncluded' => 'true',
            'VATRate' => 17, // Default Israeli VAT
            'Customer' => $this->customer,
            'AuthoriseOnly' => $this->authorizeOnly ? 'true' : 'false',
            'DraftDocument' => $this->draftDocument ? 'true' : 'false',
            'SendDocumentByEmail' => $this->sendDocumentByEmail ? 'true' : 'false',
            'Payments_Count' => $this->paymentsCount,
            'MaximumPayments' => $this->maximumPayments,
            'DocumentLanguage' => $this->documentLanguage,
            'RedirectURL' => $this->redirectUrl,
            'CancelRedirectURL' => $this->cancelRedirectUrl,
            'AutomaticallyRedirectToProviderPaymentPage' => 'UpayBit', // Force Bit
            'IPNURL' => $this->ipnUrl,
        ];

        // Optional document description
        if ($this->documentDescription) {
            $body['DocumentDescription'] = $this->documentDescription;
        }

        // Merchant number from config (required for Bit)
        $merchantNumber = config('officeguy.merchant_number');
        if ($merchantNumber) {
            $body['MerchantNumber'] = $merchantNumber;
        }

        return $body;
    }

    /**
     * Cast response to array
     *
     * Returns RedirectURL for customer redirect
     *
     * @param Response $response
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Configure request timeout
     *
     * Bit redirect setup is fast
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
