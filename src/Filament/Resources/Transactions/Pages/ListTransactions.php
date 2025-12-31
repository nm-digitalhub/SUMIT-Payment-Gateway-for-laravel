<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Pages;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
