<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Support\Facades\URL;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

/**
 * Secure Success URL Generator
 *
 * Generates cryptographically secure URLs for post-payment success pages.
 * Part of the 7-layer security architecture.
 *
 * Security Layers:
 * 1. Signed URL (Laravel's built-in HMAC signature)
 * 2. One-time token (SHA256 hashed, stored in DB)
 * 3. Cryptographic nonce (replay attack protection)
 * 4. TTL-based expiration (single source of truth in DB)
 *
 * Usage:
 * ```php
 * $generator = app(SecureSuccessUrlGenerator::class);
 * $url = $generator->generate($order);
 * ```
 */
class SecureSuccessUrlGenerator
{
    protected SettingsService $settings;

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Generate a secure success URL for a Payable entity
     *
     * Creates:
     * - Random token (64 bytes, hex-encoded)
     * - Cryptographic nonce (32 bytes, hex-encoded)
     * - Database record with SHA256 hash
     * - Laravel signed URL with token and nonce
     *
     * @param Payable $payable The payable entity (Order, Invoice, etc.)
     * @param int|null $ttlHours Token validity in hours (default: from SettingsService)
     * @return string The secure signed URL
     */
    public function generate(Payable $payable, ?int $ttlHours = null): string
    {
        // Check if enabled first
        if (!$this->isEnabled()) {
            return route('checkout.success');
        }

        // Generate cryptographic token and nonce
        $token = $this->generateToken();
        $nonce = $this->generateNonce();

        // Determine TTL with safe parsing
        $ttl = $ttlHours ?? $this->getDefaultTtl();

        // Create database record with hashed token
        $this->createTokenRecord($payable, $token, $nonce, $ttl);

        // Generate signed URL (Layer 1: Signed URL)
        return URL::temporarySignedRoute(
            'officeguy.success',
            now()->addHours($ttl),
            [
                'token' => $token, // Layer 2: One-time token
                'nonce' => $nonce, // Layer 3: Replay protection
            ]
        );
    }

    /**
     * Generate cryptographically secure random token
     *
     * Uses PHP's random_bytes() for CSPRNG.
     * Returns 128 character hex string (64 bytes).
     *
     * @return string 128-character hex token
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(64));
    }

    /**
     * Generate cryptographic nonce
     *
     * Returns 64 character hex string (32 bytes).
     *
     * @return string 64-character hex nonce
     */
    protected function generateNonce(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create token record in database
     *
     * Stores SHA256 hash of token (never plain text).
     * Sets expiration timestamp (single source of truth).
     *
     * @param Payable $payable The payable entity
     * @param string $token Plain text token (will be hashed)
     * @param string $nonce Cryptographic nonce
     * @param int $ttlHours Token validity in hours
     * @return OrderSuccessToken The created token record
     */
    protected function createTokenRecord(
        Payable $payable,
        string $token,
        string $nonce,
        int $ttlHours
    ): OrderSuccessToken {
        return OrderSuccessToken::create([
            'payable_id' => $payable->getPayableId(),
            'payable_type' => get_class($payable),
            'token_hash' => hash('sha256', $token), // SHA256 hash
            'nonce' => $nonce,
            'expires_at' => now()->addHours($ttlHours), // Single source of truth
        ]);
    }

    /**
     * Generate success URL for testing/development
     *
     * WARNING: This bypasses signature validation.
     * Only use in development/testing environments.
     *
     * @param Payable $payable The payable entity
     * @param int|null $ttlHours Token validity in hours
     * @return string Unsigned URL (for testing only)
     */
    public function generateUnsigned(Payable $payable, ?int $ttlHours = null): string
    {
        $token = $this->generateToken();
        $nonce = $this->generateNonce();
        $ttl = $ttlHours ?? $this->getDefaultTtl();

        $this->createTokenRecord($payable, $token, $nonce, $ttl);

        return route('officeguy.success', [
            'token' => $token,
            'nonce' => $nonce,
        ]);
    }

    /**
     * Regenerate URL for existing payable
     *
     * Invalidates previous tokens and creates a new one.
     * Useful for "resend confirmation" features.
     *
     * @param Payable $payable The payable entity
     * @param int|null $ttlHours Token validity in hours
     * @return string New secure signed URL
     */
    public function regenerate(Payable $payable, ?int $ttlHours = null): string
    {
        // Invalidate existing unconsumed tokens for this payable
        OrderSuccessToken::where('payable_id', $payable->getPayableId())
            ->where('payable_type', get_class($payable))
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'consumed_by_ip' => request()->ip() ?? '0.0.0.0',
                'consumed_by_user_agent' => 'System: Regenerated',
            ]);

        // Generate new URL
        return $this->generate($payable, $ttlHours);
    }

    /**
     * Get token TTL configuration
     *
     * Priority: DB → Config → Default
     *
     * @return int Hours
     */
    public function getDefaultTtl(): int
    {
        $value = $this->settings->get(
            'success_token_ttl',
            config('officeguy.success.token_ttl', 24) // Fallback to legacy config
        );

        return is_numeric($value) ? (int) $value : 24;
    }

    /**
     * Check if URL generation is enabled
     *
     * Priority: DB → Config → Default
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $value = $this->settings->get(
            'success_enabled',
            config('officeguy.success.enabled', true) // Fallback to legacy config
        );

        // Safe boolean conversion (handles "false", "0", "", etc.)
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
