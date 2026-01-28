<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OfficeGuy Document Model
 *
 * Stores invoice/receipt document information created via SUMIT
 */
class OfficeGuyDocument extends Model
{
    use SoftDeletes;

    protected $table = 'officeguy_documents';

    protected $fillable = [
        'document_id',
        'document_number',
        'document_date',
        'order_id',
        'order_type',
        'subscription_id',
        'customer_id',
        'document_type',
        'is_draft',
        'is_closed',
        'language',
        'currency',
        'amount',
        'description',
        'external_reference',
        'document_download_url',
        'document_payment_url',
        'items',
        'emailed',
        'raw_response',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'is_closed' => 'boolean',
        'emailed' => 'boolean',
        'amount' => 'decimal:2',
        'document_date' => 'datetime',
        'items' => 'array',
        'raw_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the owning order model (polymorphic)
     */
    public function order(): MorphTo
    {
        return $this->morphTo('order', 'order_type', 'order_id');
    }

    /**
     * Get the subscription this document belongs to (legacy single relationship)
     *
     * @deprecated Use subscriptions() for many-to-many relationship
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the customer relationship using dynamic model resolution.
     *
     * This method uses app('officeguy.customer_model') with 3-layer priority:
     * 1. Database: officeguy_settings.customer_model_class (Admin Panel editable)
     * 2. Config: officeguy.models.customer (new nested structure)
     * 3. Config: officeguy.customer_model_class (legacy flat structure)
     *
     * Fallback: If no customer model is configured, defaults to \App\Models\Client
     * for backward compatibility.
     *
     * The relationship matches SUMIT customer ID stored in the customer_id field
     * with the sumit_customer_id field in the customer model.
     */
    public function customer(): BelongsTo
    {
        $customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;

        return $this->belongsTo($customerModel, 'customer_id', 'sumit_customer_id');
    }

    /**
     * Legacy client relationship - DEPRECATED.
     *
     * @deprecated Use customer() instead. This method will be removed in v3.0.0.
     *
     * This method is preserved for backward compatibility but delegates to customer().
     * The relationship is identical - only the method name differs.
     *
     * Migration:
     * - Replace $document->client with $document->customer
     * - Replace $document->client() with $document->customer()
     */
    public function client(): BelongsTo
    {
        return $this->customer();
    }

    /**
     * Get all subscriptions associated with this document (many-to-many)
     *
     * A document can contain charges for multiple subscriptions.
     */
    public function subscriptions()
    {
        return $this->belongsToMany(
            Subscription::class,
            'document_subscription',
            'document_id',        // Foreign key on pivot table for this model
            'subscription_id'     // Foreign key on pivot table for the related model
        )
            ->withPivot('amount', 'item_data')
            ->withTimestamps();
    }

    /**
     * Create a document from SUMIT API response
     *
     * @param  array  $response  SUMIT API response
     * @param  array  $request  Original request data
     * @param  string  $orderType  Optional morph type
     */
    public static function createFromApiResponse(
        string | int $orderId,
        array $response,
        array $request = [],
        ?string $orderType = null
    ): static {
        $data = $response['Data'] ?? [];

        return static::create([
            'document_id' => $data['DocumentID'],
            'order_id' => $orderId,
            'order_type' => $orderType,
            'customer_id' => $data['CustomerID'] ?? null,
            'document_type' => $request['Details']['Type'] ?? $request['DocumentType'] ?? '1',
            'is_draft' => ($request['Details']['IsDraft'] ?? $request['DraftDocument'] ?? 'false') === 'true',
            'language' => $request['Details']['Language'] ?? $request['DocumentLanguage'] ?? null,
            'currency' => $request['Details']['Currency'] ?? $request['Items'][0]['Currency'] ?? config('app.currency', 'ILS'),
            'amount' => $request['amount'] ?? 0,
            'description' => $request['Details']['Description'] ?? $request['DocumentDescription'] ?? null,
            'emailed' => isset($request['Details']['SendByEmail']) || ($request['SendDocumentByEmail'] ?? 'false') === 'true',
            'raw_response' => $response,
        ]);
    }

    /**
     * Get document type name
     */
    public function getDocumentTypeName(): string
    {
        return match ($this->document_type) {
            '1' => __('Invoice'),
            '8' => __('Order'),
            'DonationReceipt' => __('Donation Receipt'),
            default => __('Document'),
        };
    }

    /**
     * Check if document is an invoice
     */
    public function isInvoice(): bool
    {
        return $this->document_type === '1';
    }

    /**
     * Check if document is an order
     */
    public function isOrder(): bool
    {
        return $this->document_type === '8';
    }

    /**
     * Check if document is a donation receipt
     */
    public function isDonationReceipt(): bool
    {
        return $this->document_type === 'DonationReceipt';
    }

    /**
     * Create document from SUMIT API List response
     *
     * @param  array  $doc  Document data from SUMIT /accounting/documents/list/ response
     * @param  int|null  $subscriptionId  Optional subscription ID to link
     */
    public static function createFromListResponse(
        array $doc,
        ?int $subscriptionId = null
    ): static {
        return static::create([
            'document_id' => $doc['DocumentID'] ?? null,
            'document_number' => $doc['DocumentNumber'] ?? null,
            'document_date' => $doc['Date'] ?? now(),
            'subscription_id' => $subscriptionId,
            'customer_id' => $doc['CustomerID'] ?? null,
            'document_type' => $doc['Type'] ?? '1',
            'is_draft' => $doc['IsDraft'] ?? false,
            'is_closed' => $doc['IsClosed'] ?? false,
            'language' => $doc['Language'] ?? 'he',
            'currency' => $doc['Currency'] ?? 'ILS',
            'amount' => $doc['DocumentValue'] ?? 0,
            'description' => $doc['Description'] ?? null,
            'external_reference' => $doc['ExternalReference'] ?? null,
            'document_download_url' => $doc['DocumentDownloadURL'] ?? null,
            'document_payment_url' => $doc['DocumentPaymentURL'] ?? null,
            'emailed' => false, // Not available in list response
            'raw_response' => $doc,
        ]);
    }
}
