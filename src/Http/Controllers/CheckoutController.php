<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Support\OrderResolver;

/**
 * Example checkout endpoint using PaymentService::processCharge
 */
class CheckoutController extends Controller
{
    public function charge(Request $request): Response
    {
        $orderId = $request->input('order_id');
        $paymentsCount = max(1, (int) $request->input('payments_count', 1));
        $recurring = (bool) $request->boolean('recurring', false);

        $order = OrderResolver::resolve($orderId);
        if (!$order) {
            return response(['message' => 'Order not found or not payable'], 404);
        }

        $pciMode = config('officeguy.pci', config('officeguy.pci_mode', 'no'));
        $redirectMode = $pciMode === 'redirect';

        $extra = [];
        if ($redirectMode) {
            $extra['RedirectURL'] = route(config('officeguy.routes.success', 'checkout.success'), ['order' => $orderId]);
            $extra['CancelRedirectURL'] = route(config('officeguy.routes.failed', 'checkout.failed'), ['order' => $orderId]);
        }

        // Optional: load saved token by ID
        $tokenId = $request->input('token_id');
        $token = $tokenId ? OfficeGuyToken::find($tokenId) : null;

        $result = PaymentService::processCharge(
            $order,
            $paymentsCount,
            $recurring,
            $redirectMode,
            $token,
            $extra
        );

        if ($result['success'] === true) {
            // Create document immediately if configured and not redirect
            if (!$redirectMode && config('officeguy.create_order_document', false)) {
                $customer = PaymentService::getOrderCustomer($order);
                DocumentService::createOrderDocument($order, $customer, $result['response']['Data']['DocumentID'] ?? null);
            }

            // Redirect flow
            if ($redirectMode && isset($result['redirect_url'])) {
                return response(['redirect_url' => $result['redirect_url']], 200);
            }

            return response([
                'message' => 'Payment completed',
                'payment' => $result['payment'] ?? null,
            ], 200);
        }

        $status = $result['response']['Status'] ?? null;
        $errorMessage = $result['message'] ?? 'Payment failed';

        return response([
            'message' => $errorMessage,
            'status' => $status,
            'response' => $result['response'] ?? null,
        ], 422);
    }
}
