<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Order Success Access Log Model
 *
 * Logs all access attempts to success pages for security auditing and analytics.
 * Part of the 7-layer security architecture for monitoring and forensics.
 *
 * Tracks:
 * - Valid and invalid access attempts
 * - IP addresses and User-Agents
 * - Validation failures (which layers failed)
 * - Token usage patterns
 * - URL signature validation results
 *
 * @property int $id
 * @property int $payable_id
 * @property string $payable_type
 * @property string $ip_address Client IP address
 * @property string|null $user_agent Client user agent
 * @property string|null $referer HTTP referer header
 * @property bool $is_valid Whether access was valid
 * @property array|null $validation_failures Failed validation layers
 * @property string|null $token_hash Token hash used
 * @property string|null $nonce Nonce used
 * @property bool $signature_valid URL signature validation result
 * @property Carbon $accessed_at When access occurred
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OrderSuccessAccessLog extends Model
{
    protected $table = 'order_success_access_log';

    protected $fillable = [
        'payable_id',
        'payable_type',
        'ip_address',
        'user_agent',
        'referer',
        'is_valid',
        'validation_failures',
        'token_hash',
        'nonce',
        'signature_valid',
        'accessed_at',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'signature_valid' => 'boolean',
        'validation_failures' => 'array',
        'accessed_at' => 'datetime',
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
     * Create log entry for successful access
     *
     * @param  object  $payable  The Payable entity (Order, Package, etc.)
     * @param  string  $tokenHash  SHA256 hash of the token used
     * @param  string  $nonce  Nonce used for access
     * @param  string  $ip  Client IP address
     * @param  string|null  $userAgent  Client User-Agent
     * @param  string|null  $referer  HTTP Referer
     * @param  bool  $signatureValid  Whether URL signature was valid
     */
    public static function logSuccessfulAccess(
        object $payable,
        string $tokenHash,
        string $nonce,
        string $ip,
        ?string $userAgent = null,
        ?string $referer = null,
        bool $signatureValid = true
    ): static {
        return static::create([
            'payable_id' => $payable->getKey(),
            'payable_type' => $payable::class,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'is_valid' => true,
            'validation_failures' => null,
            'token_hash' => $tokenHash,
            'nonce' => $nonce,
            'signature_valid' => $signatureValid,
            'accessed_at' => now(),
        ]);
    }

    /**
     * Create log entry for failed access
     *
     * @param  int|null  $payableId  The Payable ID (may be null if not found)
     * @param  string|null  $payableType  The Payable type (may be null)
     * @param  array  $failures  Array of failed validation layers
     * @param  string  $ip  Client IP address
     * @param  string|null  $userAgent  Client User-Agent
     * @param  string|null  $referer  HTTP Referer
     * @param  string|null  $tokenHash  Token hash if available
     * @param  string|null  $nonce  Nonce if available
     * @param  bool  $signatureValid  Whether URL signature was valid
     */
    public static function logFailedAccess(
        ?int $payableId,
        ?string $payableType,
        array $failures,
        string $ip,
        ?string $userAgent = null,
        ?string $referer = null,
        ?string $tokenHash = null,
        ?string $nonce = null,
        bool $signatureValid = false
    ): static {
        return static::create([
            'payable_id' => $payableId,
            'payable_type' => $payableType,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'is_valid' => false,
            'validation_failures' => $failures,
            'token_hash' => $tokenHash,
            'nonce' => $nonce,
            'signature_valid' => $signatureValid,
            'accessed_at' => now(),
        ]);
    }

    /**
     * Scope: Only valid accesses
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope: Only invalid accesses
     */
    public function scopeInvalid($query)
    {
        return $query->where('is_valid', false);
    }

    /**
     * Scope: By IP address
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope: Recent accesses
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('accessed_at', '>=', now()->subHours($hours));
    }

    /**
     * Get human-readable validation failures
     */
    public function getFailuresDescription(): string
    {
        if (! $this->validation_failures || empty($this->validation_failures)) {
            return 'אין כשלים';
        }

        $descriptions = [
            'signature' => 'חתימת URL לא תקינה',
            'token' => 'Token לא תקין',
            'nonce' => 'Nonce לא תקין',
            'expiration' => 'Token פג תוקף',
            'consumed' => 'Token כבר נוצל',
            'identity' => 'זהות לא מאומתת',
            'rate_limit' => 'חריגה ממגבלת קצב',
        ];

        return collect($this->validation_failures)
            ->map(fn ($layer) => $descriptions[$layer] ?? $layer)
            ->join(', ');
    }

    /**
     * Get browser name from User-Agent
     */
    public function getBrowserName(): string
    {
        if (! $this->user_agent) {
            return 'לא ידוע';
        }

        $ua = $this->user_agent;

        if (str_contains($ua, 'Chrome')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'Firefox')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'Safari') && ! str_contains($ua, 'Chrome')) {
            return 'Safari';
        }
        if (str_contains($ua, 'Edge')) {
            return 'Edge';
        }
        if (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident')) {
            return 'Internet Explorer';
        }

        return 'אחר';
    }

    /**
     * Check if access is from mobile device
     */
    public function isMobile(): bool
    {
        if (! $this->user_agent) {
            return false;
        }

        return preg_match('/Mobile|Android|iPhone|iPad/', $this->user_agent) === 1;
    }
}
