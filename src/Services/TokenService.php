<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\TokenData;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Token\CreateTokenRequest;
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
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Get token type configuration
            $paramJ = config('officeguy.token_param', '5'); // J2/J5

            // Create TokenData based on PCI mode
            if ($pciMode === 'yes') {
                // PCI Mode 'yes' - Direct card data
                $month = (int) RequestHelpers::post('og-expmonth');

                $tokenData = TokenData::fromCardData(
                    cardNumber: RequestHelpers::post('og-ccnum'),
                    cvv: RequestHelpers::post('og-cvv'),
                    citizenId: RequestHelpers::post('og-citizenid'),
                    expirationMonth: $month < 10 ? '0' . $month : (string) $month,
                    expirationYear: RequestHelpers::post('og-expyear'),
                    paramJ: $paramJ
                );
            } else {
                // PCI Mode 'no' - Single-use token from PaymentsJS
                $tokenData = TokenData::fromSingleUseToken(
                    singleUseToken: RequestHelpers::post('og-token'),
                    paramJ: $paramJ
                );
            }

            // Instantiate connector and request
            $connector = new SumitConnector();
            $request = new CreateTokenRequest(
                token: $tokenData,
                credentials: $credentials
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Token processing failed: ' . $e->getMessage(),
            ];
        }

        if (!$data) {
            return [
                'success' => false,
                'message' => __('No response from payment gateway'),
            ];
        }

        $status = $data['Status'] ?? null;
        $responseData = $data['Data'] ?? null;

        // SUCCESS: Status = 0, Success = true
        if ($status === 0 && is_array($responseData) && ($responseData['Success'] ?? false)) {
            try {
                $token = self::getTokenFromResponse($owner, $data);
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
                    ($data['UserErrorMessage'] ?? 'Gateway error'),
            ];
        }

        // DECLINE
        return [
            'success' => false,
            'message' => __('Payment method update failed') . ' - ' .
                ($responseData['ResultDescription'] ?? 'Unknown decline'),
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

    /**
     * Sync a single token from SUMIT API and update local database.
     * Fetches all payment methods for the token's owner and updates the matching token.
     *
     * @param OfficeGuyToken $token Token to sync
     * @return array{success: bool, updated?: bool, error?: string}
     */
    public static function syncTokenFromSumit(OfficeGuyToken $token): array
    {
        try {
            // Get owner (should be Client after migration)
            $owner = $token->owner;
            if (!$owner) {
                return [
                    'success' => false,
                    'error' => 'Token owner not found',
                ];
            }

            // Owner should be Client with sumit_customer_id
            $sumitCustomerId = $owner->sumit_customer_id ?? null;

            if (!$sumitCustomerId) {
                return [
                    'success' => false,
                    'error' => 'SUMIT customer ID not found for token owner',
                ];
            }

            // Fetch all payment methods from SUMIT
            $result = PaymentService::getPaymentMethodsForCustomer($sumitCustomerId, true);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fetch payment methods from SUMIT',
                ];
            }

            $methods = $result['payment_methods'] ?? [];
            $updated = false;

            // Find matching token in SUMIT response
            foreach ($methods as $method) {
                $apiToken = $method['CreditCard_Token'] ?? null;
                if ($apiToken === $token->token) {
                    // Update token with fresh data from SUMIT
                    $token->update([
                        'card_type' => (string) ($method['Type'] ?? '1'),
                        'last_four' => $method['CreditCard_LastDigits']
                            ?? substr((string) ($method['CreditCard_CardMask'] ?? ''), -4),
                        'citizen_id' => $method['CreditCard_CitizenID'] ?? null,
                        'expiry_month' => str_pad((string) ($method['CreditCard_ExpirationMonth'] ?? '1'), 2, '0', STR_PAD_LEFT),
                        'expiry_year' => (string) ($method['CreditCard_ExpirationYear'] ?? date('Y')),
                        'metadata' => $method,
                    ]);

                    $updated = true;
                    break;
                }
            }

            if (!$updated) {
                return [
                    'success' => false,
                    'error' => 'Token not found in SUMIT (may have been deleted)',
                ];
            }

            return [
                'success' => true,
                'updated' => true,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}