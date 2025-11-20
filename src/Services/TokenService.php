<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;

/**
 * Token Service
 *
 * 1:1 port of OfficeGuyTokens.php from WooCommerce plugin
 * Handles credit card tokenization for recurring payments
 */
class TokenService
{
    /**
     * Get token request array
     *
     * Port of: GetTokenRequest($Gateway)
     *
     * @param string $pciMode PCI mode ('yes', 'no', 'redirect')
     * @return array Token request data
     */
    public static function getTokenRequest(string $pciMode = 'no'): array
    {
        $request = [
            'ParamJ' => config('officeguy.token_param', '5'),
            'Amount' => 1,
            'Credentials' => PaymentService::getCredentials(),
        ];

        if ($pciMode === 'yes') {
            // PCI mode - direct card details
            $request['CardNumber'] = RequestHelpers::post('og-ccnum');
            $request['CVV'] = RequestHelpers::post('og-cvv');
            $request['CitizenID'] = RequestHelpers::post('og-citizenid');

            $expirationMonth = RequestHelpers::post('og-expmonth');
            $request['ExpirationMonth'] = $expirationMonth < 10
                ? '0' . $expirationMonth
                : (string)$expirationMonth;

            $request['ExpirationYear'] = RequestHelpers::post('og-expyear');
        } else {
            // Simple mode - single use token
            $request['SingleUseToken'] = RequestHelpers::post('og-token');
        }

        return $request;
    }

    /**
     * Create token from API response
     *
     * Port of: GetTokenFromResponse($Gateway, $Response)
     *
     * @param mixed $owner Owner model (User, Customer, etc.)
     * @param array $response SUMIT API response
     * @param string $gatewayId Gateway identifier
     * @return OfficeGuyToken
     */
    public static function getTokenFromResponse(
        mixed $owner,
        array $response,
        string $gatewayId = 'officeguy'
    ): OfficeGuyToken {
        return OfficeGuyToken::createFromApiResponse($owner, $response, $gatewayId);
    }

    /**
     * Process token creation
     *
     * Port of: ProcessToken($Gateway)
     *
     * @param mixed $owner Owner model instance
     * @param string $pciMode PCI mode
     * @return array Result with 'success' boolean and 'token' or 'message'
     */
    public static function processToken(mixed $owner, string $pciMode = 'no'): array
    {
        $request = self::getTokenRequest($pciMode);
        $environment = config('officeguy.environment', 'www');

        $response = OfficeGuyApi::post(
            $request,
            '/creditguy/gateway/transaction/',
            $environment,
            false
        );

        // Check response
        if ($response && $response['Status'] === 0 && ($response['Data']['Success'] ?? false)) {
            $token = self::getTokenFromResponse($owner, $response);

            if ($token->save()) {
                return [
                    'success' => true,
                    'token' => $token,
                ];
            }

            return [
                'success' => false,
                'message' => __('Failed to save payment method') . ' - ' . ($response['UserErrorMessage'] ?? 'Unknown error'),
            ];
        }

        if ($response && $response['Status'] !== 0) {
            // API error
            return [
                'success' => false,
                'message' => __('Update payment method failed') . ' - ' . ($response['UserErrorMessage'] ?? 'Unknown error'),
            ];
        }

        // Transaction declined
        return [
            'success' => false,
            'message' => __('Update payment method failed') . ' - ' . ($response['Data']['ResultDescription'] ?? 'Unknown error'),
        ];
    }

    /**
     * Save token to order/payable
     *
     * Port of: SaveTokenToOrder($Order, $Token)
     *
     * @param mixed $order Order model
     * @param OfficeGuyToken $token Token instance
     * @return void
     */
    public static function saveTokenToOrder(mixed $order, OfficeGuyToken $token): void
    {
        // In Laravel, we might store this relationship differently
        // This could be a metadata field or a separate pivot table
        // For now, we'll log it
        OfficeGuyApi::writeToLog(
            'Order #' . $order->getPayableId() . ' added payment token #' . $token->id,
            'debug'
        );

        // If the order has a tokens relationship, we could do:
        // $order->tokens()->attach($token->id);
        // Or store in metadata:
        // $order->setMeta('og_token_id', $token->id);
    }

    /**
     * Get payment method array from token
     *
     * @param OfficeGuyToken $token Token instance
     * @param string|null $cvv CVV code from request (required for some transactions)
     * @return array Payment method data
     */
    public static function getPaymentMethodFromToken(OfficeGuyToken $token, ?string $cvv = null): array
    {
        return [
            'CreditCard_Token' => $token->token,
            'CreditCard_CVV' => $cvv ?? RequestHelpers::post('og-cvv'),
            'CreditCard_CitizenID' => $token->citizen_id,
            'CreditCard_ExpirationMonth' => $token->expiry_month,
            'CreditCard_ExpirationYear' => $token->expiry_year,
            'Type' => 1,
        ];
    }

    /**
     * Get payment method array for PCI mode
     *
     * @return array Payment method data
     */
    public static function getPaymentMethodPCI(): array
    {
        $expirationMonth = RequestHelpers::post('og-expmonth');
        $paddedMonth = $expirationMonth < 10
            ? '0' . $expirationMonth
            : (string)$expirationMonth;

        return [
            'CreditCard_Number' => RequestHelpers::post('og-ccnum'),
            'CreditCard_CVV' => RequestHelpers::post('og-cvv'),
            'CreditCard_CitizenID' => RequestHelpers::post('og-citizenid'),
            'CreditCard_ExpirationMonth' => $paddedMonth,
            'CreditCard_ExpirationYear' => RequestHelpers::post('og-expyear'),
            'Type' => 1,
        ];
    }
}
