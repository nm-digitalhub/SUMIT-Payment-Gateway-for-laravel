<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;

/**
 * Bit Webhook Request Validation
 *
 * CRITICAL: This Form Request MUST return HTTP 200 even on validation failure
 * to prevent SUMIT from retrying the webhook (up to 5 times).
 *
 * Port of WooCommerce validation logic with improvements:
 * - Fix #10: Centralized validation with proper error handling
 * - Fix #2: Always returns 200 OK (implemented via failedValidation override)
 *
 * @property string $orderid Order ID from SUMIT webhook
 * @property string $orderkey Order security key for validation
 * @property string $documentid SUMIT document ID
 * @property string $customerid SUMIT customer ID
 */
class BitWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Webhooks don't require user authentication
        // Security is handled by order_key validation in the service layer
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * This merges query parameters into request data for uniform access.
     * SUMIT sends webhook parameters as query params (GET-style).
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Merge query parameters into request data
        $this->merge([
            'orderid' => $this->query('orderid') ?? $this->input('orderid'),
            'orderkey' => $this->query('orderkey') ?? $this->input('orderkey'),
            'documentid' => $this->query('documentid') ?? $this->input('documentid'),
            'customerid' => $this->query('customerid') ?? $this->input('customerid'),
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
            'orderkey' => ['required', 'string', 'max:255'],
            'documentid' => ['required', 'string', 'max:255'],
            'customerid' => ['required', 'string', 'max:255'],
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
            'orderid.required' => 'Missing order ID in webhook request',
            'orderid.string' => 'Invalid order ID format',
            'orderid.max' => 'Order ID too long',

            'orderkey.required' => 'Missing order key in webhook request (security parameter)',
            'orderkey.string' => 'Invalid order key format',
            'orderkey.max' => 'Order key too long',

            'documentid.required' => 'Missing SUMIT document ID in webhook request',
            'documentid.string' => 'Invalid document ID format',
            'documentid.max' => 'Document ID too long',

            'customerid.required' => 'Missing SUMIT customer ID in webhook request',
            'customerid.string' => 'Invalid customer ID format',
            'customerid.max' => 'Customer ID too long',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * ✅ FIX #2: CRITICAL - Always return HTTP 200 even on validation failure!
     *
     * This prevents SUMIT from retrying invalid webhook requests (up to 5 times).
     * WooCommerce pattern: Log the error but return success to prevent retry loops.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->all();
        $errorMessage = implode(', ', $errors);

        OfficeGuyApi::writeToLog(
            "Bit webhook validation failed: {$errorMessage}. ".
            'Returning 200 OK to prevent SUMIT retries. '.
            'Request data: '.json_encode($this->all()),
            'warning'
        );

        // ✅ Return HTTP 200 with error details (prevents SUMIT retry loop)
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Webhook validation failed',
                'errors' => $errors,
            ], 200) // ← CRITICAL: Must be 200, not 422!
        );
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
     * Get validated SUMIT document ID.
     *
     * @return string
     */
    public function getDocumentId(): string
    {
        return $this->validated()['documentid'];
    }

    /**
     * Get validated SUMIT customer ID.
     *
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->validated()['customerid'];
    }
}
