<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use OfficeGuy\LaravelSumitGateway\Http\Requests\BitWebhookRequest;
use OfficeGuy\LaravelSumitGateway\Services\BitPaymentService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Support\OrderResolver;

/**
 * Bit Webhook Controller
 *
 * Handles server-to-server webhooks (IPN) from SUMIT for Bit payments.
 *
 * ✅ FIX #2: CRITICAL - Always returns HTTP 200 OK (even on errors!)
 * This prevents SUMIT from retrying webhooks up to 5 times.
 *
 * ✅ FIX #10: Uses BitWebhookRequest for centralized validation
 *
 * Port of: ProcessIPN() from WC_OfficeGuyBit with improvements
 */
class BitWebhookController extends Controller
{
    /**
     * Handle Bit payment webhook.
     *
     * CRITICAL: This method MUST always return HTTP 200 OK to prevent SUMIT retries.
     * Errors are logged but success status is returned to SUMIT.
     *
     * WooCommerce pattern: "Always return 200 to prevent retry loops"
     *
     * @param BitWebhookRequest $request Validated webhook request
     * @return JsonResponse Always returns 200 OK with success/error details
     */
    public function handle(BitWebhookRequest $request): JsonResponse
    {
        // ✅ Validation already handled by BitWebhookRequest
        // If validation fails, BitWebhookRequest returns 200 with errors
        $orderId = $request->getOrderId();
        $orderKey = $request->getOrderKey();
        $documentId = $request->getDocumentId();
        $customerId = $request->getCustomerId();

        OfficeGuyApi::writeToLog(
            "Bit webhook received for order: {$orderId} (orderkey: {$orderKey}, documentid: {$documentId})",
            'debug'
        );

        try {
            // Resolve order model (may be null if not found)
            $order = OrderResolver::resolve($orderId);

            if (! $order) {
                OfficeGuyApi::writeToLog(
                    "Bit webhook: Order {$orderId} not found in system, processing webhook anyway",
                    'warning'
                );
            }

            // ✅ Process webhook with new signature (includes all fixes)
            $success = BitPaymentService::processWebhook(
                $orderId,
                $orderKey,
                $documentId,
                $customerId,
                $order
            );

            if ($success) {
                OfficeGuyApi::writeToLog(
                    "Bit webhook processed successfully for order: {$orderId}",
                    'info'
                );

                // ✅ SECURE SUCCESS FLOW: Mark transaction as webhook-confirmed
                // This is the gatekeeper - only webhook can confirm
                if ($order && method_exists($order, 'transactions')) {
                    $transaction = $order->transactions()
                        ->where('document_id', $documentId)
                        ->latest()
                        ->first();

                    if ($transaction) {
                        $transaction->update([
                            'is_webhook_confirmed' => true,
                            'confirmed_at' => now(),
                            'confirmed_by' => 'webhook',
                        ]);

                        OfficeGuyApi::writeToLog(
                            "Transaction {$transaction->id} marked as webhook-confirmed",
                            'debug'
                        );
                    }
                }

                // ✅ FIX #2: Return 200 OK on success
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook processed successfully',
                    'order_id' => $orderId,
                ], 200);
            }

            // Processing failed (logical error, not server error)
            OfficeGuyApi::writeToLog(
                "Bit webhook processing failed for order: {$orderId} (logical error, not retryable)",
                'error'
            );

            // ✅ FIX #2: Return 200 OK even on logical failure
            // This prevents SUMIT from retrying a request that won't succeed
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed (logical error)',
                'order_id' => $orderId,
            ], 200); // ← CRITICAL: Must be 200, not 500!

        } catch (\Exception $e) {
            // Exception occurred during processing
            OfficeGuyApi::writeToLog(
                "Bit webhook exception for order {$orderId}: {$e->getMessage()}. ".
                "Stack trace: {$e->getTraceAsString()}",
                'error'
            );

            // ✅ FIX #2: Return 200 OK even on exception
            // SUMIT should not retry exceptions (they're usually permanent errors)
            return response()->json([
                'success' => false,
                'message' => 'Internal error during webhook processing',
                'order_id' => $orderId,
                'error' => config('app.debug') ? $e->getMessage() : 'Internal error',
            ], 200); // ← CRITICAL: Must be 200, not 500!
        }
    }
}

