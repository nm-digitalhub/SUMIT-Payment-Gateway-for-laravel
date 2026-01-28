<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Responses;

use Saloon\Http\Response as SaloonResponse;

/**
 * Document Response DTO
 *
 * Parsed SUMIT document API response (invoices/receipts).
 *
 * SUMIT Document Response Structure:
 * - Status: 0 = API success
 * - Data.Success: true = document created
 * - Data.DocumentID: Generated document ID
 * - Data.DocumentNumber: Sequential document number
 * - Data.DocumentURL: Download URL
 * - UserErrorMessage: API-level error
 */
class DocumentResponse
{
    public function __construct(
        public readonly int $status, // API status: 0 = success
        public readonly bool $success, // Document created successfully
        public readonly ?string $documentId = null, // Internal document ID
        public readonly ?string $documentNumber = null, // Sequential number
        public readonly ?string $documentUrl = null, // Download URL
        public readonly ?string $errorMessage = null, // API error
        public readonly array $data = [], // Full Data object
        public readonly array $rawResponse = [], // Raw API response
    ) {}

    /**
     * Create from Saloon response
     */
    public static function fromSaloonResponse(SaloonResponse $response): self
    {
        $json = $response->json();

        $status = $json['Status'] ?? -1;
        $data = $json['Data'] ?? [];
        $dataSuccess = is_array($data) ? ($data['Success'] ?? false) : false;

        // Document creation is successful if:
        // 1. API call succeeded (Status === 0)
        // 2. Document was created (Data.Success === true)
        $documentCreated = $status === 0 && $dataSuccess;

        return new self(
            status: $status,
            success: $documentCreated,
            documentId: $data['DocumentID'] ?? null,
            documentNumber: $data['DocumentNumber'] ?? null,
            documentUrl: $data['DocumentURL'] ?? $data['DownloadURL'] ?? null,
            errorMessage: $json['UserErrorMessage'] ?? null,
            data: $data,
            rawResponse: $json,
        );
    }

    /**
     * Check if document was created successfully
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if API call failed
     */
    public function isApiError(): bool
    {
        return $this->status !== 0;
    }

    /**
     * Get error message
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get document details for logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'success' => $this->success,
            'document_id' => $this->documentId,
            'document_number' => $this->documentNumber,
            'document_url' => $this->documentUrl,
            'error_message' => $this->errorMessage,
            'data' => $this->data,
        ];
    }

    /**
     * Check if response has document ID
     */
    public function hasDocumentId(): bool
    {
        return $this->documentId !== null;
    }

    /**
     * Check if response has download URL
     */
    public function hasDownloadUrl(): bool
    {
        return $this->documentUrl !== null;
    }

    /**
     * Get specific data field
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
