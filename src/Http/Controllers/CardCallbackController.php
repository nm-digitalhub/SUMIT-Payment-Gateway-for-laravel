<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

/**
 * Card Callback Controller
 *
 * Handles redirect callbacks from SUMIT after card payment processing
 * Port of: ProcessRedirectResponse() and ThankYou() logic
 */
class CardCallbackController extends Controller
{
    /**
     * Handle the payment callback
     *
     * Port of: ThankYou($OrderID) from OfficeGuyPayment.php
     * and ProcessRedirectResponse() from officeguy_woocommerce_gateway.php
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request)
    {
        $orderId = $request->query('OG-OrderID');
        $paymentId = $request->query('OG-PaymentID');
        $documentId = $request->query('OG-DocumentID');

        if (empty($orderId) || empty($paymentId)) {
            OfficeGuyApi::writeToLog('Card callback received without required parameters', 'error');
            return redirect()->route('checkout.failed')
                ->with('error', __('Invalid payment callback'));
        }

        OfficeGuyApi::writeToLog('Processing card callback for order #' . $orderId, 'debug');

        // Find the order transaction
        $transaction = OfficeGuyTransaction::where('order_id', $orderId)
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            // Create a new transaction if not found (redirect mode)
            $transaction = new OfficeGuyTransaction([
                'order_id' => $orderId,
                'payment_id' => $paymentId,
                'status' => 'pending',
                'payment_method' => 'card',
                'environment' => config('officeguy.environment', 'www'),
                'is_test' => config('officeguy.testing', false),
            ]);
        }

        // Get payment details from SUMIT
        $paymentRequest = [
            'Credentials' => PaymentService::getCredentials(),
            'PaymentID' => $paymentId,
        ];

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post($paymentRequest, '/billing/payments/get/', $environment, false);

        if ($response === null) {
            OfficeGuyApi::writeToLog('Failed to get payment details for payment #' . $paymentId, 'error');
            return redirect()->route('checkout.failed')
                ->with('error', __('Failed to verify payment'));
        }

        $payment = $response['Data']['Payment'] ?? null;

        if (!$payment || $payment['ValidPayment'] !== true) {
            // Payment failed
            $statusDescription = $payment['StatusDescription'] ?? 'Unknown error';

            $transaction->update([
                'status' => 'failed',
                'status_description' => $statusDescription,
                'error_message' => $statusDescription,
                'raw_response' => $response,
            ]);

            OfficeGuyApi::writeToLog(
                'Payment failed for order #' . $orderId . ': ' . $statusDescription,
                'error'
            );

            return redirect()->route('checkout.failed')
                ->with('error', __('Payment failed') . ' - ' . $statusDescription);
        }

        // Payment succeeded
        $paymentMethod = $payment['PaymentMethod'] ?? [];

        $transaction->update([
            'payment_id' => $payment['ID'],
            'document_id' => $documentId,
            'customer_id' => $payment['CustomerID'] ?? null,
            'auth_number' => $payment['AuthNumber'] ?? null,
            'amount' => $payment['Amount'] ?? 0,
            'first_payment_amount' => $payment['FirstPaymentAmount'] ?? null,
            'non_first_payment_amount' => $payment['NonFirstPaymentAmount'] ?? null,
            'status' => 'completed',
            'last_digits' => $paymentMethod['CreditCard_LastDigits'] ?? null,
            'expiration_month' => $paymentMethod['CreditCard_ExpirationMonth'] ?? null,
            'expiration_year' => $paymentMethod['CreditCard_ExpirationYear'] ?? null,
            'status_description' => $payment['StatusDescription'] ?? null,
            'raw_response' => $response,
        ]);

        OfficeGuyApi::writeToLog(
            'Payment completed for order #' . $orderId . 
            '. Auth: ' . ($payment['AuthNumber'] ?? 'N/A') .
            ', Last digits: ' . ($paymentMethod['CreditCard_LastDigits'] ?? 'N/A') .
            ', Payment ID: ' . $payment['ID'] .
            ', Document ID: ' . $documentId,
            'info'
        );

        // Create order document if configured
        if (config('officeguy.create_order_document', false)) {
            // You would need to load the actual order here
            // and call DocumentService::createOrderDocument()
            // This depends on your Order implementation
        }

        // Redirect to success page
        // You'll need to implement your own success route
        return redirect()->route('checkout.success', ['order' => $orderId])
            ->with('success', __('Payment completed successfully'));
    }
}
