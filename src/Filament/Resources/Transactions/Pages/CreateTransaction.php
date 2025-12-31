<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Pages;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
}
