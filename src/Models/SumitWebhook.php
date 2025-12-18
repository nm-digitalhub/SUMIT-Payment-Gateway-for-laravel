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
        'endpoint',
        'client_id',
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
        'endpoint',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'client_id');
    }

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
        ?string $sourceIp = null,
        ?string $endpoint = null
    ): static {
        // Extract common fields from payload (supports both flat and Properties structure)
        $cardId = $payload['ID'] ?? $payload['id'] ?? $payload['CardID'] ?? $payload['EntityID'] ?? null;
        $customerId = $payload['CustomerID'] ?? $payload['customer_id'] ?? null;
        $customerEmail = $payload['Email']
            ?? $payload['email']
            ?? $payload['Properties']['Customers_EmailAddress'][0]
            ?? null;
        $customerName = $payload['Name']
            ?? $payload['name']
            ?? $payload['CustomerName']
            ?? $payload['Properties']['Customers_FullName'][0]
            ?? null;
        $amount = $payload['Amount'] ?? $payload['amount'] ?? $payload['Total'] ?? null;
        $currency = $payload['Currency'] ?? $payload['currency'] ?? null;
        $cardType = $payload['CardType'] ?? $payload['card_type'] ?? $payload['Type'] ?? null;

        $clientId = static::matchClientIdFromPayload($payload);

        return static::create([
            'event_type' => $eventType,
            'card_type' => $cardType,
            'endpoint' => $endpoint ?? request()->path(),
            'source_ip' => $sourceIp,
            'content_type' => $headers['content-type'] ?? $headers['Content-Type'] ?? null,
            'headers' => $headers,
            'payload' => $payload,
            'client_id' => $clientId,
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
     * Try to match a local Client based on webhook payload.
     */
    protected static function matchClientIdFromPayload(array $payload): ?int
    {
        try {
            // Try to match by SUMIT customer ID
            $customerId = $payload['CustomerID'] ?? $payload['customer_id'] ?? $payload['ID'] ?? null;
            if ($customerId) {
                $client = \App\Models\Client::where('sumit_customer_id', $customerId)->first();
                if ($client) {
                    return $client->id;
                }
            }

            // Try to match by email (supports both flat and Properties structure)
            $email = $payload['Email']
                ?? $payload['email']
                ?? $payload['CustomerEmail']
                ?? $payload['CustomerEmailAddress']
                ?? $payload['Properties']['Customers_EmailAddress'][0]
                ?? null;
            if ($email) {
                $emailNorm = strtolower(trim($email));
                $client = \App\Models\Client::whereRaw('LOWER(email) = ?', [$emailNorm])
                    ->orWhereRaw('LOWER(client_email) = ?', [$emailNorm])
                    ->first();
                if ($client) {
                    return $client->id;
                }
            }

            // Try to match by VAT/Company number (supports both structures)
            $vat = $payload['Customers_CompanyNumber'][0]
                ?? $payload['CompanyNumber']
                ?? $payload['Properties']['Customers_CompanyNumber'][0]
                ?? null;
            if ($vat) {
                $client = \App\Models\Client::where('vat_number', $vat)->orWhere('id_number', $vat)->first();
                if ($client) {
                    return $client->id;
                }
            }

            // Try to match by phone (supports both structures)
            $phone = $payload['Customers_Phone'][0]
                ?? $payload['Phone']
                ?? $payload['Properties']['Customers_Phone'][0]
                ?? null;
            if ($phone) {
                $norm = preg_replace('/\\D+/', '', $phone);
                $client = \App\Models\Client::whereRaw('REPLACE(REPLACE(REPLACE(phone,\"-\",\"\"),\" \",\"\"),\"+\",\"\") = ?', [$norm])
                    ->orWhereRaw('REPLACE(REPLACE(REPLACE(client_phone,\"-\",\"\"),\" \",\"\"),\"+\",\"\") = ?', [$norm])
                    ->orWhereRaw('REPLACE(REPLACE(REPLACE(mobile_phone,\"-\",\"\"),\" \",\"\"),\"+\",\"\") = ?', [$norm])
                    ->first();
                if ($client) {
                    return $client->id;
                }
            }
        } catch (\Throwable $e) {
            // swallow matching errors
        }

        return null;
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

    /**
     * Helpers for CRM webhooks (payload may be keyed or positional array).
     */
    public function getCrmFolderId(): ?int
    {
        $payload = $this->payload;

        if (is_array($payload)) {
            // keyed variants
            $folder = $payload['FolderID'] ?? $payload['Folder'] ?? $payload['folder_id'] ?? $payload['folder'] ?? null;
            if (is_numeric($folder)) {
                return (int) $folder;
            }

            // positional: [FolderID, EntityID, Action, Properties]
            $values = array_values($payload);
            if (isset($values[0]) && is_numeric($values[0])) {
                return (int) $values[0];
            }
        }

        return null;
    }

    public function getCrmEntityId(): ?int
    {
        $payload = $this->payload;

        if (is_array($payload)) {
            $entity = $payload['EntityID'] ?? $payload['ID'] ?? $payload['entity_id'] ?? $payload['entity'] ?? null;
            if (is_numeric($entity)) {
                return (int) $entity;
            }

            $values = array_values($payload);
            if (isset($values[1]) && is_numeric($values[1])) {
                return (int) $values[1];
            }
        }

        return null;
    }

    public function getCrmAction(): ?string
    {
        $payload = $this->payload;

        if (is_array($payload)) {
            $action = $payload['Action'] ?? $payload['action'] ?? null;
            if (is_string($action)) {
                return $action;
            }

            $values = array_values($payload);
            if (isset($values[2]) && is_string($values[2])) {
                return $values[2];
            }
        }

        return null;
    }

    public function getCrmProperties(): ?array
    {
        $payload = $this->payload;

        if (is_array($payload)) {
            $props = $payload['Properties'] ?? $payload['properties'] ?? null;
            if (is_array($props)) {
                return $props;
            }

            $values = array_values($payload);
            if (isset($values[3]) && is_array($values[3])) {
                return $values[3];
            }
        }

        return null;
    }

    /**
     * Get known endpoints dynamically from registered routes and database records.
     *
     * This method automatically discovers all OfficeGuy webhook/callback routes
     * by scanning Laravel's route collection, so it stays up-to-date even when
     * new endpoints are added without requiring code changes.
     *
     * Sources (in priority order):
     * 1. Actual endpoints from database records
     * 2. Registered routes with 'officeguy.' prefix
     *
     * @return array<string,string>
     */
    public static function getKnownEndpoints(): array
    {
        try {
            // Get unique endpoints from existing webhook records
            $dbEndpoints = static::query()
                ->selectRaw('COALESCE(endpoint, event_type) as ep')
                ->distinct()
                ->pluck('ep')
                ->filter(fn ($ep) => is_string($ep) && trim($ep) !== '')
                ->all();
        } catch (\Throwable $e) {
            $dbEndpoints = [];
        }

        // Discover all registered OfficeGuy routes dynamically
        $routeEndpoints = [];
        try {
            $routes = \Illuminate\Support\Facades\Route::getRoutes();
            foreach ($routes as $route) {
                $routeName = $route->getName();

                // Filter only OfficeGuy webhook/callback routes
                if ($routeName && str_starts_with($routeName, 'officeguy.')) {
                    // Include webhook and callback routes only
                    if (str_contains($routeName, 'webhook') || str_contains($routeName, 'callback')) {
                        $uri = $route->uri();
                        if (!empty($uri)) {
                            $routeEndpoints[] = $uri;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Route discovery failed - continue with DB endpoints only
        }

        // Merge and deduplicate
        return collect($dbEndpoints)
            ->merge($routeEndpoints)
            ->filter(fn ($ep) => is_string($ep) && trim($ep) !== '')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $ep) => [$ep => $ep])
            ->toArray();
    }
}
