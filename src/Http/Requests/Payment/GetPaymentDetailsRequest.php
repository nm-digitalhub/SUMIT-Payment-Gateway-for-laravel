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
 * Get Payment Details Request
 *
 * Retrieve detailed information about a specific payment transaction.
 *
 * Payment Details Flow:
 * 1. Have SUMIT PaymentID (from transaction record or webhook)
 * 2. Call this request to get full payment details
 * 3. SUMIT returns complete transaction information
 * 4. Use for display, reconciliation, or status verification
 *
 * Use Cases:
 * - Display payment details to customer
 * - Verify payment status before fulfillment
 * - Reconcile transactions with accounting system
 * - Audit payment records
 * - Investigate payment issues
 * - Get card details (masked) for display
 *
 * Request Parameters:
 * - PaymentID: SUMIT payment ID (required)
 *
 * Response Data (on success):
 * - Payment object with:
 *   - PaymentID
 *   - TransactionID
 *   - Amount
 *   - Currency
 *   - Status (Approved/Declined/Pending)
 *   - CardNumber_Masked (last 4 digits)
 *   - ExpirationMonth/Year
 *   - NumPayments (installments)
 *   - CustomerID
 *   - OrderID
 *   - CreatedDate
 *   - ApprovalNumber
 *
 * Payment Object Example:
 * {
 *   "PaymentID": 123456,
 *   "TransactionID": "txn_789",
 *   "Amount": 100.00,
 *   "Currency": "ILS",
 *   "Status": "Approved",
 *   "CardNumber_Masked": "****1234",
 *   "ExpirationMonth": 12,
 *   "ExpirationYear": 2025,
 *   "NumPayments": 3,
 *   "CustomerID": 456,
 *   "OrderID": "ORDER-123",
 *   "CreatedDate": "2025-01-16T10:30:00",
 *   "ApprovalNumber": "123456"
 * }
 *
 * IMPORTANT:
 * - Returns full payment details including status
 * - Card numbers are always masked (security)
 * - Status may change after initial response (check again)
 * - Use for verification before fulfillment
 *
 * Error Handling:
 * - Invalid PaymentID → "Payment not found"
 * - No permissions → "Access denied"
 * - Payment in different company → "Payment not found"
 *
 * Best Practices:
 * - Cache results if querying frequently
 * - Verify payment status before fulfillment
 * - Log all payment detail queries
 * - Handle missing payment gracefully
 * - Use for reconciliation with local records
 *
 * Integration:
 * - Used in transaction detail pages
 * - Used in webhook processing
 * - Used in accounting exports
 * - Used in customer support tools
 */
class GetPaymentDetailsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new get payment details request
     *
     * @param int $paymentId SUMIT payment ID
     * @param CredentialsData $credentials SUMIT API credentials
     */
    public function __construct(
        protected readonly int $paymentId,
        protected readonly CredentialsData $credentials,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/billing/payments/get/';
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
            'PaymentID' => $this->paymentId,
        ];
    }

    /**
     * Cast response to array
     *
     * Success response structure:
     * {
     *   "Status": 0,
     *   "Data": {
     *     "Payment": {
     *       "PaymentID": 123456,
     *       "TransactionID": "txn_789",
     *       "Amount": 100.00,
     *       "Currency": "ILS",
     *       "Status": "Approved",
     *       "CardNumber_Masked": "****1234",
     *       "ExpirationMonth": 12,
     *       "ExpirationYear": 2025,
     *       "NumPayments": 3,
     *       "CustomerID": 456,
     *       "OrderID": "ORDER-123",
     *       "CreatedDate": "2025-01-16T10:30:00",
     *       "ApprovalNumber": "123456"
     *     }
     *   }
     * }
     *
     * Error response structure:
     * {
     *   "Status": 1,
     *   "UserErrorMessage": "Payment not found"
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
     * @return bool True if payment details were retrieved successfully
     */
    public function isSuccessful(Response $response): bool
    {
        $data = $response->json();
        return ($data['Status'] ?? 1) === 0;
    }

    /**
     * Get payment details from response
     *
     * @param Response $response
     * @return array<string, mixed>|null Payment details, or null if not found
     */
    public function getPayment(Response $response): ?array
    {
        $data = $response->json();
        return $data['Data']['Payment'] ?? null;
    }

    /**
     * Get payment status from response
     *
     * @param Response $response
     * @return string|null Payment status (Approved/Declined/Pending), or null
     */
    public function getPaymentStatus(Response $response): ?string
    {
        $payment = $this->getPayment($response);
        return $payment['Status'] ?? null;
    }

    /**
     * Check if payment was approved
     *
     * @param Response $response
     * @return bool True if payment status is "Approved"
     */
    public function isApproved(Response $response): bool
    {
        return $this->getPaymentStatus($response) === 'Approved';
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

        return $data['UserErrorMessage'] ?? 'Failed to fetch payment details';
    }

    /**
     * Configure request timeout
     *
     * Payment detail queries are fast
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
