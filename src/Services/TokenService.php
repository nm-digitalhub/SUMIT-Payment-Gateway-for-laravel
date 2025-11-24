<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;
use Illuminate\Support\Facades\Log;

class TokenService
{
    /**
     * Build tokenization request
     *
     * Mirrors WooCommerce: GetTokenRequest()
     */
    public static function getTokenRequest(string $pciMode = 'no'): array
    {
        $request = [
            'ParamJ'      => config('officeguy.token_param', '5'),
            'Amount'      => 1,
            'Credentials' => PaymentService::getCredentials(),
        ];

        if ($pciMode === 'yes') {
            // Direct card details (PCI mode)
            $expirationMonth = (int) RequestHelpers::post('og-expmonth');

            $request += [
                'CardNumber'       => RequestHelpers::post('og-ccnum'),
                'CVV'              => RequestHelpers::post('og-cvv'),
                'CitizenID'        => RequestHelpers::post('og-citizenid'),
                'ExpirationMonth'  => $expirationMonth < 10 ? '0' . $expirationMonth : (string) $expirationMonth,
                'ExpirationYear'   => RequestHelpers::post('og-expyear'),
            ];

        } else {
            // Non-PCI / Redirect mode → use single-use token
            $request['SingleUseToken'] = RequestHelpers::post('og-token');
        }

        return $request;
    }

    /**
     * Create token entity from API response
     *
     * Mirrors WooCommerce: GetTokenFromResponse()
     */
    public static function getTokenFromResponse(
        mixed $owner,
        array $response,
        string $gatewayId = 'officeguy'
    ): OfficeGuyToken {

        if (!isset($response['Data']['CardToken'])) {
            throw new \RuntimeException("SUMIT API response missing CardToken");
        }

        return OfficeGuyToken::createFromApiResponse($owner, $response, $gatewayId);
    }

    /**
     * Process tokenization request
     *
     * Mirrors WooCommerce: ProcessToken()
     */
    public static function processToken(mixed $owner, string $pciMode = 'no'): array
    {
        $request     = self::getTokenRequest($pciMode);
        $environment = config('officeguy.environment', 'www');

        $response = OfficeGuyApi::post(
            $request,
            '/creditguy/gateway/transaction/',
            $environment,
            false
        );

        if (!$response) {
            return [
                'success' => false,
                'message' => __('No response from payment gateway'),
            ];
        }

        // API-level error
        if (($response['Status'] ?? null) !== 0) {
            return [
                'success' => false,
                'message' => __('Payment method update failed') . ' - ' .
                    ($response['UserErrorMessage'] ?? 'Gateway error'),
            ];
        }

        // Declined or unsuccessful
        if (!($response['Data']['Success'] ?? false)) {
            return [
                'success' => false,
                'message' => __('Payment method update failed') . ' - ' .
                    ($response['Data']['ResultDescription'] ?? 'Unknown decline'),
            ];
        }

        // SUCCESS → Create token in DB
        $token = self::getTokenFromResponse($owner, $response);

        if (!$token->save()) {
            return [
                'success' => false,
                'message' => __('Failed to save payment method'),
            ];
        }

        return [
            'success' => true,
            'token'   => $token,
        ];
    }

    /**
     * Parity with WooCommerce SaveTokenToOrder()
     */
    public static function saveTokenToOrder(mixed $order, OfficeGuyToken $token): void
    {
        $orderId = method_exists($order, 'getPayableId')
            ? $order->getPayableId()
            : ($order->id ?? 'unknown');

        OfficeGuyApi::writeToLog(
            "Order #{$orderId} added payment token #{$token->id}",
            'debug'
        );

        // Optionally:
        // $order->tokens()->attach($token->id);
        // Or $order->setMeta('og_token_id', $token->id);
    }

    /**
     * Build payment method payload based on existing card token
     */
    public static function getPaymentMethodFromToken(OfficeGuyToken $token, ?string $cvv = null): array
    {
        return [
            'CreditCard_Token'           => $token->token,
            'CreditCard_CVV'            => $cvv ?? RequestHelpers::post('og-cvv'),
            'CreditCard_CitizenID'      => $token->citizen_id,
            'CreditCard_ExpirationMonth'=> $token->expiry_month,
            'CreditCard_ExpirationYear' => $token->expiry_year,
            'Type'                      => 1,
        ];
    }

    /**
     * Build PCI direct-payment payload
     */
    public static function getPaymentMethodPCI(): array
    {
        $expirationMonth = (int) RequestHelpers::post('og-expmonth');

        return [
            'CreditCard_Number'          => RequestHelpers::post('og-ccnum'),
            'CreditCard_CVV'             => RequestHelpers::post('og-cvv'),
            'CreditCard_CitizenID'       => RequestHelpers::post('og-citizenid'),
            'CreditCard_ExpirationMonth' => $expirationMonth < 10 ? '0' . $expirationMonth : (string)$expirationMonth,
            'CreditCard_ExpirationYear'  => RequestHelpers::post('og-expyear'),
            'Type'                       => 1,
        ];
    }
}