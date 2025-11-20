<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OfficeGuy Token Model
 *
 * Stores tokenized credit card information for recurring payments
 */
class OfficeGuyToken extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'token',
        'gateway_id',
        'card_type',
        'last_four',
        'citizen_id',
        'expiry_month',
        'expiry_year',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the owning model (User, Customer, etc.)
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a token from SUMIT API response
     *
     * @param mixed $owner Owner model instance
     * @param array $response SUMIT API response
     * @param string $gatewayId Gateway identifier
     * @return static
     */
    public static function createFromApiResponse(
        mixed $owner,
        array $response,
        string $gatewayId = 'officeguy'
    ): static {
        $data = $response['Data'] ?? [];

        return static::create([
            'owner_type' => get_class($owner),
            'owner_id' => $owner->id,
            'token' => $data['CardToken'],
            'gateway_id' => $gatewayId,
            'card_type' => 'card', // Could be enhanced with brand detection
            'last_four' => substr($data['CardPattern'] ?? '', -4),
            'citizen_id' => $data['CitizenID'] ?? null,
            'expiry_month' => str_pad((string)($data['ExpirationMonth'] ?? '01'), 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string)($data['ExpirationYear'] ?? date('Y')),
            'is_default' => false,
            'metadata' => $data,
        ]);
    }

    /**
     * Create a token from payment method response
     *
     * @param mixed $owner Owner model instance
     * @param array $paymentMethod Payment method data from transaction
     * @param string $gatewayId Gateway identifier
     * @return static
     */
    public static function createFromPaymentMethod(
        mixed $owner,
        array $paymentMethod,
        string $gatewayId = 'officeguy'
    ): static {
        return static::create([
            'owner_type' => get_class($owner),
            'owner_id' => $owner->id,
            'token' => $paymentMethod['CreditCard_Token'],
            'gateway_id' => $gatewayId,
            'card_type' => 'card',
            'last_four' => $paymentMethod['CreditCard_LastDigits'] ?? '',
            'citizen_id' => $paymentMethod['CreditCard_CitizenID'] ?? null,
            'expiry_month' => str_pad((string)($paymentMethod['CreditCard_ExpirationMonth'] ?? '01'), 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string)($paymentMethod['CreditCard_ExpirationYear'] ?? date('Y')),
            'is_default' => false,
            'metadata' => $paymentMethod,
        ]);
    }

    /**
     * Set this token as the default for the owner
     */
    public function setAsDefault(): void
    {
        // Unset any other default tokens for this owner
        static::where('owner_type', $this->owner_type)
            ->where('owner_id', $this->owner_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get the default token for an owner
     *
     * @param mixed $owner Owner model instance
     * @param string $gatewayId Optional gateway filter
     * @return static|null
     */
    public static function getDefaultForOwner(mixed $owner, string $gatewayId = null): ?static
    {
        $query = static::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id)
            ->where('is_default', true);

        if ($gatewayId) {
            $query->where('gateway_id', $gatewayId);
        }

        return $query->first();
    }

    /**
     * Get all tokens for an owner
     *
     * @param mixed $owner Owner model instance
     * @param string $gatewayId Optional gateway filter
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getForOwner(mixed $owner, string $gatewayId = null)
    {
        $query = static::where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id);

        if ($gatewayId) {
            $query->where('gateway_id', $gatewayId);
        }

        return $query->get();
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        $expiryDate = \Carbon\Carbon::createFromDate(
            (int)$this->expiry_year,
            (int)$this->expiry_month,
            1
        )->endOfMonth();

        return $expiryDate->isPast();
    }

    /**
     * Get masked card number for display
     */
    public function getMaskedNumber(): string
    {
        return '**** **** **** ' . $this->last_four;
    }
}
