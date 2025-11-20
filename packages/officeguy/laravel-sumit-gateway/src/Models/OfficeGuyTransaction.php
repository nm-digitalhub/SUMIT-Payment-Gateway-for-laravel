<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OfficeGuy Transaction Model
 *
 * Stores payment transaction details from SUMIT gateway
 */
class OfficeGuyTransaction extends Model
{
    use SoftDeletes;

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
        'payment_method',
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
    ];

    /**
     * Get the owning order model (polymorphic)
     */
    public function order(): MorphTo
    {
        return $this->morphTo('order', 'order_type', 'order_id');
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
            'currency' => $request['Items'][0]['Currency'] ?? config('app.currency', 'ILS'),
            'payments_count' => $request['Payments_Count'] ?? 1,
            'status' => ($response['Status'] === 0 && ($payment['ValidPayment'] ?? false)) ? 'completed' : 'failed',
            'payment_method' => 'card',
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
}
