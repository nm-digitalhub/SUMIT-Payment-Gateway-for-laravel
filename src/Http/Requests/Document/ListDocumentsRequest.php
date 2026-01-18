<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Document;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;

/**
 * List Documents Request
 *
 * Retrieves paginated list of documents with optional filtering.
 *
 * Use Cases:
 * - Display customer invoice history
 * - Export accounting records
 * - Search by date range
 * - Filter by document type
 * - Sync documents to local database
 *
 * Filters Available:
 * - Date range (from/to)
 * - Document type (1/2/3/320)
 * - Customer ID
 * - Payment status (is_closed)
 * - Order ID reference
 *
 * Pagination:
 * - Page: Current page number (1-based)
 * - PerPage: Items per page (default: 50, max: 100)
 * - Total: Total document count
 * - LastPage: Last available page
 *
 * Response Format:
 * {
 *   "Status": 0,
 *   "Data": {
 *     "Success": true,
 *     "Documents": [
 *       {
 *         "DocumentID": "123",
 *         "DocumentNumber": "2024-001",
 *         "DocumentType": 1,
 *         "Amount": 100.00,
 *         "IsClosed": true,
 *         "CreateDate": "2024-01-15",
 *         "CustomerName": "John Doe",
 *         ...
 *       }
 *     ],
 *     "Pagination": {
 *       "CurrentPage": 1,
 *       "PerPage": 50,
 *       "Total": 150,
 *       "LastPage": 3
 *     }
 *   }
 * }
 *
 * What You Get:
 * - Array of document summaries (NOT full details)
 * - Pagination metadata
 * - Total count for UI display
 * - Use GetDocumentDetailsRequest for full document
 *
 * IMPORTANT:
 * - Does NOT include Items array (use GetDocumentDetailsRequest)
 * - Sorted by creation date (newest first)
 * - Maximum 100 items per page
 * - Use pagination for large datasets
 */
class ListDocumentsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new list documents request
     *
     * @param CredentialsData $credentials SUMIT API credentials
     * @param int $page Current page number (1-based)
     * @param int $perPage Items per page (max: 100)
     * @param string|null $dateFrom Start date (YYYY-MM-DD)
     * @param string|null $dateTo End date (YYYY-MM-DD)
     * @param int|null $documentType Filter by document type (1/2/3/320)
     * @param string|null $customerId Filter by customer ID
     * @param bool|null $isClosed Filter by payment status (true=paid, false=unpaid)
     * @param string|null $orderId Filter by order reference
     */
    public function __construct(
        protected readonly CredentialsData $credentials,
        protected readonly int $page = 1,
        protected readonly int $perPage = 50,
        protected readonly ?string $dateFrom = null,
        protected readonly ?string $dateTo = null,
        protected readonly ?int $documentType = null,
        protected readonly ?string $customerId = null,
        protected readonly ?bool $isClosed = null,
        protected readonly ?string $orderId = null,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/documents/list/';
    }

    /**
     * Build request body with pagination and filters
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'Credentials' => $this->credentials->toArray(),
            'Page' => $this->page,
            'PerPage' => min($this->perPage, 100), // Cap at 100
        ];

        // Optional date range filter
        if ($this->dateFrom) {
            $body['DateFrom'] = $this->dateFrom;
        }
        if ($this->dateTo) {
            $body['DateTo'] = $this->dateTo;
        }

        // Optional document type filter
        if ($this->documentType !== null) {
            $body['DocumentType'] = $this->documentType;
        }

        // Optional customer filter
        if ($this->customerId) {
            $body['CustomerID'] = $this->customerId;
        }

        // Optional payment status filter
        if ($this->isClosed !== null) {
            $body['IsClosed'] = $this->isClosed;
        }

        // Optional order reference filter
        if ($this->orderId) {
            $body['OrderID'] = $this->orderId;
        }

        return $body;
    }

    /**
     * Cast response to array
     *
     * List response doesn't use DocumentResponse DTO (different structure)
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
     * List queries can take time with large datasets
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 90, // Longer timeout for large lists
        ];
    }
}
