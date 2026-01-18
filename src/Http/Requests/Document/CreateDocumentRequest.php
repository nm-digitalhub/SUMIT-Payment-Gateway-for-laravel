<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests\Document;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\DocumentData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Responses\DocumentResponse;

/**
 * Create Document Request
 *
 * Creates tax invoices, receipts, credit notes, or donation receipts.
 *
 * Document Types:
 * - Type 1: חשבונית מס (Tax Invoice)
 * - Type 2: קבלה (Receipt)
 * - Type 3: מסמך זיכוי (Credit Note)
 * - Type 320: קבלה על תרומה (Donation Receipt)
 *
 * Use Cases:
 * - Generate invoice after payment completed
 * - Issue receipt for cash/check payments
 * - Create credit note for refunds
 * - Provide donation receipts for tax deduction
 *
 * Document Generation Flow:
 * 1. Payment completed → TransactionID received
 * 2. CreateDocumentRequest with transaction details
 * 3. SUMIT generates PDF document
 * 4. DocumentResponse contains download URL
 * 5. Store DocumentID + URL in database
 * 6. Send document to customer via email/download
 *
 * IMPORTANT:
 * - Documents are legally binding tax documents
 * - Cannot be deleted, only cancelled with credit notes
 * - Sequential numbering is automatic (per year)
 * - PDF is stored in SUMIT system for 7 years
 * - Customer email = automatic PDF delivery
 *
 * Request Structure:
 * - Credentials: CompanyID + APIKey
 * - Type: Document type (1/2/3/320)
 * - Amount: Total amount (must match items sum)
 * - Customer: Name, email, address (required for invoices)
 * - Items: Product/service lines with VAT
 * - Optional: TransactionID, OrderID for references
 */
class CreateDocumentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new document request
     *
     * @param DocumentData $document Document details
     * @param CredentialsData $credentials SUMIT API credentials
     */
    public function __construct(
        protected readonly DocumentData $document,
        protected readonly CredentialsData $credentials
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/documents/create/';
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
            ...$this->document->toArray(),
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
     * Document generation can take up to 180 seconds
     * (PDF rendering, sequential number allocation, storage)
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 180,
        ];
    }
}
