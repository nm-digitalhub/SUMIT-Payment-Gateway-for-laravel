<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OfficeGuy Transaction Model
 *
 * Stores payment transaction details from SUMIT gateway
 */
class OfficeGuyTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'officeguy_transactions';

    protected $fillable = [
        'order_id',
        'order_type',
        'payment_id',
        'document_id',
        'customer_id',
        'auth_number',
        'amount',
        'first_payment_amount',
        'non_first_payment_amount',
        'currency',
        'payments_count',
        'status',
        'transaction_type',           // NEW: charge/refund/void
        'parent_transaction_id',      // NEW: Link to original charge (for refunds)
        'refund_transaction_id',      // NEW: Link to refund (for charges)
        'payment_method',
        'payment_token',              // NEW: CreditCard_Token from SUMIT
        'last_digits',
        'expiration_month',
        'expiration_year',
        'card_type',
        'status_description',
        'error_message',
        'raw_request',
        'raw_response',
        'environment',
        'is_test',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'first_payment_amount' => 'decimal:2',
        'non_first_payment_amount' => 'decimal:2',
        'payments_count' => 'integer',
        'raw_request' => 'array',
        'raw_response' => 'array',
        'is_test' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the owning order model (polymorphic)
     */
    public function order(): MorphTo
    {
        return $this->morphTo('order', 'order_type', 'order_id');
    }

    /**
     * Link to local Client if matched by SUMIT customer ID.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Client::class, 'customer_id', 'sumit_customer_id');
    }

    /**
     * Get the parent transaction (original charge) for refund transactions.
     *
     * @return BelongsTo
     */
    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyTransaction::class, 'parent_transaction_id');
    }

    /**
     * Get the refund transaction for charged transactions.
     *
     * @return BelongsTo
     */
    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(OfficeGuyTransaction::class, 'refund_transaction_id');
    }

    /**
     * Get all child refund transactions (if this charge was partially refunded multiple times).
     *
     * @return HasMany
     */
    public function childRefunds(): HasMany
    {
        return $this->hasMany(OfficeGuyTransaction::class, 'parent_transaction_id');
    }

    /**
     * Check if this is a refund transaction.
     *
     * @return bool
     */
    public function isRefund(): bool
    {
        return $this->transaction_type === 'refund';
    }

    /**
     * Check if this is a charge transaction.
     *
     * @return bool
     */
    public function isCharge(): bool
    {
        return $this->transaction_type === 'charge';
    }

    /**
     * Check if this transaction has been refunded.
     *
     * @return bool
     */
    public function hasBeenRefunded(): bool
    {
        return $this->refund_transaction_id !== null;
    }

    /**
     * Get payment token from raw_response or payment_token field.
     * Handles both standard SUMIT responses and custom implementations.
     *
     * @return string|null
     */
    public function getPaymentToken(): ?string
    {
        // Priority 1: Direct field
        if ($this->payment_token) {
            return $this->payment_token;
        }

        // Priority 2: Standard SUMIT response
        if (isset($this->raw_response['Data']['Payment']['PaymentMethod']['CreditCard_Token'])) {
            return $this->raw_response['Data']['Payment']['PaymentMethod']['CreditCard_Token'];
        }

        // Priority 3: Custom implementation (eSIM/etc)
        if (isset($this->raw_response['sumit_payment_token'])) {
            return $this->raw_response['sumit_payment_token'];
        }

        return null;
    }

    /**
     * Create a transaction from SUMIT API response
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
        $payment = $data['Payment'] ?? [];
        $paymentMethod = $payment['PaymentMethod'] ?? [];

        // Convert SUMIT currency enum to string (0=ILS, 1=USD, 2=EUR, etc.)
        $currencyEnum = $payment['Currency'] ?? null;
        $currencyMap = [0 => 'ILS', 1 => 'USD', 2 => 'EUR', 3 => 'GBP'];
        $currency = $currencyMap[$currencyEnum] ?? $request['Items'][0]['Currency'] ?? config('app.currency', 'ILS');

        return static::create([
            'order_id' => $orderId,
            'order_type' => $orderType,
            'payment_id' => $payment['ID'] ?? null,
            'document_id' => $data['DocumentID'] ?? null,
            'customer_id' => $data['CustomerID'] ?? $payment['CustomerID'] ?? null,
            'auth_number' => $payment['AuthNumber'] ?? null,
            'amount' => $payment['Amount'] ?? 0,
            'first_payment_amount' => $payment['FirstPaymentAmount'] ?? null,
            'non_first_payment_amount' => $payment['NonFirstPaymentAmount'] ?? null,
            'currency' => $currency,
            'payments_count' => $request['Payments_Count'] ?? 1,
            'status' => ($response['Status'] === 0 && ($payment['ValidPayment'] ?? false)) ? 'completed' : 'failed',
            'transaction_type' => 'charge',  // Default to charge (can be overridden)
            'payment_method' => 'card',
            'payment_token' => $paymentMethod['CreditCard_Token'] ?? null,  // NEW: Store token
            'last_digits' => $paymentMethod['CreditCard_LastDigits'] ?? null,
            'expiration_month' => $paymentMethod['CreditCard_ExpirationMonth'] ?? null,
            'expiration_year' => $paymentMethod['CreditCard_ExpirationYear'] ?? null,
            'card_type' => $paymentMethod['Type'] ?? null,
            'status_description' => $payment['StatusDescription'] ?? null,
            'error_message' => $response['UserErrorMessage'] ?? null,
            'raw_request' => $request,
            'raw_response' => $response,
            'environment' => config('officeguy.environment', 'www'),
            'is_test' => config('officeguy.testing', false),
        ]);
    }

    /**
     * Mark transaction as completed
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark transaction as failed
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark transaction as refunded
     */
    public function markAsRefunded(): void
    {
        $this->update(['status' => 'refunded']);
    }

    /**
     * Check if transaction is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Add a timestamped note to the transaction (similar to WooCommerce order notes).
     *
     * @param string $note Note content
     * @return void
     */
    public function addNote(string $note): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $existing = $this->notes ?? '';

        $this->notes = trim($existing . "\n[$timestamp] $note");
        $this->save();
    }

    /**
     * Get all notes as array.
     *
     * @return array<int, string>
     */
    public function getNotes(): array
    {
        if (empty($this->notes)) {
            return [];
        }

        return array_filter(explode("\n", $this->notes));
    }
}
