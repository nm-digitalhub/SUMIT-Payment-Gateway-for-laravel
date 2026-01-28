<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SUMIT Exchange Rate Service
 *
 * Handles currency exchange rate operations via SUMIT API
 * Endpoint: POST /accounting/general/getexchangerate/
 */
class ExchangeRateService
{
    /**
     * Cache TTL for exchange rates (in seconds)
     *
     * Exchange rates update logic:
     * - Historical rates (past dates): 24 hours (they don't change)
     * - Today's rate before 4:00 PM: 2 hours (waiting for daily update)
     * - Today's rate after 4:00 PM: 8 hours (already updated for the day)
     *
     * Bank of Israel typically updates rates once daily around 4:00 PM Israel time
     */
    private const CACHE_TTL_HISTORICAL = 86400;  // 24 hours

    private const CACHE_TTL_TODAY_BEFORE_UPDATE = 7200;  // 2 hours

    private const CACHE_TTL_TODAY_AFTER_UPDATE = 28800;  // 8 hours

    private const BANK_UPDATE_HOUR = 16;  // 4:00 PM Israel time

    /**
     * Get exchange rate from SUMIT API
     *
     * @param  string|null  $currencyFrom  Source currency (e.g., 'USD')
     * @param  string|null  $currencyTo  Target currency (e.g., 'ILS')
     * @param  Carbon|null  $date  Date for historical rates (null = today)
     * @param  bool  $useCache  Whether to use cached rates
     * @return array{rate: float, source: string, date: string, cached: bool, age_seconds: int}|null
     */
    public function getExchangeRate(
        ?string $currencyFrom = 'USD',
        ?string $currencyTo = 'ILS',
        ?Carbon $date = null,
        bool $useCache = true
    ): ?array {
        // Default to today if no date provided
        if (! $date instanceof \Carbon\Carbon) {
            $date = Carbon::now();
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($currencyFrom, $currencyTo, $date);

        // Try cache first if enabled
        if ($useCache && Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            $cached['cached'] = true;
            $cached['age_seconds'] = Carbon::parse($cached['fetched_at'])->diffInSeconds(Carbon::now());

            Log::debug('Exchange rate from cache', [
                'from' => $currencyFrom,
                'to' => $currencyTo,
                'rate' => $cached['rate'],
                'age_seconds' => $cached['age_seconds'],
            ]);

            return $cached;
        }

        // Fetch from API
        $rateData = $this->fetchFromApi($currencyFrom, $currencyTo, $date);

        if ($rateData === null) {
            return null;
        }

        // Prepare response
        $response = [
            'rate' => $rateData,
            'source' => 'sumit_api',
            'date' => $date->format('Y-m-d'),
            'cached' => false,
            'age_seconds' => 0,
            'fetched_at' => Carbon::now()->toIso8601String(),
        ];

        // Cache the result with smart TTL
        if ($useCache) {
            $ttl = $this->calculateCacheTtl($date);
            Cache::put($cacheKey, $response, $ttl);
        }

        Log::info('Exchange rate fetched from SUMIT API', [
            'from' => $currencyFrom,
            'to' => $currencyTo,
            'rate' => $response['rate'],
            'date' => $response['date'],
        ]);

        return $response;
    }

    /**
     * Get USD to ILS exchange rate (most common use case)
     *
     * @param  Carbon|null  $date  Date for rate (null = today)
     * @param  bool  $useCache  Whether to use cached rates
     * @return float|null Exchange rate or null on error
     */
    public function getUsdToIls(?Carbon $date = null, bool $useCache = true): ?float
    {
        $result = $this->getExchangeRate('USD', 'ILS', $date, $useCache);

        return $result ? $result['rate'] : null;
    }

    /**
     * Get USD to ILS rate for transaction processing
     * Always uses cache for consistency within transaction
     *
     * @return array{rate: float, source: string, cached: bool, age_seconds: int}
     */
    public function getUsdToIlsForTransaction(): array
    {
        $result = $this->getExchangeRate('USD', 'ILS', null, true);

        if ($result === null) {
            // Fallback to config if API fails
            $fallbackRate = (float) config('services.currency.usd_to_ils', 3.7);

            Log::warning('SUMIT exchange rate API failed, using fallback', [
                'fallback_rate' => $fallbackRate,
            ]);

            return [
                'rate' => $fallbackRate,
                'source' => 'config_fallback',
                'cached' => false,
                'age_seconds' => 0,
            ];
        }

        return $result;
    }

    /**
     * Fetch exchange rate from SUMIT API
     *
     * @param  string|null  $currencyFrom  Source currency
     * @param  string|null  $currencyTo  Target currency
     * @param  Carbon  $date  Date for rate
     * @return float|null Exchange rate or null on error
     */
    private function fetchFromApi(?string $currencyFrom, ?string $currencyTo, Carbon $date): ?float
    {
        // Get credentials from settings
        $companyId = (int) config('officeguy.company_id');
        $apiKey = config('officeguy.private_key');

        if ($companyId === 0 || empty($apiKey)) {
            Log::error('SUMIT credentials not configured for exchange rate API');

            return null;
        }

        // Build request payload
        $request = [
            'Credentials' => [
                'CompanyID' => $companyId,
                'APIKey' => $apiKey,
            ],
            'Date' => $date->format('Y-m-d\TH:i:s.v\Z'), // ISO 8601 format
            'Currency_From' => $currencyFrom,
            'Currency_To' => $currencyTo,
        ];

        // Get environment
        $environment = config('officeguy.environment', 'www');

        // Make API call
        $response = OfficeGuyApi::post(
            $request,
            '/accounting/general/getexchangerate/',
            $environment,
            false
        );

        // Handle response
        if ($response === null) {
            Log::error('SUMIT exchange rate API returned null response');

            return null;
        }

        // Check status (SUMIT returns integer 0 for success, not string "Success (0)")
        $status = $response['Status'] ?? null;
        if (! in_array($status, [0, '0', 'Success (0)', 'Success'], true)) {
            Log::error('SUMIT exchange rate API error', [
                'status' => $status ?? 'unknown',
                'error_message' => $response['UserErrorMessage'] ?? 'No error message',
                'technical_details' => $response['TechnicalErrorDetails'] ?? 'No technical details',
            ]);

            return null;
        }

        // Extract rate from response (nested in Data object)
        $rate = $response['Data']['Rate'] ?? null;

        if ($rate === null || ! is_numeric($rate)) {
            Log::error('SUMIT exchange rate API returned invalid data', [
                'data' => $response['Data'] ?? 'null',
            ]);

            return null;
        }

        return (float) $rate;
    }

    /**
     * Generate cache key for exchange rate
     *
     * @param  string|null  $currencyFrom  Source currency
     * @param  string|null  $currencyTo  Target currency
     * @param  Carbon  $date  Date for rate
     * @return string Cache key
     */
    private function generateCacheKey(?string $currencyFrom, ?string $currencyTo, Carbon $date): string
    {
        return sprintf(
            'sumit_exchange_rate_%s_%s_%s',
            strtoupper($currencyFrom ?? 'USD'),
            strtoupper($currencyTo ?? 'ILS'),
            $date->format('Y-m-d')
        );
    }

    /**
     * Clear exchange rate cache
     *
     * @param  string|null  $currencyFrom  Source currency (null = all)
     * @param  string|null  $currencyTo  Target currency (null = all)
     * @param  Carbon|null  $date  Date (null = all dates)
     */
    public function clearCache(?string $currencyFrom = null, ?string $currencyTo = null, ?Carbon $date = null): void
    {
        if ($currencyFrom !== null && $currencyTo !== null && $date instanceof \Carbon\Carbon) {
            // Clear specific rate
            $cacheKey = $this->generateCacheKey($currencyFrom, $currencyTo, $date);
            Cache::forget($cacheKey);

            Log::info('Cleared specific exchange rate cache', [
                'from' => $currencyFrom,
                'to' => $currencyTo,
                'date' => $date->format('Y-m-d'),
            ]);
        } else {
            // Clear all exchange rates
            Cache::forget('sumit_exchange_rate_*');

            Log::info('Cleared all exchange rate cache');
        }
    }

    /**
     * Convert amount using exchange rate
     *
     * @param  float  $amount  Amount to convert
     * @param  string|null  $currencyFrom  Source currency
     * @param  string|null  $currencyTo  Target currency
     * @param  Carbon|null  $date  Date for rate
     * @return float|null Converted amount or null on error
     */
    public function convertAmount(
        float $amount,
        ?string $currencyFrom = 'USD',
        ?string $currencyTo = 'ILS',
        ?Carbon $date = null
    ): ?float {
        $rateData = $this->getExchangeRate($currencyFrom, $currencyTo, $date);

        if ($rateData === null) {
            return null;
        }

        return round($amount * $rateData['rate'], 2);
    }

    /**
     * Calculate smart cache TTL based on date
     *
     * Exchange rates are updated once daily by Bank of Israel around 4:00 PM.
     * This method returns appropriate TTL to minimize API calls while staying current.
     *
     * @param  Carbon  $date  Date for the exchange rate
     * @return int Cache TTL in seconds
     */
    private function calculateCacheTtl(Carbon $date): int
    {
        $now = Carbon::now('Asia/Jerusalem');
        $requestDate = $date->copy()->setTimezone('Asia/Jerusalem');

        // Historical rates (past dates) - cache for 24 hours
        if ($requestDate->isBefore($now->copy()->startOfDay())) {
            return self::CACHE_TTL_HISTORICAL;
        }

        // Today's rate - depends on time of day
        if ($requestDate->isSameDay($now)) {
            $currentHour = $now->hour;

            // Before 4:00 PM - rates might still update today
            if ($currentHour < self::BANK_UPDATE_HOUR) {
                return self::CACHE_TTL_TODAY_BEFORE_UPDATE;
            }

            // After 4:00 PM - rates already updated for today
            return self::CACHE_TTL_TODAY_AFTER_UPDATE;
        }

        // Future dates (shouldn't happen, but treat as current)
        return self::CACHE_TTL_TODAY_BEFORE_UPDATE;
    }
}
