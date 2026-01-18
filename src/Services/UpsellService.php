<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Events\UpsellPaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Events\UpsellPaymentFailed;

/**
 * Upsell Service
 *
 * Port of OfficeGuyCartFlow.php and class-cartflows-pro-gateway-officeguy.php from WooCommerce plugin.
 * Handles upsell/downsell payments using saved tokens from initial charge.
 */
class UpsellService
{
    /**
     * Process an upsell charge using a saved token
     *
     * @param Payable $upsellOrder The upsell order to charge
     * @param OfficeGuyToken $token The token from the initial charge
     * @param int|string|null $parentOrderId The ID of the original/parent order
     * @param int $paymentsCount Number of installments
     * @return array
     */
    public static function processUpsellCharge(
        Payable $upsellOrder,
        OfficeGuyToken $token,
        int|string|null $parentOrderId = null,
        int $paymentsCount = 1
    ): array {
        try {
            // Create credentials DTO
            $credentials = new \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Extract request parameters
            $orderTotal = round($upsellOrder->getPayableAmount(), 2);
            $items = PaymentService::getPaymentOrderItems($upsellOrder);
            $vatIncluded = 'true';
            $vatRate = PaymentService::getOrderVatRate($upsellOrder);
            $customer = PaymentService::getOrderCustomer($upsellOrder);
            $authoriseOnly = 'false'; // Upsells are always immediate charges
            $draftDocument = config('officeguy.draft_document', false) ? 'true' : 'false';
            $sendDocumentByEmail = config('officeguy.email_document', true) ? 'true' : 'false';
            $documentDescription = __('Upsell - Order number') . ': ' . $upsellOrder->getPayableId() .
                ($parentOrderId ? ' (' . __('Original order') . ': ' . $parentOrderId . ')' : '');
            $maximumPayments = PaymentService::getMaximumPayments($orderTotal);
            $documentLanguage = PaymentService::getOrderLanguage();
            $paymentMethod = TokenService::getPaymentMethodFromToken($token);
            $environment = config('officeguy.environment', 'www');

            // Instantiate connector and inline request
            $connector = new \OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector();
            $request = new class(
                $credentials,
                $items,
                $vatIncluded,
                $vatRate,
                $customer,
                $authoriseOnly,
                $draftDocument,
                $sendDocumentByEmail,
                $documentDescription,
                $paymentsCount,
                $maximumPayments,
                $documentLanguage,
                $paymentMethod
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly \OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData $credentials,
                    protected readonly array $items,
                    protected readonly string $vatIncluded,
                    protected readonly int $vatRate,
                    protected readonly array $customer,
                    protected readonly string $authoriseOnly,
                    protected readonly string $draftDocument,
                    protected readonly string $sendDocumentByEmail,
                    protected readonly string $documentDescription,
                    protected readonly int $paymentsCount,
                    protected readonly int $maximumPayments,
                    protected readonly int $documentLanguage,
                    protected readonly array $paymentMethod
                ) {}

                public function resolveEndpoint(): string
                {
                    return '/billing/payments/charge/';
                }

                protected function defaultBody(): array
                {
                    return [
                        'Credentials' => $this->credentials->toArray(),
                        'Items' => $this->items,
                        'VATIncluded' => $this->vatIncluded,
                        'VATRate' => $this->vatRate,
                        'Customer' => $this->customer,
                        'AuthoriseOnly' => $this->authoriseOnly,
                        'DraftDocument' => $this->draftDocument,
                        'SendDocumentByEmail' => $this->sendDocumentByEmail,
                        'DocumentDescription' => $this->documentDescription,
                        'Payments_Count' => $this->paymentsCount,
                        'MaximumPayments' => $this->maximumPayments,
                        'DocumentLanguage' => $this->documentLanguage,
                        'PaymentMethod' => $this->paymentMethod,
                    ];
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            // Send request
            $saloonResponse = $connector->send($request);
            $response = $saloonResponse->json();

            // Build request array for logging
            $requestArray = [
                'Credentials' => $credentials->toArray(),
                'Items' => $items,
                'VATIncluded' => $vatIncluded,
                'VATRate' => $vatRate,
                'Customer' => $customer,
                'AuthoriseOnly' => $authoriseOnly,
                'DraftDocument' => $draftDocument,
                'SendDocumentByEmail' => $sendDocumentByEmail,
                'DocumentDescription' => $documentDescription,
                'Payments_Count' => $paymentsCount,
                'MaximumPayments' => $maximumPayments,
                'DocumentLanguage' => $documentLanguage,
                'PaymentMethod' => $paymentMethod,
            ];

        } catch (\Throwable $e) {
            event(new UpsellPaymentFailed(
                $upsellOrder->getPayableId(),
                $parentOrderId,
                'Request exception: ' . $e->getMessage()
            ));

            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . $e->getMessage(),
            ];
        }

        if (!$response) {
            event(new UpsellPaymentFailed(
                $upsellOrder->getPayableId(),
                $parentOrderId,
                'No response from gateway'
            ));

            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . __('No response'),
            ];
        }

