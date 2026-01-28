<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\DTOs;

use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken;

/**
 * Validation Result DTO
 *
 * Data Transfer Object for success page access validation results.
 * Contains validation status, error messages, and validated entities.
 *
 * Usage:
 * ```php
 * $result = $validator->validate($request);
 *
 * if ($result->isValid()) {
 *     $order = $result->payable;
 *     $token = $result->token;
 * } else {
 *     return response($result->errorMessage, 403);
 * }
 * ```
 */
class ValidationResult
{
    /**
     * @param  bool  $isValid  Whether validation passed all layers
     * @param  OrderSuccessToken|null  $token  The validated token (if valid)
     * @param  object|null  $payable  The validated payable entity (if valid)
     * @param  string|null  $errorMessage  User-friendly error message (if invalid)
     * @param  array  $failures  Failed validation layers (if invalid)
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly ?OrderSuccessToken $token = null,
        public readonly ?object $payable = null,
        public readonly ?string $errorMessage = null,
        public readonly array $failures = []
    ) {}

    /**
     * Create successful validation result
     *
     * @param  OrderSuccessToken  $token  The validated token
     * @param  object  $payable  The validated payable entity
     */
    public static function success(OrderSuccessToken $token, object $payable): static
    {
        return new static(
            isValid: true,
            token: $token,
            payable: $payable,
            errorMessage: null,
            failures: []
        );
    }

    /**
     * Create failed validation result
     *
     * @param  string  $errorMessage  User-friendly error message
     * @param  array  $failures  Failed validation layers
     */
    public static function failed(string $errorMessage, array $failures = []): static
    {
        return new static(
            isValid: false,
            token: null,
            payable: null,
            errorMessage: $errorMessage,
            failures: $failures
        );
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * Check if validation failed
     */
    public function isFailed(): bool
    {
        return ! $this->isValid;
    }

    /**
     * Get the validated payable entity
     *
     * @throws \RuntimeException if validation failed
     */
    public function getPayable(): ?object
    {
        if ($this->isFailed()) {
            throw new \RuntimeException('Cannot get payable from failed validation result');
        }

        return $this->payable;
    }

    /**
     * Get the validated token
     *
     * @throws \RuntimeException if validation failed
     */
    public function getToken(): ?OrderSuccessToken
    {
        if ($this->isFailed()) {
            throw new \RuntimeException('Cannot get token from failed validation result');
        }

        return $this->token;
    }

    /**
     * Get error message
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get failed validation layers
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Check if specific layer failed
     *
     * @param  string  $layer  Layer name (e.g., 'signature', 'token', 'nonce')
     */
    public function hasFailure(string $layer): bool
    {
        return in_array($layer, $this->failures, true);
    }

    /**
     * Get failed layers as comma-separated string
     */
    public function getFailuresAsString(): string
    {
        return implode(', ', $this->failures);
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'error_message' => $this->errorMessage,
            'failures' => $this->failures,
            'payable_id' => $this->payable?->getKey(),
            'payable_type' => $this->payable ? $this->payable::class : null,
        ];
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
