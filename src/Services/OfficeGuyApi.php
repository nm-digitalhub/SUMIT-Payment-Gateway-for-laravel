<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SUMIT API Service
 *
 * 1:1 port of OfficeGuyAPI.php from WooCommerce plugin
 * Handles all HTTP communication with SUMIT API endpoints
 */
class OfficeGuyApi
{
    /**
     * Get the API URL for a given path and environment
     *
     * Port of: GetURL($Path, $Environment)
     *
     * @param  string  $path  API endpoint path
     * @param  string  $environment  Environment (www, dev, test)
     * @return string Full API URL
     */
    public static function getUrl(string $path, string $environment): string
    {
        if ($environment === 'dev') {
            return 'http://' . $environment . '.api.sumit.co.il' . $path;
        }

        return 'https://api.sumit.co.il' . $path;
    }

    /**
     * Make a POST request to the SUMIT API
     *
     * Port of: Post($Request, $Path, $Environment, $SendClientIP)
     *
     * @param  array  $request  Request payload
     * @param  string  $path  API endpoint path
     * @param  string  $environment  Environment (www, dev, test)
     * @param  bool  $sendClientIp  Whether to send client IP in headers
     * @return array|null Response body as array, or null on error
     */
    public static function post(
        array $request,
        string $path,
        string $environment,
        bool $sendClientIp = false
    ): ?array {
        return self::postRaw($request, $path, $environment, $sendClientIp);
    }

    /**
     * Make a raw POST request to the SUMIT API
     *
     * Port of: PostRaw($Request, $Path, $Environment, $SendClientIP)
     *
     * @param  array  $request  Request payload
     * @param  string  $path  API endpoint path
     * @param  string  $environment  Environment (www, dev, test)
     * @param  bool  $sendClientIp  Whether to send client IP in headers
     * @return array|null Response body as array, or null on error
     */
    public static function postRaw(
        array $request,
        string $path,
        string $environment,
        bool $sendClientIp = false
    ): ?array {
        if ($environment === '' || $environment === '0') {
            $environment = 'www';
        }

        $url = self::getUrl($path, $environment);

        // Create a copy of request for logging (remove sensitive data)
        $requestLog = $request;
        if (isset($requestLog['PaymentMethod'])) {
            $requestLog['PaymentMethod']['CreditCard_Number'] = '';
            $requestLog['PaymentMethod']['CreditCard_CVV'] = '';
        }
        $requestLog['CardNumber'] = '';
        $requestLog['CVV'] = '';

        self::writeToLog('Request: ' . $url . "\r\n" . json_encode($requestLog, JSON_PRETTY_PRINT), 'debug');

        // Build headers
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Language' => app()->getLocale(),
            'User-Agent' => 'Laravel/12.0 SUMIT-Gateway/1.0',
            'X-OG-Client' => 'Laravel',
        ];

        if ($sendClientIp) {
            $headers['X-OG-ClientIP'] = request()->ip();
        }

        try {
            // Send request
            $response = Http::withHeaders($headers)
                ->timeout(180)
                ->withOptions([
                    'verify' => config('officeguy.ssl_verify', true),
                ])
                ->post($url, $request);

            $responseData = $response->json();

            self::writeToLog('Response: ' . $url . "\r\n" . json_encode($responseData), 'debug');

            return $responseData;
        } catch (RequestException $e) {
            $errorMessage = __('Problem connecting to server at ') . $url . ' (' . $e->getMessage() . ')';
            self::writeToLog('Error: ' . $errorMessage, 'error');

            // In Laravel, we don't have wc_add_notice, so we'll throw or return null
            // The calling code should handle the error appropriately
            return null;
        } catch (\Exception $e) {
            self::writeToLog('Exception: ' . $e->getMessage(), 'error');

            return null;
        }
    }

    /**
     * Check API credentials
     *
     * Port of: CheckCredentials($CompanyID, $APIKey)
     *
     * @param  int  $companyId  SUMIT company ID
     * @param  string  $apiKey  Private API key
     * @return string|null Error message, or null if credentials are valid
     */
    public static function checkCredentials(int $companyId, string $apiKey): ?string
    {
        $credentials = [
            'CompanyID' => $companyId,
            'APIKey' => $apiKey,
        ];

        $request = [
            'Credentials' => $credentials,
        ];

        $environment = config('officeguy.environment', 'www');
        $response = self::post($request, '/website/companies/getdetails/', $environment, false);

        if ($response === null) {
            return 'No response';
        }

        if ($response['Status'] === 'Success') {
            return null;
        }

        return $response['UserErrorMessage'] ?? 'Unknown error';
    }

    /**
     * Check public API credentials
     *
     * Port of: CheckPublicCredentials($CompanyID, $APIPublicKey)
     *
     * @param  int  $companyId  SUMIT company ID
     * @param  string  $apiPublicKey  Public API key
     * @return string|null Error message, or null if credentials are valid
     */
    public static function checkPublicCredentials(int $companyId, string $apiPublicKey): ?string
    {
        $credentials = [
            'CompanyID' => $companyId,
            'APIPublicKey' => $apiPublicKey,
        ];

        $request = [
            'Credentials' => $credentials,
            'CardNumber' => '12345678',
            'ExpirationMonth' => '01',
            'ExpirationYear' => '2030',
            'CVV' => '123',
            'CitizenID' => '123456789',
        ];

        $environment = config('officeguy.environment', 'www');
        $response = self::post($request, '/creditguy/vault/tokenizesingleusejson/', $environment, false);

        if ($response === null) {
            return 'No response';
        }

        if ($response['Status'] === 'Success') {
            return null;
        }

        return $response['UserErrorMessage'] ?? 'Unknown error';
    }

    /**
     * Write to log
     *
     * Port of: WriteToLog($Text, $Type)
     *
     * @param  string  $text  Log message
     * @param  string  $type  Log level (debug, info, warning, error)
     */
    public static function writeToLog(string $text, string $type = 'debug'): void
    {
        if (! config('officeguy.logging', false)) {
            return;
        }

        $channel = config('officeguy.log_channel', 'stack');

        Log::channel($channel)->log($type, $type . ': ' . $text);
    }
}
