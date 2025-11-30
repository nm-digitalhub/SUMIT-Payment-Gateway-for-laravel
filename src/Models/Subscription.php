<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * Subscription Model
 *
 * Port of OfficeGuySubscriptions.php from WooCommerce plugin.
 * Manages recurring payment subscriptions.
 */
class Subscription extends Model implements Payable
{
    use SoftDeletes;

    protected $table = 'officeguy_subscriptions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'subscriber_type',
        'subscriber_id',
        'name',
        'amount',
        'currency',
        'interval_months',
        'total_cycles',
        'completed_cycles',
        'recurring_id',
        'status',
        'payment_method_token',
        'trial_ends_at',
        'next_charge_at',
        'last_charged_at',
        'cancelled_at',
        'expires_at',
        'cancellation_reason',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interval_months' => 'integer',
        'total_cycles' => 'integer',
        'completed_cycles' => 'integer',
        'metadata' => 'array',
        'trial_ends_at' => 'datetime',
        'next_charge_at' => 'datetime',
        'last_charged_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ========================================
    // Relationships
    // ========================================

    /**
     * Get the subscriber (User/Customer)
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all transactions for this subscription
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(OfficeGuyTransaction::class, 'subscription_id');
    }

    /**
     * Get the payment token
     */
    public function paymentToken()
    {
        if (!$this->payment_method_token) {
            return null;
        }
        return OfficeGuyToken::find($this->payment_method_token);
    }

    // ========================================
    // Payable Interface Implementation
    // ========================================

    public function getPayableId(): string|int
    {
        return 'subscription_' . $this->id;
    }

    public function getPayableAmount(): float
    {
        return (float) $this->amount;
    }

    public function getPayableCurrency(): string
    {
        return $this->currency;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->subscriber?->email ?? null;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->subscriber?->phone ?? null;
    }

    public function getCustomerName(): string
    {
        if ($this->subscriber) {
            return $this->subscriber->name ?? 
                   (($this->subscriber->first_name ?? '') . ' ' . ($this->subscriber->last_name ?? ''));
        }
        return __('Guest');
    }

    public function getCustomerAddress(): ?array
    {
        return null; // Subscriptions typically don't need addresses
    }

    public function getCustomerCompany(): ?string
    {
        return $this->subscriber?->company ?? null;
    }

    public function getCustomerId(): string|int|null
    {
        return $this->subscriber_id;
    }

    public function getLineItems(): array
    {
        return [
            [
                'name' => $this->name,
                'sku' => 'subscription_' . $this->id,
                'quantity' => 1,
                'unit_price' => (float) $this->amount,
                'product_id' => $this->id,
                'variation_id' => null,
            ],
        ];
    }

    public function getShippingAmount(): float
    {
        return 0;
    }

    public function getShippingMethod(): ?string
    {
        return null;
    }

    public function getFees(): array
    {
        return [];
    }

    public function getVatRate(): ?float
    {
        return null;
    }

    public function isTaxEnabled(): bool
    {
        return false;
    }

    public function getCustomerNote(): ?string
    {
        return __('Subscription payment') . ': ' . $this->name;
    }

    // ========================================
    // Status Methods
    // ========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isInTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasReachedLimit(): bool
    {
        if ($this->total_cycles === null) {
            return false;
        }
        return $this->completed_cycles >= $this->total_cycles;
    }

    public function canBeCharged(): bool
    {
        return $this->isActive() && 
               !$this->hasReachedLimit() && 
               !$this->isInTrial() &&
               $this->next_charge_at && 
               $this->next_charge_at->isPast();
    }

    // ========================================
    // Actions
    // ========================================

    public function activate(): void
    {
        $this->status = self::STATUS_ACTIVE;
        
        if (!$this->next_charge_at) {
            $this->next_charge_at = $this->trial_ends_at ?? now();
        }
        
        $this->save();
    }

    public function pause(): void
    {
        $this->status = self::STATUS_PAUSED;
        $this->save();
    }

    public function resume(): void
    {
        if ($this->status === self::STATUS_PAUSED) {
            $this->status = self::STATUS_ACTIVE;
            $this->save();
        }
    }

    public function cancel(?string $reason = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->save();
    }

