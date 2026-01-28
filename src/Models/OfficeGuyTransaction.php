<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeGuyTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'officeguy_transactions';

    protected $fillable = [
        'order_id',
        'order_type',

        'payment_id',
        'document_id',

        'customer_id',              // legacy
        'client_id',                // canonical local client
        'sumit_customer_id_used',   // what SUMIT actually used

        'auth_number',
        'amount',
        'first_payment_amount',
        'non_first_payment_amount',
        'currency',
        'payments_count',

        'status',
        'transaction_type',         // charge | refund | void

        'parent_transaction_id',
        'refund_transaction_id',

        'payment_method',
        'payment_token',
        'last_digits',
        'expiration_month',
        'expiration_year',
        'card_type',

        'status_description',
        'error_message',

        'raw_request',
        'raw_response',

        'source',                   // checkout | webhook | api_polling
        'environment',
        'is_test',

        'completed_at',
        'notes',

        // Webhook confirmation fields (ADR-004)
        'sumit_entity_id',
        'is_webhook_confirmed',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'first_payment_amount' => 'decimal:2',
        'non_first_payment_amount' => 'decimal:2',
        'payments_count' => 'integer',

        'raw_request' => 'array',
        'raw_response' => 'array',

        'is_test' => 'boolean',
        'is_webhook_confirmed' => 'boolean',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'completed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     | Relationships
     |-----------------------------------------------------------------*/

    public function order(): MorphTo
    {
        return $this->morphTo('order', 'order_type', 'order_id');
    }

    /**
     * Get the customer relationship using dynamic model resolution.
     *
     * This method uses config('officeguy.models.customer') with 3-layer priority:
     * 1. Database: officeguy_settings.customer_model_class (Admin Panel editable)
     * 2. Config: officeguy.models.customer (new nested structure)
     * 3. Config: officeguy.customer_model_class (legacy flat structure)
     *
     * Fallback: If no customer model is configured, defaults to \App\Models\Client
     * for backward compatibility.
     */
    public function customer(): BelongsTo
    {
        $customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;

        return $this->belongsTo($customerModel, 'client_id');
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
     * - Replace $transaction->client with $transaction->customer
     * - Replace $transaction->client() with $transaction->customer()
     */
    public function client(): BelongsTo
    {
        return $this->customer();
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transaction_id');
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_transaction_id');
    }

    public function childRefunds(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transaction_id');
    }

    /**
     * Accessor: payable is an alias for order relationship
     *
     * Allows FulfillmentHandlers to use $transaction->payable
     * while the actual relationship is stored as order/order_type/order_id
     *
     * @return mixed
     */
    public function getPayableAttribute()
    {
        return $this->order;
    }

    /* -----------------------------------------------------------------
     | Helpers
     |-----------------------------------------------------------------*/

    public function isRefund(): bool
    {
        return $this->transaction_type === 'refund';
    }

    public function isCharge(): bool
    {
        return $this->transaction_type === 'charge';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isOrphan(): bool
    {
        return empty($this->order_id) || empty($this->order_type);
    }

    public function getPaymentToken(): ?string
    {
        return $this->payment_token
            ?? data_get($this->raw_response, 'Data.Payment.PaymentMethod.CreditCard_Token')
            ?? data_get($this->raw_response, 'sumit_payment_token');
    }

    public function addNote(string $note): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $existing = $this->notes ?? '';
        $this->notes = trim($existing . "\n[$timestamp] $note");
        $this->save();
    }

    /* -----------------------------------------------------------------
     | Factory
     |-----------------------------------------------------------------*/

    public static function createFromApiResponse(
        string | int $orderId,
        array $response,
        array $request = [],
        ?string $orderType = null
    ): static {
        $data = $response['Data'] ?? [];
        $payment = $data['Payment'] ?? [];
        $paymentMethod = $payment['PaymentMethod'] ?? [];

        /* ---------- SOURCE DETECTION ---------- */
        $source = $request['_source']
            ?? ($request['_webhook'] ?? false ? 'webhook' : 'api_polling');

        /* ---------- SYNTHETIC REQUEST (API POLLING) ---------- */
        if ($request === []) {
            $request = [
                '_synthetic' => true,
                '_source' => 'api_polling',
                'sumit_customer_id' => $data['CustomerID'] ?? null,
                'document_id' => $data['DocumentID'] ?? null,
                'payment_id' => $payment['ID'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ];
        }

        /* ---------- CURRENCY ---------- */
        $currencyMap = [0 => 'ILS', 1 => 'USD', 2 => 'EUR', 3 => 'GBP'];
        $currencyEnum = $payment['Currency'] ?? null;
        $currency = $currencyMap[$currencyEnum]
            ?? data_get($request, 'Items.0.Currency')
            ?? config('app.currency', 'ILS');

        /* ---------- CUSTOMER / CLIENT ---------- */
        $sumitCustomerIdUsed = $data['CustomerID'] ?? $payment['CustomerID'] ?? null;
        $clientId = null;

        $externalId = data_get($request, 'Customer.ExternalIdentifier');
        if ($externalId && is_numeric($externalId)) {
            $clientId = (int) $externalId;
        }

        if (! $clientId && $sumitCustomerIdUsed) {
            // Use dynamic customer model resolution with fallback to App\Models\Client
            $customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;
            $client = $customerModel::where('sumit_customer_id', $sumitCustomerIdUsed)->first();
            $clientId = $client?->id;
        }

        $tx = static::create([
            'order_id' => $orderId,
            'order_type' => $orderType,

            'payment_id' => $payment['ID'] ?? null,
            'document_id' => $data['DocumentID'] ?? null,

            'customer_id' => $sumitCustomerIdUsed,
            'client_id' => $clientId,
            'sumit_customer_id_used' => $sumitCustomerIdUsed,

            'auth_number' => $payment['AuthNumber'] ?? null,
            'amount' => $payment['Amount'] ?? 0,
            'first_payment_amount' => $payment['FirstPaymentAmount'] ?? null,
            'non_first_payment_amount' => $payment['NonFirstPaymentAmount'] ?? null,
            'currency' => $currency,
            'payments_count' => $request['Payments_Count'] ?? 1,

            'status' => ($response['Status'] === 0 && ($payment['ValidPayment'] ?? false))
                ? 'completed'
                : 'failed',

            'transaction_type' => 'charge',
            'payment_method' => 'card',

            'payment_token' => $paymentMethod['CreditCard_Token'] ?? null,
            'last_digits' => $paymentMethod['CreditCard_LastDigits'] ?? null,
            'expiration_month' => $paymentMethod['CreditCard_ExpirationMonth'] ?? null,
            'expiration_year' => $paymentMethod['CreditCard_ExpirationYear'] ?? null,
            'card_type' => $paymentMethod['Type'] ?? null,

            'status_description' => $payment['StatusDescription'] ?? null,
            'error_message' => $response['UserErrorMessage'] ?? null,

            'raw_request' => $request,
            'raw_response' => $response,

            'source' => $source,
            'environment' => config('officeguy.environment', 'www'),
            'is_test' => config('officeguy.testing', false),
        ]);

        if ($tx->isOrphan()) {
            $tx->addNote('⚠️ Transaction created without order context (API polling)');
        }

        return $tx;
    }
}
