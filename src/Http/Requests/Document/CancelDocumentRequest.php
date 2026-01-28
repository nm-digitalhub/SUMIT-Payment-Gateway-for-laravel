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
 * Cancel Document Request
 *
 * Cancels/deletes a document by generating a credit note (Type 3).
 *
 * Use Cases:
 * - Cancel incorrect invoice
 * - Refund customer (creates credit note)
 * - Correct billing errors
 * - Comply with accounting regulations
 *
 * How It Works:
 * 1. SUMIT generates credit note (Type 3) for original document
 * 2. Credit note references original document
 * 3. Original document marked as cancelled
 * 4. Both documents kept for audit trail
 *
 * IMPORTANT Legal Notes:
 * - Documents CANNOT be deleted from SUMIT system
 * - Cancellation creates audit trail (required by tax law)
 * - Credit note has its own sequential number
 * - Both documents stored for 7 years (legal requirement)
 * - Customer receives credit note by email
 *
 * Response:
 * - DocumentID: Credit note ID (new document)
 * - DocumentNumber: Credit note number (new sequential)
 * - DocumentURL: Credit note PDF URL
 *
 * What You Get:
 * - Credit note details (new document)
 * - Original document stays in system (marked cancelled)
 * - Customer notification email sent automatically
 *
 * Use Cases by Document Type:
 * - Invoice (Type 1) → Credit Note (Type 3)
 * - Receipt (Type 2) → Credit Note (Type 3)
 * - Donation (Type 320) → Credit Note (Type 3)
 *
 * CRITICAL:
 * - Cannot cancel already cancelled documents
 * - Cannot cancel credit notes (Type 3)
 * - Must provide cancellation reason for audit
 */
class CancelDocumentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new cancel document request
     *
     * @param  string|int  $documentId  SUMIT document ID to cancel
     * @param  CredentialsData  $credentials  SUMIT API credentials
     * @param  string|null  $reason  Optional cancellation reason (recommended for audit)
     */
    public function __construct(
        protected readonly string | int $documentId,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $reason = null,
    ) {}

    /**
     * Define the endpoint
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/documents/cancel/';
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
            'DocumentID' => $this->documentId,
        ];

        // Optional cancellation reason (recommended for audit trail)
        if ($this->reason) {
            $body['Reason'] = $this->reason;
        }

        return $body;
    }

    /**
     * Cast response to DocumentResponse DTO
     *
     * Response contains CREDIT NOTE details (new document), not original
     */
    public function createDtoFromResponse(Response $response): DocumentResponse
    {
        return DocumentResponse::fromSaloonResponse($response);
    }

    /**
     * Configure request timeout
     *
     * Credit note generation can take time (PDF, sequential number, email)
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 120, // Longer timeout for credit note generation
        ];
    }
}
