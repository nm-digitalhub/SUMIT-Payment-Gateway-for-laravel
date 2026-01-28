<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * WebhookEvent Model
 *
 * Stores webhook event logs and enables automation workflows.
 * Connected to transactions, documents, tokens, and subscriptions.
 */
class WebhookEvent extends Model
{
    protected $table = 'officeguy_webhook_events';

    protected $fillable = [
        'event_type',
        'status',
        'webhook_url',
        'http_status_code',
        'payload',
        'response',
        'error_message',
        'retry_count',
        'next_retry_at',
        'sent_at',
        'transaction_id',
        'document_id',
        'token_id',
        'subscription_id',
        'order_type',
        'order_id',
        'customer_email',
        'customer_id',
        'amount',
        'currency',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'http_status_code' => 'integer',
        'retry_count' => 'integer',
        'amount' => 'decimal:2',
        'next_retry_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Event type constants
     */
    const TYPE_PAYMENT_COMPLETED = 'payment_completed';

    const TYPE_PAYMENT_FAILED = 'payment_failed';

    const TYPE_DOCUMENT_CREATED = 'document_created';

    const TYPE_SUBSCRIPTION_CREATED = 'subscription_created';

    const TYPE_SUBSCRIPTION_CHARGED = 'subscription_charged';

    const TYPE_BIT_PAYMENT_COMPLETED = 'bit_payment_completed';

    const TYPE_STOCK_SYNCED = 'stock_synced';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_SENT = 'sent';

    const STATUS_FAILED = 'failed';

    const STATUS_RETRYING = 'retrying';

    /**
     * Get the related transaction.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyTransaction::class, 'transaction_id');
    }

    /**
     * Get the related document.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyDocument::class, 'document_id');
    }

    /**
     * Get the related token.
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyToken::class, 'token_id');
    }

    /**
     * Get the related subscription.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * Get the owning order model (polymorphic)
     */
    public function order(): MorphTo
    {
        return $this->morphTo('order', 'order_type', 'order_id');
    }

    /**
     * Scope a query to only include events of a given type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope a query to only include events with a given status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending events.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include sent events.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope a query to only include failed events.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to include events ready for retry.
     */
    public function scopeReadyForRetry(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RETRYING)
            ->where('next_retry_at', '<=', now())
            ->where('retry_count', '<', 5);
    }

    /**
     * Scope a query to filter by customer email.
     */
    public function scopeForCustomer(Builder $query, string $email): Builder
    {
        return $query->where('customer_email', $email);
    }

    /**
     * Create a new webhook event.
     */
    public static function createEvent(string $eventType, array $payload, array $options = []): static
    {
        return static::create([
            'event_type' => $eventType,
            'status' => self::STATUS_PENDING,
            'payload' => $payload,
            'webhook_url' => $options['webhook_url'] ?? null,
            'transaction_id' => $options['transaction_id'] ?? null,
            'document_id' => $options['document_id'] ?? null,
            'token_id' => $options['token_id'] ?? null,
            'subscription_id' => $options['subscription_id'] ?? null,
            'order_type' => $options['order_type'] ?? null,
            'order_id' => $options['order_id'] ?? null,
            'customer_email' => $payload['customer_email'] ?? $options['customer_email'] ?? null,
            'customer_id' => $payload['customer_id'] ?? $options['customer_id'] ?? null,
            'amount' => $payload['amount'] ?? $options['amount'] ?? null,
            'currency' => $payload['currency'] ?? $options['currency'] ?? null,
        ]);
    }

    /**
     * Mark the event as sent.
     */
    public function markAsSent(int $httpStatusCode = 200, ?array $response = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'http_status_code' => $httpStatusCode,
            'response' => $response,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark the event as failed.
     */
    public function markAsFailed(string $errorMessage, ?int $httpStatusCode = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'http_status_code' => $httpStatusCode,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Schedule the event for retry.
     */
    public function scheduleRetry(int $delayMinutes = 5): void
    {
        $this->update([
            'status' => self::STATUS_RETRYING,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => now()->addMinutes($delayMinutes * ($this->retry_count + 1)),
        ]);
    }

    /**
     * Check if the event can be retried.
     */
    public function canRetry(): bool
    {
        return $this->retry_count < 5 && in_array($this->status, [self::STATUS_FAILED, self::STATUS_RETRYING]);
    }

    /**
     * Check if the event was successfully sent.
     */
    public function wasSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Get the event type label for display.
     */
    public function getEventTypeLabel(): string
    {
        return match ($this->event_type) {
            self::TYPE_PAYMENT_COMPLETED => 'Payment Completed',
            self::TYPE_PAYMENT_FAILED => 'Payment Failed',
            self::TYPE_DOCUMENT_CREATED => 'Document Created',
            self::TYPE_SUBSCRIPTION_CREATED => 'Subscription Created',
            self::TYPE_SUBSCRIPTION_CHARGED => 'Subscription Charged',
            self::TYPE_BIT_PAYMENT_COMPLETED => 'Bit Payment Completed',
            self::TYPE_STOCK_SYNCED => 'Stock Synced',
            default => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Get available event types.
     */
    public static function getEventTypes(): array
    {
        return [
            self::TYPE_PAYMENT_COMPLETED => 'Payment Completed',
            self::TYPE_PAYMENT_FAILED => 'Payment Failed',
            self::TYPE_DOCUMENT_CREATED => 'Document Created',
            self::TYPE_SUBSCRIPTION_CREATED => 'Subscription Created',
            self::TYPE_SUBSCRIPTION_CHARGED => 'Subscription Charged',
            self::TYPE_BIT_PAYMENT_COMPLETED => 'Bit Payment Completed',
            self::TYPE_STOCK_SYNCED => 'Stock Synced',
        ];
    }

    /**
     * Get available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_RETRYING => 'Retrying',
        ];
    }
}
