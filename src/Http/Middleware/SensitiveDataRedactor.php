<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Middleware;

use Saloon\Http\PendingRequest;

/**
 * Sensitive Data Redactor Middleware
 *
 * Redacts sensitive payment and credential data from request bodies
 * before they can be logged or exposed. Security-critical middleware.
 *
 * This middleware MUST run before LoggingMiddleware to prevent
 * sensitive data from appearing in logs.
 */
class SensitiveDataRedactor
{
    /**
     * Sensitive fields to redact
     *
     * @var array<string>
     */
    protected array $sensitiveFields = [
        'CardNumber',
        'CVV',
        'CreditCard_Number',
        'CreditCard_CVV',
        'APIKey',
        'ApiKey',
        'api_key',
        'CreditCard_Token', // Don't log tokens
        'SingleUseToken',
    ];

    /**
     * Nested paths that may contain sensitive data
     *
     * @var array<string>
     */
    protected array $nestedPaths = [
        'PaymentMethod',
        'Credentials',
    ];

    /**
     * Handle request body redaction
     *
     * @param PendingRequest $pendingRequest
     * @return void
     */
    public function __invoke(PendingRequest $pendingRequest): void
    {
        $body = $pendingRequest->body()->all();

        // Redact top-level sensitive fields
        foreach ($this->sensitiveFields as $field) {
            if (isset($body[$field])) {
                $body[$field] = '[REDACTED]';
            }
        }

        // Redact nested sensitive fields
        foreach ($this->nestedPaths as $path) {
            if (isset($body[$path]) && is_array($body[$path])) {
                foreach ($this->sensitiveFields as $field) {
                    if (isset($body[$path][$field])) {
                        $body[$path][$field] = '[REDACTED]';
                    }
                }
            }
        }

        // Replace the request body with redacted version
        $pendingRequest->body()->replace($body);
    }

    /**
     * Redact sensitive data from array (helper method)
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function redact(array $data): array
    {
        $instance = new self();

        // Redact top-level
        foreach ($instance->sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        // Redact nested
        foreach ($instance->nestedPaths as $path) {
            if (isset($data[$path]) && is_array($data[$path])) {
                foreach ($instance->sensitiveFields as $field) {
                    if (isset($data[$path][$field])) {
                        $data[$path][$field] = '[REDACTED]';
                    }
                }
            }
        }

        return $data;
    }
}
