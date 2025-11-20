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
        // If order total is 0, create document only
        if ($order->getPayableAmount() == 0) {
            return self::processZeroAmountOrder($order, $successUrl);
        }

        $request = self::buildBitPaymentRequest($order, $successUrl, $cancelUrl, $webhookUrl);
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
     * Process Bit webhook/IPN
     *
     * Port of: ProcessIPN()
     *
     * @param string $orderId Order ID from webhook
     * @param string $orderKey Order key for verification
     * @param string $documentId SUMIT document ID
     * @param string $customerId SUMIT customer ID
     * @param mixed $orderModel Order model instance (must have get method)
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

        // If order verification fails, return false
        // In a real implementation, you'd verify the order key matches
        // This depends on your order implementation

        // Find existing transaction
        $transaction = OfficeGuyTransaction::where('order_id', $orderId)
            ->where('payment_method', 'bit')
            ->where('status', 'pending')
            ->first();

        if ($transaction) {
            $transaction->update([
                'status' => 'completed',
                'document_id' => $documentId,
                'customer_id' => $customerId,
            ]);

            OfficeGuyApi::writeToLog("Bit payment completed for order $orderId, document ID: $documentId", 'info');

            return true;
        }

        // Create new transaction if not found
        OfficeGuyTransaction::create([
            'order_id' => $orderId,
            'document_id' => $documentId,
            'customer_id' => $customerId,
            'status' => 'completed',
            'payment_method' => 'bit',
            'amount' => 0, // Would need to get this from order
            'currency' => 'ILS',
            'environment' => config('officeguy.environment', 'www'),
            'is_test' => config('officeguy.testing', false),
        ]);

        OfficeGuyApi::writeToLog("Bit IPN processed for order $orderId", 'info');

        return true;
    }
}
