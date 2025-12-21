<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CustomerData;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\PaymentPreferences;

/**
 * PendingCheckout Model
 *
 * Temporary storage for checkout data before payment confirmation.
 *
 * CRITICAL RULES:
 * - DB-first storage (not Session-based)
 * - Auto-expires after configurable time (default: 2 hours)
 * - Stores CheckoutIntent + ServiceData separately
 * - Cleaned up by scheduled job
 *
 * Use Cases:
 * - Pre-payment data collection
 * - Redirect flow (SUMIT redirect mode)
 * - Webhook recovery (if session is lost)
 * - Abandoned checkout analytics
 *
 * @property int $id
 * @property string $payable_type
 * @property int $payable_id
 * @property array $customer_data
 * @property array $payment_preferences
 * @property array|null $service_data
 * @property string|null $session_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.2.0
 */
class PendingCheckout extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pending_checkouts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'payable_type',
        'payable_id',
        'customer_data',
        'payment_preferences',
        'service_data',
        'session_id',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'customer_data' => 'array',
        'payment_preferences' => 'array',
        'service_data' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Convert to CheckoutIntent DTO (context only)
     *
     * ⚠️ CRITICAL: Intent does NOT include service_data!
     * - Intent = checkout context only (payable, customer, payment)
     * - Service data = stored separately, retrieved via getServiceData()
     *
     * This enforces the architectural boundary:
     * - CheckoutIntent = immutable context
     * - Service data = temporary storage
     *
     * @param Payable $payable The payable entity (must be loaded separately)
     * @return CheckoutIntent Context only (no service data)
     */
    public function toIntent(Payable $payable): CheckoutIntent
    {
        // ⚠️ Intentionally does NOT pass service_data to Intent
        return new CheckoutIntent(
            payable: $payable,
            customer: CustomerData::fromArray($this->customer_data),
            payment: PaymentPreferences::fromArray($this->payment_preferences),
        );
    }

    /**
     * Check if this pending checkout has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if this pending checkout is still valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Get service data as array
     *
     * @return array<string, mixed>
     */
    public function getServiceData(): array
    {
        return $this->service_data ?? [];
    }

    /**
     * Scope: Only active (not expired) checkouts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: Only expired checkouts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: For specific payable
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type Payable class name
     * @param int $id Payable ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPayable($query, string $type, int $id)
    {
        return $query->where('payable_type', $type)
                     ->where('payable_id', $id);
    }

    /**
     * Scope: For specific session
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sessionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }
}
