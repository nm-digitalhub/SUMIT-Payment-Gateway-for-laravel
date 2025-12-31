<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Bit Payment Service
 *
 * 1:1 port of Bit payment logic from officeguybit_woocommerce_gateway.php
 * Handles Bit payment processing via SUMIT
 */
class BitPaymentService
{
    /**
     * Process Bit payment for an order
     *
     * Port of: ProcessBitOrder($Gateway, $Order)
     *
     * Implements fixes:
     * - Fix #6: IPN URL includes orderkey for security
     * - Fix #9: Enforce bit_enabled setting
     *
     * @param Payable $order Order instance
     * @param string $successUrl Success redirect URL
     * @param string $cancelUrl Cancel redirect URL
     * @param string $webhookUrl Webhook/IPN URL
     * @return array Result with 'success' boolean and 'redirect_url' or 'message'
     */
    public static function processOrder(
        Payable $order,
        string $successUrl,
        string $cancelUrl,
        string $webhookUrl
    ): array {
        // ✅ FIX #9: Check if Bit payments are enabled
        if (! config('officeguy.bit_enabled', false)) {
            OfficeGuyApi::writeToLog(
                'Bit payment attempt rejected: Bit payments are disabled via settings',
                'warning'
            );

            return [
                'success' => false,
                'message' => __('Bit payments are currently unavailable. Please choose another payment method.'),
            ];
        }

        // If order total is 0, create document only
        if ($order->getPayableAmount() == 0) {
            return self::processZeroAmountOrder($order, $successUrl);
        }

        // ✅ FIX #6: Build IPN URL with orderkey (security!)
        $orderId = $order->getPayableId();
        $orderKey = method_exists($order, 'getOrderKey') ? $order->getOrderKey() : null;

        // Log warning if orderkey missing (should not happen after migrations)
        if (! $orderKey) {
            OfficeGuyApi::writeToLog(
                "Warning: Order {$orderId} has no order_key - webhook validation will fail!",
                'warning'
            );
        }

        // Build complete IPN URL with security parameters
        $ipnUrl = $webhookUrl;
        if (strpos($ipnUrl, '?') === false) {
            $ipnUrl .= '?';
        } else {
            $ipnUrl .= '&';
        }
        $ipnUrl .= 'orderid='.urlencode((string) $orderId);
        if ($orderKey) {
            $ipnUrl .= '&orderkey='.urlencode($orderKey);
        }

        OfficeGuyApi::writeToLog(
            "Bit IPN URL built with security params for order #{$orderId}",
            'debug'
        );

        $request = self::buildBitPaymentRequest($order, $successUrl, $cancelUrl, $ipnUrl);
        $environment = config('officeguy.environment', 'www');

        OfficeGuyApi::writeToLog('Bit payment request for order #' . $order->getPayableId(), 'debug');

        $response = OfficeGuyApi::post(
            $request,
            '/billing/payments/beginredirect/',
            $environment,
            true
        );

        if ($response && $response['Status'] === 0 && isset($response['Data']['RedirectURL'])) {
            // Create pending transaction
            OfficeGuyTransaction::create([
                'order_id' => $order->getPayableId(),
                'order_type' => get_class($order),
                'amount' => $order->getPayableAmount(),
                'currency' => $order->getPayableCurrency(),
                'status' => 'pending',
                'payment_method' => 'bit',
                'raw_request' => $request,
                'raw_response' => $response,
                'environment' => $environment,
                'is_test' => config('officeguy.testing', false),
            ]);

            return [
                'success' => true,
                'redirect_url' => $response['Data']['RedirectURL'],
            ];
        }

        if ($response && $response['Status'] !== 0) {
            // API error
            OfficeGuyApi::writeToLog(
                'Bit payment failed for order #' . $order->getPayableId() . ': ' . ($response['UserErrorMessage'] ?? 'Unknown error'),
                'error'
            );

            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . ($response['UserErrorMessage'] ?? 'Unknown error'),
            ];
        }

