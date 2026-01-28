<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Responses;

use Saloon\Http\Response as SaloonResponse;

/**
 * Token Response DTO
 *
 * Parsed SUMIT token API response with convenience methods.
 * Used for token creation (single-use to permanent J2/J5).
 *
 * SUMIT Token Response Structure:
 * - Status: 0 = API success, non-zero = API error
 * - Data.Success: true = token created, false = declined
 * - Data.Token: Permanent token (J2/J5 format)
 * - UserErrorMessage: API-level error message
 * - Data.ResultDescription: Decline/error reason
 */
class TokenResponse
{
    public function __construct(
        public readonly int $status, // API status: 0 = success
        public readonly bool $success, // Token created successfully
        public readonly ?string $token = null, // Permanent token (J2/J5)
        public readonly ?string $transactionId = null, // Confirmation code
        public readonly ?string $errorMessage = null, // API error
        public readonly ?string $declineReason = null, // Decline reason
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

        // Token creation is successful if:
        // 1. API call succeeded (Status === 0)
        // 2. Token was created (Data.Success === true)
        // 3. Token is present in response
        $tokenCreated = $status === 0 && $dataSuccess && isset($data['Token']);

        return new self(
            status: $status,
            success: $tokenCreated,
            token: $data['Token'] ?? null,
            transactionId: $data['TransactionID'] ?? $data['ConfirmationCode'] ?? null,
            errorMessage: $json['UserErrorMessage'] ?? null,
            declineReason: is_array($data) ? ($data['ResultDescription'] ?? null) : null,
            data: $data,
            rawResponse: $json,
        );
    }

    /**
     * Check if token was created successfully
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if API call failed (not declined)
     */
    public function isApiError(): bool
    {
        return $this->status !== 0;
    }

    /**
     * Check if token creation was declined by gateway
     */
    public function isDeclined(): bool
    {
        return $this->status === 0 && ! $this->success;
    }

    /**
     * Get human-readable error/decline message
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
     * Get token details for logging
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'success' => $this->success,
            'token' => $this->token,
            'transaction_id' => $this->transactionId,
            'error_message' => $this->errorMessage,
            'decline_reason' => $this->declineReason,
            'data' => $this->data,
        ];
    }

    /**
     * Get specific data field
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if response has token
     */
    public function hasToken(): bool
    {
        return $this->token !== null;
    }

    /**
     * Check if response has transaction ID
     */
    public function hasTransactionId(): bool
    {
        return $this->transactionId !== null;
    }

    /**
     * Get token format (J2 or J5)
     * Determined by first character of token
     *
     * @return string|null 'J2' or 'J5' or null
     */
    public function getTokenFormat(): ?string
    {
        if (! $this->token) {
            return null;
        }

        // SUMIT tokens start with 'J2' or 'J5'
        if (str_starts_with($this->token, 'J2')) {
            return 'J2';
        }

        if (str_starts_with($this->token, 'J5')) {
            return 'J5';
        }

        return null;
    }
}
