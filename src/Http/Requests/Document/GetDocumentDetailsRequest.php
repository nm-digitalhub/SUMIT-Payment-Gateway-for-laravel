<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Document;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\DocumentResponse;

/**
 * Get Document Details Request
 *
 * Retrieves complete document information including items, customer, and payment status.
 *
 * Use Cases:
 * - Fetch document items for subscription matching
 * - Verify document payment status (is_closed)
 * - Get complete customer information
 * - Prepare for document sync to local database
 *
 * What You Get:
 * - Full document metadata (number, type, date, etc.)
 * - Items array with product/service details
 * - Customer information
 * - Payment URLs (if unpaid)
 * - Download URLs
 * - IsClosed status (true = paid, false = unpaid)
 *
 * IMPORTANT:
 * - Use this for full document data (includes Items)
 * - Use ListDocumentsRequest for summary lists
 * - IsClosed = true means paid (no payment URL)
 * - IsClosed = false means unpaid (has payment URL)
 */
class GetDocumentDetailsRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new get document details request
     *
     * @param string|int $documentId SUMIT document ID
     * @param CredentialsData $credentials SUMIT API credentials
     */
    public function __construct(
        protected readonly string|int $documentId,
        protected readonly CredentialsData $credentials
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/documents/getdetails/';
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
            'DocumentID' => $this->documentId,
        ];
    }

    /**
     * Cast response to DocumentResponse DTO
     *
     * @param Response $response
     * @return DocumentResponse
     */
    public function createDtoFromResponse(Response $response): DocumentResponse
    {
        return DocumentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Details retrieval is usually fast
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
