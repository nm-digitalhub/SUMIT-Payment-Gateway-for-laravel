<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource;

class ListClientPaymentMethods extends ListRecords
{
    protected static string $resource = ClientPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
