<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Connectors;

use OfficeGuy\LaravelSumitGateway\Http\Middleware\LoggingMiddleware;
use OfficeGuy\LaravelSumitGateway\Http\Middleware\SensitiveDataRedactor;
use Saloon\Contracts\Authenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

/**
 * SUMIT API Connector
 *
 * Core HTTP client for all SUMIT API communication.
 * Replaces static OfficeGuyApi::post() with modern Saloon architecture.
 */
class SumitConnector extends Connector
{
    use AlwaysThrowOnErrors;

    /**
     * Retry configuration
     */
    public ?int $tries = 3;

    public ?bool $throwOnMaxTries = true;

    /**
     * Resolve base URL based on environment
     *
     * @return string Base API URL
     */
    public function resolveBaseUrl(): string
    {
        $env = config('officeguy.environment', 'www');

        // Dev environment uses HTTP (not HTTPS)
        if ($env === 'dev') {
            return "http://{$env}.api.sumit.co.il";
        }

        // Production/Test use HTTPS
        return 'https://api.sumit.co.il';
    }

    /**
     * Default headers for all requests
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Content-Language' => app()->getLocale(), // he/en/fr
            'User-Agent' => 'Laravel/12.0 SUMIT-Gateway/2.0-Saloon',
            'X-OG-Client' => 'Laravel-Saloon',
        ];
    }

    /**
     * Default configuration for Guzzle
     *
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 180, // 3 minutes for document generation
            'verify' => config('officeguy.ssl_verify', true), // SSL verification
        ];
    }

    /**
     * Boot middleware pipeline
     *
     * @param  PendingRequest  $pendingRequest  The pending request instance
     */
    public function boot(PendingRequest $pendingRequest): void
    {
        // Add logging middleware if enabled in config
        if (config('officeguy.logging', false)) {
            $this->middleware()->onRequest(
                new LoggingMiddleware
            );
        }

        // Always add sensitive data redaction (security)
        $this->middleware()->onRequest(
            new SensitiveDataRedactor
        );
    }

    /**
     * Default authentication
     *
     * SUMIT uses credentials in request body (not headers),
     * so we don't use header-based authentication.
     */
    protected function defaultAuth(): ?Authenticator
    {
        return null;
    }

    /**
     * Add client IP header conditionally
     *
     * @param  bool  $sendClientIp  Whether to send client IP
     * @return $this
     */
    public function withClientIp(bool $sendClientIp = true): static
    {
        if ($sendClientIp) {
            $this->headers()->add('X-OG-ClientIP', request()->ip());
        }

        return $this;
    }
}
