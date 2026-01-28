<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use OfficeGuy\LaravelSumitGateway\DTOs\ValidationResult;
use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessAccessLog;
use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken;

/**
 * Success Access Validator
 *
 * Implements 7-layer security architecture for validating success page access.
 * Each layer provides defense-in-depth protection against different attack vectors.
 *
 * Security Layers:
 * 1. Rate Limiting - Prevents brute force attacks
 * 2. Signed URL - Laravel HMAC signature validation
 * 3. Token Existence - Token must exist in database
 * 4. Token Validity - Token must not be expired
 * 5. Single Use - Token must not be consumed
 * 6. Nonce Matching - Cryptographic replay protection
 * 7. Identity Proof - Guest-safe cryptographic ownership (no auth required)
 *
 * All validation attempts are logged for security auditing.
 */
class SuccessAccessValidator
{
    /**
     * Validate access to success page
     *
     * Performs all 7 security layers validation.
     * Logs all access attempts (valid and invalid).
     * Returns ValidationResult DTO with detailed information.
     *
     * @param  Request  $request  The HTTP request
     * @return ValidationResult Validation result with token and payable
     */
    public function validate(Request $request): ValidationResult
    {
        $failures = [];
        $ip = $request->ip() ?? '0.0.0.0';
        $userAgent = $request->userAgent();
        $referer = $request->header('referer');

        // Layer 1: Rate Limiting
        if (! $this->validateRateLimit($request)) {
            $failures[] = 'rate_limit';
            $this->logFailedAccess(null, null, $failures, $ip, $userAgent, $referer);

            return ValidationResult::failed(
                'חריגה ממגבלת קצב. נא לנסות שנית בעוד מספר דקות.',
                $failures
            );
        }

        // Layer 2: Signed URL Validation
        if (! $request->hasValidSignature()) {
            $failures[] = 'signature';
            $this->logFailedAccess(null, null, $failures, $ip, $userAgent, $referer, signatureValid: false);

            return ValidationResult::failed(
                'קישור לא תקין. ייתכן שהקישור פג תוקף.',
                $failures
            );
        }

        // Extract parameters
        $rawToken = $request->query('token');
        $requestNonce = $request->query('nonce');

        if (! $rawToken || ! $requestNonce) {
            $failures[] = 'missing_parameters';
            $this->logFailedAccess(null, null, $failures, $ip, $userAgent, $referer, signatureValid: true);

            return ValidationResult::failed(
                'פרמטרים חסרים בקישור.',
                $failures
            );
        }

        // Layer 3: Token Existence
        $token = OrderSuccessToken::findByToken($rawToken);

        if (! $token instanceof \OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken) {
            $failures[] = 'token';
            $tokenHash = hash('sha256', $rawToken);
            $this->logFailedAccess(null, null, $failures, $ip, $userAgent, $referer, $tokenHash, $requestNonce, true);

            return ValidationResult::failed(
                'טוקן לא תקין או כבר נוצל.',
                $failures
            );
        }

        // Layer 4: Token Validity (Expiration)
        if ($token->isExpired()) {
            $failures[] = 'expiration';
            $this->logFailedAccess(
                $token->payable_id,
                $token->payable_type,
                $failures,
                $ip,
                $userAgent,
                $referer,
                $token->token_hash,
                $requestNonce,
                true
            );

            return ValidationResult::failed(
                'הקישור פג תוקף. נא לבקש קישור חדש.',
                $failures
            );
        }

        // Layer 5: Single Use (Consumption Check)
        if ($token->isConsumed()) {
            $failures[] = 'consumed';
            $this->logFailedAccess(
                $token->payable_id,
                $token->payable_type,
                $failures,
                $ip,
                $userAgent,
                $referer,
                $token->token_hash,
                $requestNonce,
                true
            );

            return ValidationResult::failed(
                'הקישור כבר נוצל. אין אפשרות להשתמש בו שוב.',
                $failures
            );
        }

        // Layer 6: Nonce Matching
        if ($token->nonce !== $requestNonce) {
            $failures[] = 'nonce';
            $this->logFailedAccess(
                $token->payable_id,
                $token->payable_type,
                $failures,
                $ip,
                $userAgent,
                $referer,
                $token->token_hash,
                $requestNonce,
                true
            );

            return ValidationResult::failed(
                'אימות נכשל. נא לבקש קישור חדש.',
                $failures
            );
        }

        // Load payable entity
        $payable = $token->payable;

        if (! $payable) {
            $failures[] = 'payable_not_found';
            $this->logFailedAccess(
                $token->payable_id,
                $token->payable_type,
                $failures,
                $ip,
                $userAgent,
                $referer,
                $token->token_hash,
                $requestNonce,
                true
            );

            return ValidationResult::failed(
                'הזמנה לא נמצאה.',
                $failures
            );
        }

        // Layer 7: Identity Proof (Guest-Safe)
        if (! $this->validateIdentity($payable, $request)) {
            $failures[] = 'identity';
            $this->logFailedAccess(
                $token->payable_id,
                $token->payable_type,
                $failures,
                $ip,
                $userAgent,
                $referer,
                $token->token_hash,
                $requestNonce,
                true
            );

            return ValidationResult::failed(
                'אין הרשאה לצפות בדף זה.',
                $failures
            );
        }

        // 🎉 ALL LAYERS PASSED - Success!

        // Log successful access
        $this->logSuccessfulAccess($payable, $token->token_hash, $requestNonce, $ip, $userAgent, $referer);

        // Consume the token (one-time use)
        $token->consume($ip, $userAgent ?? 'Unknown');

        return ValidationResult::success($token, $payable);
    }