        $status = $response['Status'] ?? null;
        $payment = $response['Data']['Payment'] ?? null;

        if ($status === 0 && $payment && ($payment['ValidPayment'] ?? false) === true) {
            // Create transaction record for upsell
            $transaction = OfficeGuyTransaction::create([
                'order_id' => $upsellOrder->getPayableId(),
                'order_type' => get_class($upsellOrder),
                'payment_id' => $payment['ID'] ?? null,
                'document_id' => $response['Data']['DocumentID'] ?? null,
                'customer_id' => $response['Data']['CustomerID'] ?? null,
                'auth_number' => $payment['AuthNumber'] ?? null,
                'amount' => $payment['Amount'] ?? $orderTotal,
                'first_payment_amount' => $payment['FirstPaymentAmount'] ?? null,
                'non_first_payment_amount' => $payment['NonFirstPaymentAmount'] ?? null,
                'status' => 'completed',
                'status_description' => $payment['StatusDescription'] ?? null,
                'payment_method' => 'card',
                'last_digits' => $payment['PaymentMethod']['CreditCard_LastDigits'] ?? null,
                'expiration_month' => $payment['PaymentMethod']['CreditCard_ExpirationMonth'] ?? null,
                'expiration_year' => $payment['PaymentMethod']['CreditCard_ExpirationYear'] ?? null,
                'raw_request' => $requestArray,
                'raw_response' => $response,
                'environment' => $environment,
                'is_test' => config('officeguy.testing', false),
                'is_upsell' => true,
                'parent_transaction_id' => self::getParentTransactionId($parentOrderId),
            ]);

            event(new UpsellPaymentCompleted(
                $upsellOrder->getPayableId(),
                $parentOrderId,
                $payment,
                $response
            ));

            return [
                'success' => true,
                'payment' => $payment,
                'response' => $response,
                'transaction' => $transaction,
            ];
        }

        // Failure
        $message = $status !== 0
            ? ($response['UserErrorMessage'] ?? 'Gateway error')
            : ($payment['StatusDescription'] ?? 'Declined');

        event(new UpsellPaymentFailed(
            $upsellOrder->getPayableId(),
            $parentOrderId,
            $message
        ));

        return [
            'success' => false,
            'message' => __('Payment failed') . ' - ' . $message,
            'response' => $response,
        ];
    }

    /**
     * Get the token from the original order's transaction
     *
     * @param int|string $orderId
     * @return OfficeGuyToken|null
     */
    public static function getTokenFromOrderTransaction(int|string $orderId): ?OfficeGuyToken
    {
        $transaction = OfficeGuyTransaction::where('order_id', $orderId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$transaction) {
            return null;
        }

        // Check if there's a token saved from this transaction
        $response = $transaction->raw_response;

        if (!isset($response['Data']['CardToken'])) {
            return null;
        }

        // Find or create token from the response
        return OfficeGuyToken::where('token', $response['Data']['CardToken'])->first();
    }

    /**
     * Get the customer's default token
     *
     * @param mixed $customer
     * @return OfficeGuyToken|null
     */
    public static function getCustomerDefaultToken(mixed $customer): ?OfficeGuyToken
    {
        return OfficeGuyToken::where('owner_type', get_class($customer))
            ->where('owner_id', $customer->getKey())
            ->where('is_default', true)
            ->first();
    }

    /**
     * Process upsell with automatic token detection
     *
     * @param Payable $upsellOrder
     * @param int|string $parentOrderId
     * @param mixed|null $customer Customer model for fallback to default token
     * @param int $paymentsCount
     * @return array
     */
    public static function processUpsellWithAutoToken(
        Payable $upsellOrder,
        int|string $parentOrderId,
        mixed $customer = null,
        int $paymentsCount = 1
    ): array {
        // Try to get token from parent order transaction
        $token = self::getTokenFromOrderTransaction($parentOrderId);

        // Fallback to customer's default token
        if (!$token && $customer) {
            $token = self::getCustomerDefaultToken($customer);
        }

        if (!$token) {
            return [
                'success' => false,
                'message' => __('No saved payment method found for upsell'),
            ];
        }

        return self::processUpsellCharge($upsellOrder, $token, $parentOrderId, $paymentsCount);
    }

    /**
     * Get parent transaction ID from parent order ID
     *
     * @param int|string|null $parentOrderId
     * @return int|null
     */
    protected static function getParentTransactionId(int|string|null $parentOrderId): ?int
    {
        if (!$parentOrderId) {
            return null;
        }

        $transaction = OfficeGuyTransaction::where('order_id', $parentOrderId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        return $transaction?->id;
    }

    /**
     * Check if upsell is available for an order
     * (Order must have completed transaction with token available)
     *
     * @param int|string $orderId
     * @return bool
     */
    public static function isUpsellAvailable(int|string $orderId): bool
    {
        return self::getTokenFromOrderTransaction($orderId) !== null;
    }
}
