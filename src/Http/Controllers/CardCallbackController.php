<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use OfficeGuy\LaravelSumitGateway\Support\OrderResolver;

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
     */
    public function handle(Request $request)
    {
        $orderId    = $request->query('OG-OrderID');
        $paymentId  = $request->query('OG-PaymentID');
        $documentId = $request->query('OG-DocumentID');

        if (empty($orderId) || empty($paymentId)) {
            OfficeGuyApi::writeToLog('Card callback received without required parameters', 'error');
            return $this->redirectFailed(__('Invalid payment callback'));
        }

        OfficeGuyApi::writeToLog('Processing card callback for order #' . $orderId, 'debug');

        $order = OrderResolver::resolve($orderId);

        // CRITICAL: Validate that order exists
        // Without this check, invalid order_id (e.g., 264, 408) can create Transactions
        // with no valid Order, causing SUMIT to create duplicate customers
        if (!$order) {
            OfficeGuyApi::writeToLog(
                'Card callback received for non-existent order #' . $orderId .
                '. Payment ID: ' . $paymentId .
                '. This indicates either:
1. Order was deleted after payment
2. Invalid order_id in callback URL
3. Callback URL was manually modified',
                'error'
            );

            return $this->redirectFailed(
                __('Order not found. Please contact support with payment ID: ') . $paymentId
            );
        }

        // Find existing transaction or create pending
        $transaction = OfficeGuyTransaction::where('order_id', $orderId)
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            $transaction = new OfficeGuyTransaction([
                'order_id'      => $orderId,
                'payment_id'    => $paymentId,
                'status'        => 'pending',
                'payment_method'=> 'card',
                'environment'   => config('officeguy.environment', 'www'),
                'is_test'       => config('officeguy.testing', false),
            ]);
        }

        // Get payment details from SUMIT
        $paymentRequest = [
            'Credentials' => PaymentService::getCredentials(),
            'PaymentID'   => $paymentId,
        ];

        $environment = config('officeguy.environment', 'www');
        $response    = OfficeGuyApi::post($paymentRequest, '/billing/payments/get/', $environment, false);

        if ($response === null) {
            OfficeGuyApi::writeToLog('Failed to get payment details for payment #' . $paymentId, 'error');
            return $this->redirectFailed(__('Failed to verify payment'));
        }

        $payment = $response['Data']['Payment'] ?? null;

        if (!$payment || ($payment['ValidPayment'] ?? false) !== true) {
            $statusDescription = $payment['StatusDescription'] ?? 'Unknown error';

            $transaction->status            = 'failed';
            $transaction->status_description = $statusDescription;
            $transaction->error_message     = $statusDescription;
            $transaction->raw_response      = $response;
            $transaction->save();

            OfficeGuyApi::writeToLog(
                'Payment failed for order #' . $orderId . ': ' . $statusDescription,
                'error'
            );

            return $this->redirectFailed(__('Payment failed') . ' - ' . $statusDescription);
        }

        $paymentMethod = $payment['PaymentMethod'] ?? [];

        $transaction->fill([
            'payment_id'             => $payment['ID'] ?? null,
            'document_id'            => $documentId,
            'customer_id'            => $payment['CustomerID'] ?? null,
            'auth_number'            => $payment['AuthNumber'] ?? null,
            'amount'                 => $payment['Amount'] ?? ($order ? $order->getPayableAmount() : 0),
            'first_payment_amount'   => $payment['FirstPaymentAmount'] ?? null,
            'non_first_payment_amount' => $payment['NonFirstPaymentAmount'] ?? null,
            'status'                 => 'completed',
            'is_webhook_confirmed'   => true,
            'webhook_confirmed_at'   => now(),
            'last_digits'            => $paymentMethod['CreditCard_LastDigits'] ?? null,
            'expiration_month'       => $paymentMethod['CreditCard_ExpirationMonth'] ?? null,
            'expiration_year'        => $paymentMethod['CreditCard_ExpirationYear'] ?? null,
            'status_description'     => $payment['StatusDescription'] ?? null,
            'raw_response'           => $response,
        ]);
        $transaction->save();

        // Save CustomerHistoryURL to Client model (if available)
        if ($order && method_exists($order, 'client')) {
            $client = $order->client;
            $customerHistoryUrl = $response['Data']['CustomerHistoryURL'] ?? null;

            if ($client && $customerHistoryUrl && empty($client->sumit_history_url)) {
                $client->sumit_history_url = $customerHistoryUrl;
                $client->save();

                OfficeGuyApi::writeToLog(
                    'Saved SUMIT history URL for client #' . $client->id,
                    'debug'
                );
            }
        }

        // Dispatch PaymentCompleted event (v2.0 with transaction and payable)
        event(new \OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted(
            $orderId,
            $payment,
            $response,
            $transaction,
            $order
        ));

        OfficeGuyApi::writeToLog(
            'Payment completed for order #' . $orderId .
            '. Auth: ' . ($payment['AuthNumber'] ?? 'N/A') .
            ', Last digits: ' . ($paymentMethod['CreditCard_LastDigits'] ?? 'N/A') .
            ', Payment ID: ' . $payment['ID'] .
            ', Document ID: ' . $documentId,
            'info'
        );

        // Create order document if configured and order is available
        if (config('officeguy.create_order_document', false) && $order) {
            $customer = PaymentService::getOrderCustomer($order);
            DocumentService::createOrderDocument($order, $customer, $documentId);
        }

        return $this->redirectSuccess($order, $orderId, __('Payment completed successfully'));
    }

    /**
     * Redirect to success page using secure URL generation
     *
     * @param object|null $order The Payable entity (Order, Invoice, etc.)
     * @param string|int $orderId Fallback order ID if order object is unavailable
     * @param string $message Success message
     * @return \Illuminate\Http\RedirectResponse
     */
    private function redirectSuccess($order, string|int $orderId, string $message)
    {
        // If order is a Payable entity and secure success is enabled, generate secure URL
        if ($order && $order instanceof \OfficeGuy\LaravelSumitGateway\Contracts\Payable) {
            $generator = app(SecureSuccessUrlGenerator::class);

            if ($generator->isEnabled()) {
                $secureUrl = $generator->generate($order);

                OfficeGuyApi::writeToLog(
                    'Redirecting to secure success page with token for order #' . $orderId,
                    'debug'
                );

                return redirect()->away($secureUrl);
            }
        }

        // Fallback: Legacy redirect (for non-Payable entities or if secure URL is disabled)
        OfficeGuyApi::writeToLog(
            'Using legacy success redirect for order #' . $orderId,
            'debug'
        );

        $route = config('officeguy.routes.success', 'checkout.success');

        if ($route && Route::getRoutes()->getByName($route)) {
            return redirect()->route($route, ['order' => $orderId])->with('success', $message);
        }

        return redirect()->to(url('/'))->with('success', $message);
    }

    private function redirectFailed(string $message)
    {
        $route = config('officeguy.routes.failed', 'checkout.failed');

        if ($route && Route::has($route)) {
            return redirect()->route($route)->with('error', $message);
        }

        return redirect()->to(url('/'))->with('error', $message);
    }
}
