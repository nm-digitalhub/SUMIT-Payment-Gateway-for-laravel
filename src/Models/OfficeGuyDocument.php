<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
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
        'order_id',
        'order_type',
        'customer_id',
        'document_type',
        'is_draft',
        'language',
        'currency',
        'amount',
        'description',
        'emailed',
        'raw_response',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'emailed' => 'boolean',
        'amount' => 'decimal:2',
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
     * Create a document from SUMIT API response
     *
     * @param string|int $orderId
     * @param array $response SUMIT API response
     * @param array $request Original request data
     * @param string $orderType Optional morph type
     * @return static
     */
    public static function createFromApiResponse(
        string|int $orderId,
        array $response,
        array $request = [],
        string $orderType = null
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
}
