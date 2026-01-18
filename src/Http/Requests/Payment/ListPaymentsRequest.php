<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Payment;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use Carbon\Carbon;

/**
 * List Payments Request
 *
 * Retrieve paginated list of payment transactions with optional filters.
 *
 * Payment List Flow:
 * 1. Optionally specify date range and filters
 * 2. Call this request to get payment list
 * 3. SUMIT returns paginated payment records
 * 4. Use StartIndex for pagination (next page)
 *
 * Use Cases:
 * - Display payment history to customers
 * - Export payments for accounting
 * - Generate payment reports
 * - Reconcile transactions
 * - Search for specific payments by date range
 * - Filter only successful/valid payments
 *
 * Request Parameters:
 * - Date_From: Start date (ISO 8601 string, default: 1 year ago)
 * - Date_To: End date (ISO 8601 string, default: now)
 * - Valid: Filter valid payments only (bool, optional)
 * - StartIndex: Pagination offset (int, default: 0)
 *
 * Response Data (on success):
 * - Payments array with:
 *   - PaymentID
 *   - TransactionID
 *   - Amount
 *   - Currency
 *   - Status
 *   - CardNumber_Masked
 *   - NumPayments
 *   - CustomerID
 *   - OrderID
 *   - CreatedDate
 * - HasNextPage: Boolean indicating more results
 *
 * Pagination Example:
 * Page 1: StartIndex = 0, returns 50 items, HasNextPage = true
 * Page 2: StartIndex = 50, returns 50 items, HasNextPage = true
 * Page 3: StartIndex = 100, returns 20 items, HasNextPage = false
 *
 * Date Range Examples:
 * - Last month: Date_From = 30 days ago, Date_To = now
 * - Specific date: Date_From = date start, Date_To = date end
 * - All time: Date_From = company start date, Date_To = now
 *
 * IMPORTANT:
 * - Default date range is 1 year (if not specified)
 * - Results are paginated (typically 50 per page)
 * - Use HasNextPage to determine if more results exist
 * - Dates must be in ISO 8601 format
 * - Valid filter excludes declined/pending payments
 *
 * Error Handling:
 * - Invalid date format → "Invalid date format"
 * - Date_From > Date_To → "Invalid date range"
 * - No permissions → "Access denied"
 *
 * Best Practices:
 * - Use reasonable date ranges (avoid years of data)
 * - Implement pagination for large result sets
 * - Cache results if displaying frequently
 * - Filter by Valid for accounting reports
 * - Use specific date ranges for performance
 *
 * Integration:
 * - Used in payment history pages
 * - Used in accounting exports
 * - Used in reporting dashboards
 * - Used in customer transaction lists
 */
class ListPaymentsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new list payments request
     *
     * @param CredentialsData $credentials SUMIT API credentials
     * @param string|null $dateFrom Start date (ISO 8601), default: 1 year ago
     * @param string|null $dateTo End date (ISO 8601), default: now
     * @param bool|null $valid Filter valid payments only, null = all
     * @param int $startIndex Pagination offset, default: 0
     */
    public function __construct(
        protected readonly CredentialsData $credentials,
        protected readonly ?string $dateFrom = null,
        protected readonly ?string $dateTo = null,
        protected readonly ?bool $valid = null,
        protected readonly int $startIndex = 0,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/billing/payments/list/';
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
            'Date_From' => $this->dateFrom ?? Carbon::now()->subYear()->startOfDay()->toIso8601String(),
            'Date_To' => $this->dateTo ?? Carbon::now()->endOfDay()->toIso8601String(),
            'StartIndex' => $this->startIndex,
        ];

        // Only include Valid filter if explicitly set
        if ($this->valid !== null) {
            $body['Valid'] = $this->valid;
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
     *     "Payments": [
     *       {
     *         "PaymentID": 123456,
     *         "TransactionID": "txn_789",
     *         "Amount": 100.00,
     *         "Currency": "ILS",
     *         "Status": "Approved",
     *         "CardNumber_Masked": "****1234",
     *         "NumPayments": 1,
     *         "CustomerID": 456,
     *         "OrderID": "ORDER-123",
     *         "CreatedDate": "2025-01-16T10:30:00"
     *       }
     *     ],
     *     "HasNextPage": true
     *   }
     * }
     *
     * Empty list response:
     * {
     *   "Status": 0,
     *   "Data": {
     *     "Payments": [],
     *     "HasNextPage": false
     *   }
     * }
     *
     * Error response structure:
     * {
     *   "Status": 1,
     *   "UserErrorMessage": "Invalid date range"
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
     * @return bool True if payments were retrieved successfully
     */
    public function isSuccessful(Response $response): bool
    {
        $data = $response->json();
        return ($data['Status'] ?? 1) === 0;
    }

    /**
     * Get payments array from response
     *
     * @param Response $response
     * @return array<int, array<string, mixed>> Array of payment records
     */
    public function getPayments(Response $response): array
    {
        $data = $response->json();
        return $data['Data']['Payments'] ?? [];
    }

    /**
     * Check if there are more pages available
     *
     * @param Response $response
     * @return bool True if HasNextPage is true
     */
    public function hasNextPage(Response $response): bool
    {
        $data = $response->json();
        return $data['Data']['HasNextPage'] ?? false;
    }

    /**
     * Get the next page start index
     *
     * @param Response $response
     * @param int $pageSize Number of items per page (default: 50)
     * @return int|null Next StartIndex, or null if no more pages
     */
    public function getNextStartIndex(Response $response, int $pageSize = 50): ?int
    {
        if (! $this->hasNextPage($response)) {
            return null;
        }

        return $this->startIndex + $pageSize;
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

        return $data['UserErrorMessage'] ?? 'Failed to list payments';
    }

    /**
     * Configure request timeout
     *
     * List queries can be slower with large datasets
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
