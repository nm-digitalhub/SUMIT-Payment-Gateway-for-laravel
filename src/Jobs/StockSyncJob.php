<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OfficeGuy\LaravelSumitGateway\Services\Stock\StockService;

class StockSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public bool $force = false) {}

    public function handle(StockService $service): void
    {
        $service->sync($this->force);
    }
}
