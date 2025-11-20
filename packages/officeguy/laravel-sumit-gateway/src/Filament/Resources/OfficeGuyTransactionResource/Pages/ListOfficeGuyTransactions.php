<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource;

class ListOfficeGuyTransactions extends ListRecords
{
    protected static string $resource = OfficeGuyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - transactions are created automatically
        ];
    }
}
