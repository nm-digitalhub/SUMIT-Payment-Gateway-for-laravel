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
 * Send Document Email Request
 *
 * Sends document PDF by email to customer or custom address.
 *
 * Use Cases:
 * - Resend invoice to customer
 * - Forward document to accountant
 * - Send to alternative email address
 * - Automated document delivery after generation
 *
 * CRITICAL DIFFERENCE:
 * - Uses DocumentType + DocumentNumber (NOT DocumentID!)
 * - This is how SUMIT identifies documents for email sending
 * - DocumentType: 1=Invoice, 2=Receipt, 3=Credit, 320=Donation
 * - DocumentNumber: Sequential document number (e.g., "2024-001")
 *
 * What You Get:
 * - Confirmation of email sent
 * - No PDF returned (sent directly to email)
 * - Customer receives email with PDF attachment
 *
 * Email Content:
 * - Subject: "Document [Number] from [CompanyName]"
 * - Body: Optional personal message + standard footer
 * - Attachment: PDF document
 * - From: Company email from settings
 *
 * IMPORTANT:
 * - If email not provided, uses customer email from document
 * - Personal message is optional
 * - Email is sent immediately (not queued)
 * - Check spam folder if not received
 */
class SendDocumentEmailRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * Create new send email request
     *
     * @param int $documentType SUMIT document type (1/2/3/320)
     * @param string $documentNumber Sequential document number
     * @param CredentialsData $credentials SUMIT API credentials
     * @param string|null $email Optional recipient email (uses document customer if null)
     * @param string|null $personalMessage Optional personal message in email body
     */
    public function __construct(
        protected readonly int $documentType,
        protected readonly string $documentNumber,
        protected readonly CredentialsData $credentials,
        protected readonly ?string $email = null,
        protected readonly ?string $personalMessage = null,
    ) {}

    /**
     * Define the endpoint
     *
     * @return string
     */
    public function resolveEndpoint(): string
    {
        return '/accounting/documents/send/';
    }

    /**
     * Build request body
     *
     * CRITICAL: Uses DocumentType + DocumentNumber, NOT DocumentID!
     *
     * @return array<string, mixed>
     */
    protected function defaultBody(): array
    {
        $body = [
            'Credentials' => $this->credentials->toArray(),
            'DocumentType' => $this->documentType,
            'DocumentNumber' => $this->documentNumber,
        ];

        // Optional recipient email (uses document customer if omitted)
        if ($this->email) {
            $body['Email'] = $this->email;
        }

        // Optional personal message in email body
        if ($this->personalMessage) {
            $body['PersonalMessage'] = $this->personalMessage;
        }

        return $body;
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
     * Email sending can take time (SMTP, attachments)
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
