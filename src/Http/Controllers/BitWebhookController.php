<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OfficeGuy\LaravelSumitGateway\Services\BitPaymentService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Support\OrderResolver;

/**
 * Bit Webhook Controller
 *
 * Handles server-to-server webhooks (IPN) from SUMIT for Bit payments
 * Port of: ProcessIPN() from WC_OfficeGuyBit
 */
class BitWebhookController extends Controller
{
    /**
     * Handle Bit payment webhook
     *
     * Port of: ProcessIPN() from officeguybit_woocommerce_gateway.php
     *
     * @param Request $request
     * @return Response
     */
    public function handle(Request $request): Response
    {
        $orderId = $request->query('orderid');
        $orderKey = $request->query('orderkey');
        $documentId = $request->input('documentid');
        $customerId = $request->input('customerid');

        OfficeGuyApi::writeToLog(
            'Bit webhook received for order: ' . $orderId,
            'debug'
        );

        if (empty($orderId) || empty($orderKey)) {
            OfficeGuyApi::writeToLog('Bit webhook missing required parameters', 'error');
            return response('Missing parameters', 400);
        }

        if (empty($documentId)) {
            OfficeGuyApi::writeToLog('Bit webhook missing document ID', 'error');
            return response('Missing document ID', 400);
        }

        try {
            $order = OrderResolver::resolve($orderId);

            // Process the webhook
            $success = BitPaymentService::processWebhook(
                $orderId,
                $orderKey,
                $documentId,
                $customerId,
                $order
            );

            if ($success) {
                OfficeGuyApi::writeToLog(
                    'Bit webhook processed successfully for order: ' . $orderId,
                    'info'
                );

                // You might want to trigger an event here for the application to handle
                // event(new BitPaymentCompleted($orderId, $documentId, $customerId));

                return response('OK', 200);
            }

            OfficeGuyApi::writeToLog(
                'Bit webhook processing failed for order: ' . $orderId,
                'error'
            );

            return response('Processing failed', 500);
        } catch (\Exception $e) {
            OfficeGuyApi::writeToLog(
                'Bit webhook exception for order ' . $orderId . ': ' . $e->getMessage(),
                'error'
            );

            return response('Internal error', 500);
        }
    }
}
