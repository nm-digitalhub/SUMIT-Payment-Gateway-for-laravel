<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services\Stock;

use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

class StockService
{
    /**
     * Sync stock from SUMIT and dispatch to update callback.
     * If no update callback is configured, the data is logged and returned.
     *
     * @param  bool  $forceIgnoreCooldown  ignore 1h cooldown
     * @return array{synced:int,skipped:int,data:array}
     */
    public function sync(bool $forceIgnoreCooldown = false): array
    {
        $last = cache()->get('officeguy.stock.last_sync');
        if (! $forceIgnoreCooldown && $last && now()->diffInMinutes($last) < 60) {
            return ['synced' => 0, 'skipped' => 0, 'data' => []];
        }

        $request = [
            'Credentials' => PaymentService::getCredentials(),
        ];

        $env = config('officeguy.environment', 'www');
        $body = OfficeGuyApi::post($request, '/stock/stock/list/', $env, false);
        if ($body === null) {
            OfficeGuyApi::writeToLog('Stock sync failed: no response', 'error');

            return ['synced' => 0, 'skipped' => 0, 'data' => []];
        }

        $data = $body['Data']['Stock'] ?? [];

        $updated = 0;
        $skipped = 0;

        $callback = config('officeguy.stock.update_callback');

        foreach ($data as $stockItem) {
            if (is_callable($callback)) {
                try {
                    call_user_func($callback, $stockItem);
                    $updated++;
                } catch (\Throwable $e) {
                    $skipped++;
                    OfficeGuyApi::writeToLog('Stock update callback error: ' . $e->getMessage(), 'error');
                }
            } else {
                $skipped++;
            }
        }

        cache()->put('officeguy.stock.last_sync', now(), now()->addHours(24));
        OfficeGuyApi::writeToLog('Stock sync completed. Updated: ' . $updated . ', Skipped: ' . $skipped, 'info');

        event(new \OfficeGuy\LaravelSumitGateway\Events\StockSynced($updated, $skipped, $data));

        return ['synced' => $updated, 'skipped' => $skipped, 'data' => $data];
    }
}