        // Payment declined
        $statusDescription = $response['Data']['Payment']['StatusDescription'] ?? 'Unknown error';
        OfficeGuyApi::writeToLog(
            'Bit payment declined for order #' . $order->getPayableId() . ': ' . $statusDescription,
            'error'
        );

        return [
            'success' => false,
            'message' => __('Payment failed') . ' - ' . $statusDescription,
        ];
    }

    /**
     * Process zero amount order (create document only)
     *
     * @param Payable $order Order instance
     * @param string $successUrl Success redirect URL
     * @return array Result array
     */
    protected static function processZeroAmountOrder(Payable $order, string $successUrl): array
    {
        $customer = PaymentService::getOrderCustomer($order);
        $result = DocumentService::createOrderDocument($order, $customer, null);

        if ($result === null) {
            return [
                'success' => true,
                'redirect_url' => $successUrl,
            ];
        }

        return [
            'success' => false,
            'message' => __('Payment failed') . ' - ' . $result,
        ];
    }

    /**
     * Build Bit payment request
     *
     * @param Payable $order Order instance
     * @param string $successUrl Success redirect URL
     * @param string $cancelUrl Cancel redirect URL
     * @param string $webhookUrl Webhook/IPN URL
     * @return array Request data
     */
    protected static function buildBitPaymentRequest(
        Payable $order,
        string $successUrl,
        string $cancelUrl,
        string $webhookUrl
    ): array {
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'Items' => PaymentService::getPaymentOrderItems($order),
            'VATIncluded' => 'true',
            'VATRate' => PaymentService::getOrderVatRate($order),
            'Customer' => PaymentService::getOrderCustomer($order),
            'AuthoriseOnly' => config('officeguy.testing', false) ? 'true' : 'false',
            'DraftDocument' => config('officeguy.draft_document', false) ? 'true' : 'false',
            'SendDocumentByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
            'DocumentDescription' => __('Order number') . ': ' . $order->getPayableId() .
                (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote()),
            'Payments_Count' => 1,
            'MaximumPayments' => 1,
            'DocumentLanguage' => PaymentService::getOrderLanguage(),
            'MerchantNumber' => config('officeguy.merchant_number'),
            'RedirectURL' => $successUrl,
            'CancelRedirectURL' => $cancelUrl,
            'AutomaticallyRedirectToProviderPaymentPage' => 'UpayBit',
            'IPNURL' => $webhookUrl,
        ];

        // Allow filtering via events
        // do_action('og_payment_request_handle', $order, $request);

        return $request;
    }

    /**
     * Process Bit webhook/IPN with idempotency protection.
     *
     * Port of: ProcessIPN() from officeguybit_woocommerce_gateway.php
     *
     * CRITICAL: This method MUST be idempotent to handle SUMIT retries.
     * SUMIT retries up to 5 times if it doesn't receive 200 OK within 10 seconds.
     *
     * Implements fixes:
     * - Fix #1: Idempotency check (checks completed status too)
     * - Fix #3: Order key validation (security)
     * - Fix #7: Order status update (not just Transaction)
     * - Fix #8: Order-level idempotency (primary check)
     *
     * @param string $orderId Order ID from webhook
     * @param string $orderKey Order key for verification
     * @param string $documentId SUMIT document ID
     * @param string $customerId SUMIT customer ID
     * @param mixed $orderModel Order model instance (must implement Payable)
     * @return bool Success status
     */
    public static function processWebhook(
        string $orderId,
        string $orderKey,
        string $documentId,
        string $customerId,
        mixed $orderModel = null
    ): bool {
        OfficeGuyApi::writeToLog("Processing Bit IPN for order $orderId", 'debug');

        // ✅ FIX #3 & #8: Validate order key and check Order-level idempotency
        if ($orderModel) {
            // Security check: Validate order key
            $actualOrderKey = method_exists($orderModel, 'getOrderKey')
                ? $orderModel->getOrderKey()
                : null;

            if ($actualOrderKey && $actualOrderKey !== $orderKey) {
                OfficeGuyApi::writeToLog(
                    "Bit IPN rejected: Invalid order key for order $orderId",
                    'error'
                );

                return false;
            }

            // ✅ FIX #8: Order-level idempotency check (like WooCommerce)
            // Check if Order is already paid (primary idempotency check)
            $orderPaymentStatus = null;
            if (method_exists($orderModel, 'payment_status')) {
                $orderPaymentStatus = $orderModel->payment_status;
            } elseif (isset($orderModel->payment_status)) {
                $orderPaymentStatus = $orderModel->payment_status;
            }

            // If Order already paid, don't process again
            if (in_array($orderPaymentStatus, ['completed', 'paid', 'processing'])) {
                OfficeGuyApi::writeToLog(
                    "Bit IPN ignored: Order $orderId already paid (status: $orderPaymentStatus) - idempotency check",
                    'debug'
                );

                return true; // Return true to prevent SUMIT retries
            }
        }

        // ✅ FIX #1: Transaction-level idempotency (secondary protection)
        // Find ANY transaction (not just pending)
        $transaction = OfficeGuyTransaction::where('order_id', $orderId)
            ->where('payment_method', 'bit')
            ->first();

        if ($transaction) {
            // If transaction already completed, this is a retry
            if ($transaction->status === 'completed') {
                OfficeGuyApi::writeToLog(
                    "Bit IPN ignored: Transaction #{$transaction->id} already completed - idempotency check",
                    'debug'
                );

                return true; // Already processed, idempotent
            }

            // Transaction exists but NOT completed → Update it
            if (in_array($transaction->status, ['pending', 'processing'])) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $documentId, $customerId, $orderModel, $orderId) {
                    // Update Transaction
                    $transaction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'document_id' => $documentId,
                        'customer_id' => $customerId,
                    ]);

                    // ✅ FIX #7: Update Order status (not just Transaction!)
                    if ($orderModel) {
                        // Check if Order has markAsPaid method (recommended pattern)
                        if (method_exists($orderModel, 'markAsPaid')) {
                            $orderModel->markAsPaid('bit');
                        }
                        // Or direct update using Eloquent
                        elseif ($orderModel instanceof \Illuminate\Database\Eloquent\Model) {
                            $updateData = ['paid_at' => now()];

                            // Update payment_status if field exists
                            if (isset($orderModel->payment_status)) {
                                $updateData['payment_status'] = 'paid';
                            }

                            // Update status if using OrderStatus enum
                            if (isset($orderModel->status)) {
                                $updateData['status'] = 'processing';
                            }

                            $orderModel->update($updateData);
                        }

                        // Add note if supported
                        if (method_exists($orderModel, 'addNote')) {
                            $orderModel->addNote(
                                "Bit payment completed successfully.\n".
                                "Document ID: {$documentId}\n".
                                "Customer ID: {$customerId}"
                            );
                        }
                    }

                    // Add note to transaction
                    $transaction->addNote("Bit payment completed. Document ID: $documentId, Customer ID: $customerId");

                    // Dispatch event
                    event(new \OfficeGuy\LaravelSumitGateway\Events\BitPaymentCompleted(
                        $orderId,
                        $documentId,
                        $customerId
                    ));
                });

                OfficeGuyApi::writeToLog(
                    "Bit webhook processed successfully: Transaction #{$transaction->id} and Order #{$orderId} marked as completed",
                    'info'
                );

                return true;
            }
        }

        // ❌ No transaction found → This should NOT happen!
        // Transaction should have been created by processOrder() before redirect
        OfficeGuyApi::writeToLog(
            "Bit IPN error: No transaction found for order $orderId (payment_method=bit). ".
            'This should not happen - transaction should be created before redirecting to Bit.',
            'error'
        );

        // Return false to indicate logical error (NOT a retry-able server error)
        return false;
    }
}
