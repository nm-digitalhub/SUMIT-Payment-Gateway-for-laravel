<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;
use RuntimeException;

class TokenService
{
    public static function getTokenRequest(string $pciMode = 'no'): array
    {
        $req = [
            'ParamJ'      => config('officeguy.token_param', '5'),
            'Amount'      => 1,
            'Credentials' => PaymentService::getCredentials(),
        ];

        if ($pciMode === 'yes') {
            $month = (int) RequestHelpers::post('og-expmonth');

            $req += [
                'CardNumber'      => RequestHelpers::post('og-ccnum'),
                'CVV'             => RequestHelpers::post('og-cvv'),
                'CitizenID'       => RequestHelpers::post('og-citizenid'),
                'ExpirationMonth' => $month < 10 ? '0' . $month : (string)$month,
                'ExpirationYear'  => RequestHelpers::post('og-expyear'),
            ];

        } else {
            // hosted fields token
            $req['SingleUseToken'] = RequestHelpers::post('og-token');
        }

        return $req;
    }

    public static function getTokenFromResponse(
        mixed $owner,
        array $response,
        string $gatewayId = 'officeguy'
    ): OfficeGuyToken {
        return OfficeGuyToken::createFromApiResponse($owner, $response, $gatewayId);
    }

    public static function processToken(mixed $owner, string $pciMode = 'no'): array
    {
        $req = self::getTokenRequest($pciMode);

        $env = config('officeguy.environment', 'www');

        $response = OfficeGuyApi::post(
            $req,
            '/creditguy/gateway/transaction/',
            $env,
            false
        );

        if (!$response) {
            return [
                'success' => false,
                'message' => __('No response from payment gateway'),
            ];
        }

        $status = $response['Status'] ?? null;
        $data   = $response['Data'] ?? null;

        // SUCCESS: Status = 0, Success = true
        if ($status === 0 && is_array($data) && ($data['Success'] ?? false)) {
            try {
                $token = self::getTokenFromResponse($owner, $response);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to parse token: ' . $e->getMessage(),
                ];
            }

            return [
                'success' => true,
                'token'   => $token,
            ];
        }

        // API ERROR
        if ($status !== 0) {
            return [
                'success' => false,
                'message' => __('Payment method update failed') . ' - ' .
                    ($response['UserErrorMessage'] ?? 'Gateway error'),
            ];
        }

        // DECLINE
        return [
            'success' => false,
            'message' => __('Payment method update failed') . ' - ' .
                ($data['ResultDescription'] ?? 'Unknown decline'),
        ];
    }

    public static function getPaymentMethodFromToken(OfficeGuyToken $token, ?string $cvv = null): array
    {
        return [
            'CreditCard_Token'           => $token->token,
            'CreditCard_CVV'             => $cvv ?? RequestHelpers::post('og-cvv'),
            'CreditCard_CitizenID'       => $token->citizen_id,
            'CreditCard_ExpirationMonth' => $token->expiry_month,
            'CreditCard_ExpirationYear'  => $token->expiry_year,
            'Type'                       => 1,
        ];
    }

    public static function getPaymentMethodPCI(): array
    {
        $month = (int) RequestHelpers::post('og-expmonth');

        return [
            'CreditCard_Number'          => RequestHelpers::post('og-ccnum'),
            'CreditCard_CVV'             => RequestHelpers::post('og-cvv'),
            'CreditCard_CitizenID'       => RequestHelpers::post('og-citizenid'),
            'CreditCard_ExpirationMonth' => $month < 10 ? '0' . $month : (string) $month,
            'CreditCard_ExpirationYear'  => RequestHelpers::post('og-expyear'),
            'Type'                       => 1,
        ];
    }
}