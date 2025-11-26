<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * SumitWebhook Model
 *
 * Stores incoming webhooks/triggers received from SUMIT system.
 * These are webhooks that SUMIT sends to your application when cards
 * are created, updated, deleted, or archived in the SUMIT system.
 *
 * @see https://help.sumit.co.il/he/articles/11577644-שליחת-webhook-ממערכת-סאמיט
 */
class SumitWebhook extends Model
{
    protected $table = 'officeguy_sumit_webhooks';

    protected $fillable = [
        'webhook_id',
        'event_type',
        'card_type',
        'source_ip',
        'content_type',
        'headers',
        'payload',
        'card_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'amount',
        'currency',
        'status',
        'processing_notes',
        'error_message',
        'processed_at',
        'transaction_id',
        'document_id',
        'token_id',
        'subscription_id',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Event type constants - based on SUMIT trigger actions
     */
    const TYPE_CARD_CREATED = 'card_created';
    const TYPE_CARD_UPDATED = 'card_updated';
    const TYPE_CARD_DELETED = 'card_deleted';
    const TYPE_CARD_ARCHIVED = 'card_archived';

    /**
     * Card type constants - types of cards in SUMIT
     */
    const CARD_TYPE_CUSTOMER = 'customer';
    const CARD_TYPE_DOCUMENT = 'document';
    const CARD_TYPE_TRANSACTION = 'transaction';
    const CARD_TYPE_ITEM = 'item';
    const CARD_TYPE_PAYMENT = 'payment';

    /**
     * Status constants
     */
    const STATUS_RECEIVED = 'received';
    const STATUS_PROCESSED = 'processed';
    const STATUS_FAILED = 'failed';
    const STATUS_IGNORED = 'ignored';

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
     * Scope a query to only include webhooks of a given event type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope a query to only include webhooks of a given card type.
     */
    public function scopeOfCardType(Builder $query, string $cardType): Builder
    {
        return $query->where('card_type', $cardType);
    }

    /**
     * Scope a query to only include webhooks with a given status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include received webhooks.
     */
    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    /**
     * Scope a query to only include processed webhooks.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope a query to only include failed webhooks.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to filter by customer.
     */
    public function scopeForCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope a query to filter by card.
     */
    public function scopeForCard(Builder $query, string $cardId, string $cardType = null): Builder
    {
        $query->where('card_id', $cardId);
        
        if ($cardType) {
            $query->where('card_type', $cardType);
        }
        
        return $query;
    }

    /**
     * Scope a query to include unprocessed webhooks.
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    /**
     * Create a webhook from an incoming request.
     */
    public static function createFromRequest(
        string $eventType,
        array $payload,
        array $headers = [],
        ?string $sourceIp = null
    ): static {
        // Extract common fields from payload
        $cardId = $payload['ID'] ?? $payload['id'] ?? $payload['CardID'] ?? null;
        $customerId = $payload['CustomerID'] ?? $payload['customer_id'] ?? null;
        $customerEmail = $payload['Email'] ?? $payload['email'] ?? null;
        $customerName = $payload['Name'] ?? $payload['name'] ?? $payload['CustomerName'] ?? null;
        $amount = $payload['Amount'] ?? $payload['amount'] ?? $payload['Total'] ?? null;
        $currency = $payload['Currency'] ?? $payload['currency'] ?? null;
        $cardType = $payload['CardType'] ?? $payload['card_type'] ?? $payload['Type'] ?? null;
        
        return static::create([
            'event_type' => $eventType,
            'card_type' => $cardType,
            'source_ip' => $sourceIp,
            'content_type' => $headers['content-type'] ?? $headers['Content-Type'] ?? null,
            'headers' => $headers,
            'payload' => $payload,
            'card_id' => $cardId,
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'amount' => is_numeric($amount) ? (float) $amount : null,
            'currency' => $currency,
            'status' => self::STATUS_RECEIVED,
        ]);
    }

    /**
     * Mark the webhook as processed.
     */
    public function markAsProcessed(?string $notes = null, array $relations = []): void
    {
        $updateData = [
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'processing_notes' => $notes,
        ];
        
        if (!empty($relations['transaction_id'])) {
            $updateData['transaction_id'] = $relations['transaction_id'];
        }
        if (!empty($relations['document_id'])) {
            $updateData['document_id'] = $relations['document_id'];
        }
        if (!empty($relations['token_id'])) {
            $updateData['token_id'] = $relations['token_id'];
        }
        if (!empty($relations['subscription_id'])) {
            $updateData['subscription_id'] = $relations['subscription_id'];
        }
        
        $this->update($updateData);
    }

    /**
     * Mark the webhook as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark the webhook as ignored.
     */
    public function markAsIgnored(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_IGNORED,
            'processing_notes' => $reason ?? 'Webhook ignored',
            'processed_at' => now(),
        ]);
    }

    /**
     * Check if the webhook has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Check if the webhook is pending processing.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Get the event type label for display.
     */
    public function getEventTypeLabel(): string
    {
        return match ($this->event_type) {
            self::TYPE_CARD_CREATED => 'Card Created',
            self::TYPE_CARD_UPDATED => 'Card Updated',
            self::TYPE_CARD_DELETED => 'Card Deleted',
            self::TYPE_CARD_ARCHIVED => 'Card Archived',
            default => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Get the card type label for display.
     */
    public function getCardTypeLabel(): string
    {
        return match ($this->card_type) {
            self::CARD_TYPE_CUSTOMER => 'Customer',
            self::CARD_TYPE_DOCUMENT => 'Document',
            self::CARD_TYPE_TRANSACTION => 'Transaction',
            self::CARD_TYPE_ITEM => 'Item',
            self::CARD_TYPE_PAYMENT => 'Payment',
            default => ucfirst(str_replace('_', ' ', $this->card_type ?? 'Unknown')),
        };
    }

    /**
     * Get available event types.
     */
    public static function getEventTypes(): array
    {
        return [
            self::TYPE_CARD_CREATED => 'Card Created',
            self::TYPE_CARD_UPDATED => 'Card Updated',
            self::TYPE_CARD_DELETED => 'Card Deleted',
            self::TYPE_CARD_ARCHIVED => 'Card Archived',
        ];
    }

    /**
     * Get available card types.
     */
    public static function getCardTypes(): array
    {
        return [
            self::CARD_TYPE_CUSTOMER => 'Customer',
            self::CARD_TYPE_DOCUMENT => 'Document',
            self::CARD_TYPE_TRANSACTION => 'Transaction',
            self::CARD_TYPE_ITEM => 'Item',
            self::CARD_TYPE_PAYMENT => 'Payment',
        ];
    }

    /**
     * Get available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_PROCESSED => 'Processed',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_IGNORED => 'Ignored',
        ];
    }

    /**
     * Get a specific field from the payload.
     */
    public function getPayloadField(string $field, $default = null)
    {
        return data_get($this->payload, $field, $default);
    }
}
