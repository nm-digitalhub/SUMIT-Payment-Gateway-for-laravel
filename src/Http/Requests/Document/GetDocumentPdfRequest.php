<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Document;

use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\DocumentResponse;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * Get Document PDF Request
 *
 * Retrieves the PDF download URL for a generated document.
 *
 * Use Cases:
 * - Download invoice/receipt PDF for local storage
 * - Display PDF preview to customer
 * - Archive documents for compliance
 * - Attach PDF to custom email notifications
 *
 * What You Get:
 * - DocumentURL: Direct download link to PDF
 * - Valid for immediate download
 * - No expiration (stored in SUMIT for 7 years)
 *
 * IMPORTANT:
 * - Same URL as in create response
 * - Use this if you lost the original URL
 * - PDF is identical to what customer receives
 * - Direct download, no authentication needed for URL
 */
class GetDocumentPdfRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new get PDF request
     *
     * @param  string|int  $documentId  SUMIT document ID
     * @param  CredentialsData  $credentials  SUMIT API credentials
     */
    public function __construct(
        protected readonly string | int $documentId,
        protected readonly CredentialsData $credentials
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/documents/getpdf/';
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
     */
    public function createDtoFromResponse(Response $response): DocumentResponse
    {
        return DocumentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * PDF URL retrieval is fast
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