    public function markAsFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
    }

    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->expires_at = now();
        $this->save();
    }

    /**
     * Record a successful charge
     */
    public function recordCharge(?string $recurringId = null): void
    {
        $this->completed_cycles++;
        $this->last_charged_at = now();
        $this->next_charge_at = now()->addMonths($this->interval_months);
        
        if ($recurringId) {
            $this->recurring_id = $recurringId;
        }

        // Check if subscription has reached its limit
        if ($this->hasReachedLimit()) {
            $this->markAsExpired();
        } else {
            $this->save();
        }
    }

    // ========================================
    // Scopes
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDue($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('total_cycles')
                  ->orWhereColumn('completed_cycles', '<', 'total_cycles');
            })
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '<=', now());
            })
            ->where('next_charge_at', '<=', now());
    }

    // ========================================
    // Helpers
    // ========================================

    /**
     * Get interval description string
     * Port of: GetMonthsString($Months) from OfficeGuySubscriptions.php
     */
    public function getIntervalDescription(): string
    {
        $months = $this->interval_months;

        if ($months === 1) {
            return __('Month');
        } elseif ($months === 2) {
            return __('2 months');
        } elseif ($months === 6) {
            return __('6 months');
        } elseif ($months % 12 === 0) {
            $years = $months / 12;
            if ($years === 1) {
                return __('Year');
            } elseif ($years === 2) {
                return __('2 Years');
            }
            return $years . ' ' . __('Years');
        }

        return $months . ' ' . __('months');
    }

    /**
     * Get formatted price with interval
     */
    public function getFormattedPrice(): string
    {
        $price = number_format((float) $this->amount, 2) . ' ' . $this->currency;
        $interval = ' / ' . $this->getIntervalDescription();
        
        if ($this->total_cycles) {
            $totalMonths = $this->interval_months * $this->total_cycles;
            $interval .= ' ' . __('for') . ' ' . $this->getMonthsString($totalMonths);
        }
        
        return $price . $interval;
    }

    /**
     * Get months description string
     */
    protected function getMonthsString(int $months): string
    {
        if ($months === 1) {
            return __('Month');
        } elseif ($months === 2) {
            return __('2 months');
        } elseif ($months === 6) {
            return __('6 months');
        } elseif ($months % 12 === 0) {
            $years = $months / 12;
            if ($years === 1) {
                return __('Year');
            } elseif ($years === 2) {
                return __('2 Years');
            }
            return $years . ' ' . __('Years');
        }

        return $months . ' ' . __('months');
    }

    // ========================================
    // Document Relationships
    // ========================================

    /**
     * Get all documents (invoices) for this subscription (legacy one-to-many)
     *
     * @deprecated Use documentsMany() for many-to-many relationship
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OfficeGuyDocument::class, 'subscription_id');
    }

    /**
     * Get all documents associated with this subscription (many-to-many)
     *
     * A subscription can appear in multiple consolidated documents.
     */
    public function documentsMany()
    {
        return $this->belongsToMany(
            OfficeGuyDocument::class,
            'document_subscription',
            'subscription_id',    // Foreign key on pivot table for this model
            'document_id'         // Foreign key on pivot table for the related model
        )
            ->withPivot('amount', 'item_data')
            ->withTimestamps();
    }

    /**
     * Get only invoices (type 1)
     */
    public function invoices(): HasMany
    {
        return $this->documents()->where('document_type', '1');
    }

    /**
     * Get only closed/paid documents
     */
    public function paidDocuments(): HasMany
    {
        return $this->documents()->where('is_closed', true);
    }

    /**
     * Get the latest invoice
     */
    public function latestInvoice(): ?OfficeGuyDocument
    {
        return $this->invoices()->latest('document_date')->first();
    }

    /**
     * Get total amount billed through documents
     */
    public function getTotalBilledAttribute(): float
    {
        return (float) $this->documents()->sum('amount');
    }

    /**
     * Get total amount paid (closed documents)
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->paidDocuments()->sum('amount');
    }

    /**
     * Get count of invoices
     */
    public function getInvoicesCountAttribute(): int
    {
        return $this->invoices()->count();
    }
}
