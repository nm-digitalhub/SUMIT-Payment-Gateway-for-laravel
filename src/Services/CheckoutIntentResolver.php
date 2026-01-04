<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Http\Request;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\ResolvedPaymentIntent;

/**
 * CheckoutIntentResolver
 *
 * Resolves a CheckoutIntent into a ResolvedPaymentIntent by:
 * - Determining PCI mode and redirect configuration
 * - Extracting payment method details from request
 * - Building redirect URLs if needed
 * - Preparing final payment payload
 *
 * This is the bridge between checkout context (Intent) and payment execution (ResolvedIntent).
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.18.0
 */
class CheckoutIntentResolver
{
    /**
     * Resolve CheckoutIntent into ResolvedPaymentIntent
     *
     * @param CheckoutIntent $intent The checkout context
     * @param Request|null $request Optional HTTP request for extracting single-use token
     * @return ResolvedPaymentIntent Fully resolved payment intent ready for processing
     */
    public static function resolve(CheckoutIntent $intent, ?Request $request = null): ResolvedPaymentIntent
    {
        // Get current request if not provided
        $request = $request ?? request();

        // 1. Determine PCI mode from configuration
        $pciMode = config('officeguy.pci_mode', 'no');
        $redirectMode = $pciMode === 'redirect';

        // 2. Build redirect URLs if in redirect mode
        $redirectUrls = null;
        if ($redirectMode) {
            $redirectUrls = self::buildRedirectUrls($intent);
        }

        // 3. Extract single-use token from request (for PaymentsJS SDK)
        $singleUseToken = $request->input('og-token');

        // 4. Get saved token ID (if customer selected saved payment method)
        $savedToken = $intent->payment->tokenId;

        // 5. Build payment method payload
        $paymentMethodPayload = self::buildPaymentMethodPayload($request, $pciMode);

        // 6. Extract customer citizen ID (CompanyNumber in SUMIT API)
        $customerCitizenId = $intent->customer->citizenId;

        // 7. Determine if recurring (subscription)
        $recurring = self::isRecurringPayment($intent);

        // 8. Create resolved intent
        return new ResolvedPaymentIntent(
            payable: $intent->payable,
            paymentsCount: $intent->payment->installments,
            recurring: $recurring,
            redirectMode: $redirectMode,
            token: $savedToken,
            paymentMethodPayload: $paymentMethodPayload,
            singleUseToken: $singleUseToken,
            customerCitizenId: $customerCitizenId,
            redirectUrls: $redirectUrls,
            pciMode: $pciMode,
        );
    }

    /**
     * Build redirect URLs for PCI redirect mode
     *
     * @param CheckoutIntent $intent
     * @return array{success: string, cancel: string}
     */
    protected static function buildRedirectUrls(CheckoutIntent $intent): array
    {
        $payableId = $intent->payable->getPayableId();

        return [
            'success' => route(config('officeguy.routes.callback_success', 'officeguy.callback.card'), [
                'order' => $payableId,
            ]),
            'cancel' => route(config('officeguy.routes.callback_cancel', 'officeguy.callback.cancel'), [
                'order' => $payableId,
            ]),
        ];
    }

    /**
     * Build payment method payload from request
     *
     * For PCI mode = 'yes': Extract card details from request
     * For PCI mode = 'no': Payload is empty (uses single-use token)
     * For PCI mode = 'redirect': Payload is empty (handled by SUMIT)
     *
     * @param Request $request
     * @param string $pciMode
     * @return array
     */
    protected static function buildPaymentMethodPayload(Request $request, string $pciMode): array
    {
        if ($pciMode !== 'yes') {
            return [];
        }

        // Extract card details for direct PCI mode
        $payload = [];

        if ($request->has('og-ccnum')) {
            $payload['CardNumber'] = $request->input('og-ccnum');
        }

        if ($request->has('og-cvv')) {
            $payload['CVV'] = $request->input('og-cvv');
        }

        if ($request->has('og-expmonth')) {
            $month = (int) $request->input('og-expmonth');
            $payload['ExpirationMonth'] = $month < 10 ? '0' . $month : (string) $month;
        }

        if ($request->has('og-expyear')) {
            $payload['ExpirationYear'] = $request->input('og-expyear');
        }

        return $payload;
    }

    /**
     * Determine if payment is recurring/subscription
     *
     * @param CheckoutIntent $intent
     * @return bool
     */
    protected static function isRecurringPayment(CheckoutIntent $intent): bool
    {
        // Check if PayableType is SUBSCRIPTION
        $payableType = $intent->getPayableType();

        return $payableType->value === 'SUBSCRIPTION';
    }
}
