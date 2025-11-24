<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

class StockSynced
{
    public function __construct(
        public int $synced,
        public int $skipped,
        public array $data
    ) {}
}
