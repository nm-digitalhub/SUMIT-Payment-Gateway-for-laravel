<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Support\OrderResolver;

/**
 * Bit Redirect Request Validation
 *
 * Validates user redirect from Bit payment page (success/cancel URLs).
 * This is DIFFERENT from BitWebhookRequest (which handles server-to-server IPN).
 *
 * ✅ FIX #13: Validates orderkey when user returns from Bit payment page
 * This prevents users from manually accessing success pages for orders they don't own.
 *
 * SECURITY: Unlike BitWebhookRequest, this DOES NOT return 200 on validation failure
 * (because this is a browser request, not a SUMIT webhook).
 *
 * Usage in application routes:
 * ```php
 * Route::get('/checkout/success', function (BitRedirectRequest $request) {
 *     $orderId = $request->getOrderId();
 *     $order = $request->getValidatedOrder();
 *     return view('checkout.success', compact('order'));
 * });
 * ```
 *
 * @property string $orderid Order ID from Bit redirect
 * @property string $orderkey Order security key for validation
 * @property string|null $status Optional payment status (success/failed/pending)
 */
class BitRedirectRequest extends FormRequest
{
    /**
     * Cached validated order instance
     *
     * @var mixed|null
     */
    protected mixed $validatedOrder = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by orderkey validation in rules.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization via orderkey validation
    }

    /**
     * Prepare the data for validation.
     *
     * Merges query parameters into request data for uniform access.
     * Bit sends redirect parameters as query params (GET request).
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Merge query parameters into request data
        $this->merge([
            'orderid' => $this->query('orderid') ?? $this->input('orderid'),
            'orderkey' => $this->query('orderkey') ?? $this->input('orderkey'),
            'status' => $this->query('status') ?? $this->input('status'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'orderid' => ['required', 'string', 'max:255'],
            'orderkey' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                // ✅ FIX #13: Validate orderkey matches order
                $orderId = $this->input('orderid');

                try {
                    $order = OrderResolver::resolve($orderId);

                    if (! $order) {
                        OfficeGuyApi::writeToLog(
                            "Bit redirect validation failed: Order {$orderId} not found",
                            'warning'
                        );

                        $fail('Order not found or invalid');

                        return;
                    }

                    // Get order key (supports both order_key column and getOrderKey() method)
                    $expectedOrderKey = null;
                    if (method_exists($order, 'getOrderKey')) {
                        $expectedOrderKey = $order->getOrderKey();
                    } elseif (isset($order->order_key)) {
                        $expectedOrderKey = $order->order_key;
                    }

                    if (! $expectedOrderKey) {
                        OfficeGuyApi::writeToLog(
                            "Bit redirect validation failed: Order {$orderId} has no order_key",
                            'error'
                        );

                        $fail('Order validation failed (missing security key)');

                        return;
                    }

                    // Validate orderkey matches
                    if ($value !== $expectedOrderKey) {
                        OfficeGuyApi::writeToLog(
                            "Bit redirect validation failed: Invalid order_key for order {$orderId}. ".
                            "Expected: {$expectedOrderKey}, Got: {$value}",
                            'error'
                        );

                        $fail('Invalid order security key');

                        return;
                    }

                    // Cache validated order for later use
                    $this->validatedOrder = $order;

                    OfficeGuyApi::writeToLog(
                        "Bit redirect validation successful for order {$orderId}",
                        'debug'
                    );
                } catch (\Exception $e) {
                    OfficeGuyApi::writeToLog(
                        "Bit redirect validation exception for order {$orderId}: {$e->getMessage()}",
                        'error'
                    );

                    $fail('Order validation failed');
                }
            }],
            'status' => ['nullable', 'string', 'in:success,failed,pending,cancelled'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orderid.required' => 'Missing order ID in redirect',
            'orderid.string' => 'Invalid order ID format',
            'orderid.max' => 'Order ID too long',

            'orderkey.required' => 'Missing order security key (orderkey parameter required)',
            'orderkey.string' => 'Invalid order key format',
            'orderkey.max' => 'Order key too long',

            'status.in' => 'Invalid payment status',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * Unlike BitWebhookRequest, this throws a regular ValidationException
     * because this is a browser request (not a SUMIT webhook).
     *
     * @param Validator $validator
     * @return void
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->all();
        $errorMessage = implode(', ', $errors);

        OfficeGuyApi::writeToLog(
            "Bit redirect validation failed: {$errorMessage}. ".
            'User will see error page. '.
            'Request data: '.json_encode($this->all()),
            'warning'
        );

        // Throw regular validation exception (will show error to user)
        throw new ValidationException($validator);
    }

    /**
     * Get validated order ID.
     *
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->validated()['orderid'];
    }

    /**
     * Get validated order key.
     *
     * @return string
     */
    public function getOrderKey(): string
    {
        return $this->validated()['orderkey'];
    }

    /**
     * Get payment status (if provided).
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->validated()['status'] ?? null;
    }

    /**
     * Get the validated order model.
     *
     * This returns the Order model that was validated during orderkey check.
     * Use this to avoid re-querying the database.
     *
     * @return mixed Order model instance (implements Payable)
     * @throws \RuntimeException If order was not validated (should never happen)
     */
    public function getValidatedOrder(): mixed
    {
        if ($this->validatedOrder === null) {
            // This should never happen if validation passed
            throw new \RuntimeException(
                'Order not validated. This is a bug in BitRedirectRequest.'
            );
        }

        return $this->validatedOrder;
    }

    /**
     * Check if payment was successful based on status parameter.
     *
     * Note: This is indicative only! Always verify payment status via webhook (IPN)
     * or by checking the Order model's payment_status field.
     *
     * @return bool
     */
    public function isSuccessStatus(): bool
    {
        return $this->getStatus() === 'success';
    }

    /**
     * Check if payment failed based on status parameter.
     *
     * Note: This is indicative only! Always verify payment status via webhook (IPN)
     * or by checking the Order model's payment_status field.
     *
     * @return bool
     */
    public function isFailedStatus(): bool
    {
        return in_array($this->getStatus(), ['failed', 'cancelled'], true);
    }
}
