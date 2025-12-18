<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

/**
 * Order Success Token Model
 *
 * Manages one-time cryptographic tokens for secure success page access.
 * Part of the 7-layer security architecture for post-payment redirects.
 *
 * Security Features:
 * - SHA256 hashed tokens (never stores plain text)
 * - Cryptographic nonce for replay protection
 * - Single use only (consumed_at tracking)
 * - TTL-based expiration (single source of truth)
 * - IP and User-Agent tracking for forensics
 *
 * @property int $id
 * @property int $payable_id
 * @property string $payable_type
 * @property string $token_hash SHA256 hash of the token
 * @property string $nonce Cryptographic nonce
 * @property Carbon $expires_at Token expiration timestamp
 * @property Carbon|null $consumed_at When token was consumed
 * @property string|null $consumed_by_ip IP that consumed the token
 * @property string|null $consumed_by_user_agent User agent that consumed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OrderSuccessToken extends Model
{
    protected $table = 'order_success_tokens';

    protected $fillable = [
        'payable_id',
        'payable_type',
        'token_hash',
        'nonce',
        'expires_at',
        'consumed_at',
        'consumed_by_ip',
        'consumed_by_user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Polymorphic relationship to any Payable entity
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if token is still valid
     *
     * A token is valid if:
     * - It has not been consumed yet
     * - It has not expired
     */
    public function isValid(): bool
    {
        return is_null($this->consumed_at)
            && $this->expires_at->isFuture();
    }

    /**
     * Check if token has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token has been consumed
     */
    public function isConsumed(): bool
    {
        return !is_null($this->consumed_at);
    }

    /**
     * Consume the token (mark as used)
     *
     * Once consumed, the token cannot be used again.
     * Records IP and User-Agent for security auditing.
     *
     * @param string $ip Client IP address
     * @param string $userAgent Client User-Agent
     */
    public function consume(string $ip, string $userAgent): void
    {
        $this->update([
            'consumed_at' => now(),
            'consumed_by_ip' => $ip,
            'consumed_by_user_agent' => $userAgent,
        ]);
    }

    /**
     * Find token by raw token string
     *
     * Hashes the token and searches for matching hash.
     * Only returns unconsumed tokens.
     *
     * @param string $rawToken The plain text token from URL
     * @return static|null
     */
    public static function findByToken(string $rawToken): ?static
    {
        $hash = hash('sha256', $rawToken);

        return static::where('token_hash', $hash)
            ->whereNull('consumed_at')
            ->first();
    }

    /**
     * Scope: Only valid tokens
     */
    public function scopeValid($query)
    {
        return $query->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope: Only expired tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: Only consumed tokens
     */
    public function scopeConsumed($query)
    {
        return $query->whereNotNull('consumed_at');
    }

    /**
     * Get time remaining until expiration
     *
     * @return int Seconds remaining (0 if expired)
     */
    public function getSecondsUntilExpiration(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return $this->expires_at->diffInSeconds(now());
    }

    /**
     * Get human-readable expiration status
     *
     * @return string
     */
    public function getExpirationStatus(): string
    {
        if ($this->isConsumed()) {
            return 'נוצל ב-' . $this->consumed_at->format('d/m/Y H:i');
        }

        if ($this->isExpired()) {
            return 'פג תוקף ב-' . $this->expires_at->format('d/m/Y H:i');
        }

        $remaining = $this->expires_at->diffForHumans();

        return "תקף עד {$remaining}";
    }
}
