<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Jobs\StockSyncJob;

class StockSyncCommand extends Command
{
    protected $signature = 'sumit:stock-sync {--force : Ignore 1h cooldown}';

    protected $description = 'Synchronize stock from SUMIT gateway';

    public function handle(): int
    {
        dispatch(new StockSyncJob((bool) $this->option('force')));
        $this->info('SUMIT stock sync dispatched');

        return Command::SUCCESS;
    }
}