    /**
     * Layer 1: Rate Limiting Validation
     *
     * Prevents brute force attacks by limiting requests per IP.
     * Uses Laravel's RateLimiter with sliding window.
     *
     * @return bool True if within rate limit
     */
    protected function validateRateLimit(Request $request): bool
    {
        $key = 'success-access:' . $request->ip();
        $maxAttempts = config('officeguy.success.rate_limit.max_attempts', 10);
        $decayMinutes = config('officeguy.success.rate_limit.decay_minutes', 1);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return true;
    }

    /**
     * Layer 7: Identity Proof Validation (Guest-Safe)
     *
     * Validates cryptographic ownership without requiring authentication.
     * Works for both authenticated users and guest checkout.
     *
     * For authenticated users: Checks if payable belongs to user
     * For guests: Cryptographic proof via Signed URL + Token + Nonce is sufficient
     *
     * @param  object  $payable  The Payable entity
     * @param  Request  $request  The HTTP request
     * @return bool True if identity is valid
     */
    protected function validateIdentity(object $payable, Request $request): bool
    {
        // If user is authenticated and payable has client_id, verify ownership
        if (auth()->check() && method_exists($payable, 'getClientId')) {
            $clientId = $payable->getClientId();

            if ($clientId && $clientId !== auth()->id()) {
                return false; // Payable belongs to different user
            }
        }

        // For guests OR authenticated users with matching client_id:
        // Identity is proven cryptographically via:
        // - Signed URL (Layer 2)
        // - One-time token (Layer 3)
        // - Nonce (Layer 6)
        // This is sufficient for guest-safe validation

        return true;
    }

    /**
     * Log successful access
     */
    protected function logSuccessfulAccess(
        object $payable,
        string $tokenHash,
        string $nonce,
        string $ip,
        ?string $userAgent,
        ?string $referer
    ): void {
        OrderSuccessAccessLog::logSuccessfulAccess(
            $payable,
            $tokenHash,
            $nonce,
            $ip,
            $userAgent,
            $referer
        );
    }

    /**
     * Log failed access
     */
    protected function logFailedAccess(
        ?int $payableId,
        ?string $payableType,
        array $failures,
        string $ip,
        ?string $userAgent,
        ?string $referer,
        ?string $tokenHash = null,
        ?string $nonce = null,
        bool $signatureValid = false
    ): void {
        OrderSuccessAccessLog::logFailedAccess(
            $payableId,
            $payableType,
            $failures,
            $ip,
            $userAgent,
            $referer,
            $tokenHash,
            $nonce,
            $signatureValid
        );
    }

    /**
     * Get remaining rate limit attempts
     */
    public function getRemainingAttempts(string $ip): int
    {
        $key = 'success-access:' . $ip;
        $maxAttempts = config('officeguy.success.rate_limit.max_attempts', 10);

        return RateLimiter::remaining($key, $maxAttempts);
    }

    /**
     * Clear rate limit for IP (admin use)
     */
    public function clearRateLimit(string $ip): void
    {
        $key = 'success-access:' . $ip;
        RateLimiter::clear($key);
    }
}
