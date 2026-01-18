<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Responses;

use Saloon\Http\Response as SaloonResponse;

/**
 * Payment Response DTO
 *
 * Parsed SUMIT payment API response with convenience methods.
 *
 * SUMIT Response Structure:
 * - Status: 0 = API success, non-zero = API error
 * - Data.Success: true = transaction approved, false = declined
 * - UserErrorMessage: API-level error message
 * - Data.ResultDescription: Decline/error reason
 */
class PaymentResponse
{
    public function __construct(
        public readonly int $status, // API status: 0 = success
        public readonly bool $success, // Transaction approved
        public readonly ?string $transactionId = null,
        public readonly ?string $token = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $declineReason = null,
        public readonly array $data = [],
        public readonly array $rawResponse = [],
    ) {}

    /**
     * Create from Saloon response
     *
     * @param SaloonResponse $response
     * @return self
     */
    public static function fromSaloonResponse(SaloonResponse $response): self
    {
        $json = $response->json();

        $status = $json['Status'] ?? -1;
        $data = $json['Data'] ?? [];
        $dataSuccess = is_array($data) ? ($data['Success'] ?? false) : false;

        // Transaction is successful if:
        // 1. API call succeeded (Status === 0)
        // 2. Transaction was approved (Data.Success === true)
        $transactionSuccess = $status === 0 && $dataSuccess;

        return new self(
            status: $status,
            success: $transactionSuccess,
            transactionId: $data['TransactionID'] ?? $data['ConfirmationCode'] ?? null,
            token: $data['Token'] ?? null,
            errorMessage: $json['UserErrorMessage'] ?? null,
            declineReason: is_array($data) ? ($data['ResultDescription'] ?? null) : null,
            data: $data,
            rawResponse: $json,
        );
    }

    /**
     * Check if transaction was successful
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if API call failed (not declined)
     *
     * @return bool
     */
    public function isApiError(): bool
    {
        return $this->status !== 0;
    }

    /**
     * Check if transaction was declined by gateway
     *
     * @return bool
     */
    public function isDeclined(): bool
    {
        return $this->status === 0 && !$this->success;
    }

    /**
     * Get human-readable error/decline message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        // API error takes priority
        if ($this->errorMessage) {
            return $this->errorMessage;
        }

        // Then decline reason
        if ($this->declineReason) {
            return $this->declineReason;
        }

        return null;
    }

    /**
     * Get transaction details for logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'success' => $this->success,
            'transaction_id' => $this->transactionId,
            'token' => $this->token,
            'error_message' => $this->errorMessage,
            'decline_reason' => $this->declineReason,
            'data' => $this->data,
        ];
    }

    /**
     * Get specific data field
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if response has token
     *
     * @return bool
     */
    public function hasToken(): bool
    {
        return $this->token !== null;
    }

    /**
     * Check if response has transaction ID
     *
     * @return bool
     */
    public function hasTransactionId(): bool
    {
        return $this->transactionId !== null;
    }
}
