<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Payment;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;

/**
 * Charge Payment Request
 *
 * Process a payment charge or refund using stored payment method.
 *
 * Payment Charge Flow:
 * 1. Have SUMIT CustomerID with saved payment method
 * 2. Build charge request with amount and items
 * 3. Call this request to charge customer
 * 4. SUMIT processes payment and returns result
 *
 * Refund Flow:
 * 1. Have original transaction ID/auth number
 * 2. Build refund request with negative amount
 * 3. Set SupportCredit flag to true
 * 4. SUMIT processes refund and returns result
 *
 * Use Cases:
 * - Charge customer with saved payment method
 * - Test payment method validity (₪1 test charge)
 * - Process refunds for completed payments
 * - Charge for subscription renewals
 * - Process one-click checkouts
 * - Charge for cart abandonments
 *
 * Request Parameters:
 * - Customer ID: SUMIT customer ID (required)
 * - Amount: Charge amount (positive) or refund (negative with SupportCredit)
 * - PaymentMethod: Token for charge, or omit for default customer method
 * - Items: Optional itemized list with quantities/prices
 * - Description: Charge description
 * - Cancelable: Allow future cancellation (bool)
 * - SupportCredit: Enable refund support (bool, required for refunds)
 * - Payment.CreditCardAuthNumber: Original transaction for refunds
 *
 * Charge Example:
 * {
 *   "Customer": {"ID": 123456},
 *   "PaymentMethod": {"CreditCard_Token": "uuid", "Type": 1},
 *   "Amount": 100.00,
 *   "Description": "Product purchase",
 *   "Cancelable": true
 * }
 *
 * Refund Example:
 * {
 *   "Customer": {"ID": 123456},
 *   "Items": [{"Item": {"Name": "Refund reason"}, "Quantity": 1, "UnitPrice": -100.00}],
 *   "Payment": {"CreditCardAuthNumber": "original_txn_id"},
 *   "SupportCredit": true,
 *   "VATIncluded": false
 * }
 *
 * Response Data (on success):
 * - Payment object with:
 *   - ID (transaction ID)
 *   - AuthNumber (authorization number)
 *   - ValidPayment (bool)
 *   - Amount
 *   - Currency
 *   - PaymentMethod details
 *
 * IMPORTANT:
 * - Customer must have active payment method (unless token provided)
 * - Refunds require SupportCredit flag and negative amounts
 * - Test charges (₪1) should use Cancelable flag
 * - Refunds reference original transaction via CreditCardAuthNumber
 * - Different from CreatePaymentRequest (uses /creditguy/gateway/transaction/)
 *
 * Error Handling:
 * - Invalid CustomerID → "Customer not found"
 * - No payment method → "No payment method found"
 * - Invalid token → "Token invalid or expired"
 * - Card declined → "Payment declined"
 * - Insufficient funds → "Insufficient funds"
 * - Invalid refund → "Invalid refund parameters"
 *
 * Best Practices:
 * - Validate customer has payment method before charge
 * - Use test charges (₪1) to validate tokens
 * - Set Cancelable=true for test charges
 * - Reference original transaction for refunds
 * - Log all charge/refund operations
 * - Notify customer after successful charge/refund
 *
 * Integration:
 * - Used in token validation (test payments)
 * - Used in subscription renewals
 * - Used in refund processing
 * - Used in one-click checkouts
 * - Used in cart abandonment recovery
 */
class ChargePaymentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new charge payment request
     *
     * @param int $customerId SUMIT customer ID
     * @param float $amount Charge amount (positive) or refund (negative with items)
     * @param CredentialsData $credentials SUMIT API credentials
     * @param string|null $token Payment token (UUID), or null to use customer's default method
     * @param string|null $description Payment description
     * @param bool $cancelable Allow future cancellation (useful for test charges)
     * @param bool $supportCredit Enable refund support (required for refunds)
     * @param array<string, mixed> $items Itemized list (required for refunds)
     * @param string|null $originalTransactionId Original transaction for refunds
     * @param bool $vatIncluded VAT included in amount (default: false for refunds)
     */
    public function __construct(
        protected readonly int $customerId,
        protected readonly float $amount,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $token = null,
        protected readonly ?string $description = null,
        protected readonly bool $cancelable = false,
        protected readonly bool $supportCredit = false,
        protected readonly array $items = [],
        protected readonly ?string $originalTransactionId = null,
        protected readonly bool $vatIncluded = true,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/billing/payments/charge/';
    }

    /**
     * Build request body
     *
     * Supports both charge and refund operations
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'Credentials' => $this->credentials->toArray(),
            'Customer' => [
                'ID' => $this->customerId,
            ],
        ];

        // Refund mode (items with negative amounts)
        if (! empty($this->items)) {
            $body['Items'] = $this->items;

            if ($this->originalTransactionId) {
                $body['Payment'] = [
                    'CreditCardAuthNumber' => $this->originalTransactionId,
                ];
            }

            if ($this->supportCredit) {
                $body['SupportCredit'] = true;
            }

            $body['VATIncluded'] = $this->vatIncluded;
        }
        // Charge mode (simple amount)
        else {
            $body['Amount'] = $this->amount;

            if ($this->description) {
                $body['Description'] = $this->description;
            }

            if ($this->token) {
                $body['PaymentMethod'] = [
                    'CreditCard_Token' => $this->token,
                    'Type' => 1, // CreditCard type
                ];
            }

            if ($this->cancelable) {
                $body['Cancelable'] = true;
            }
        }

        return $body;
    }

    /**
     * Cast response to array
     *
     * Success response structure:
     * {
     *   "Status": 0,
     *   "Data": {
     *     "Payment": {
     *       "ID": 123456,
     *       "AuthNumber": "123456",
     *       "ValidPayment": true,
     *       "Amount": 100.00,
     *       "Currency": 0,
     *       "PaymentMethod": {
     *         "CreditCard_Token": "uuid",
     *         "CreditCard_LastDigits": "1234",
     *         "CreditCard_ExpirationMonth": 12,
     *         "CreditCard_ExpirationYear": 2025,
     *         "Type": 1
     *       }
     *     }
     *   }
     * }
     *
     * Error response structure:
     * {
     *   "Status": 1,
     *   "UserErrorMessage": "Payment declined"
     * }
     *
     * @param Response $response
     * @return array<string, mixed>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return $response->json();
    }

    /**
     * Check if operation was successful
     *
     * @param Response $response
     * @return bool True if payment was charged successfully
     */
    public function isSuccessful(Response $response): bool
    {
        $data = $response->json();
        return ($data['Status'] ?? 1) === 0;
    }

    /**
     * Check if payment is valid
     *
     * @param Response $response
     * @return bool True if ValidPayment flag is true
     */
    public function isValidPayment(Response $response): bool
    {
        $data = $response->json();
        $payment = $data['Data']['Payment'] ?? null;

        return $payment && ($payment['ValidPayment'] ?? false) === true;
    }

    /**
     * Get transaction ID from response
     *
     * @param Response $response
     * @return int|null Transaction ID (Payment.ID), or null if not found
     */
    public function getTransactionId(Response $response): ?int
    {
        $data = $response->json();
        return $data['Data']['Payment']['ID'] ?? null;
    }

    /**
     * Get authorization number from response
     *
     * @param Response $response
     * @return string|null Authorization number, or null if not found
     */
    public function getAuthNumber(Response $response): ?string
    {
        $data = $response->json();
        return $data['Data']['Payment']['AuthNumber'] ?? null;
    }

    /**
     * Get payment method details from response
     *
     * @param Response $response
     * @return array<string, mixed>|null Payment method details, or null
     */
    public function getPaymentMethod(Response $response): ?array
    {
        $data = $response->json();
        return $data['Data']['Payment']['PaymentMethod'] ?? null;
    }

    /**
     * Get error message from failed request
     *
     * @param Response $response
     * @return string|null Error message, or null if successful
     */
    public function getErrorMessage(Response $response): ?string
    {
        $data = $response->json();

        if (($data['Status'] ?? 1) === 0) {
            return null;
        }

        return $data['UserErrorMessage'] ?? 'Failed to charge payment';
    }

    /**
     * Configure request timeout
     *
     * Charge operations can take longer
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
