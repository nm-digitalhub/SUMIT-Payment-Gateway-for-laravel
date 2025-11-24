<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource;
use Filament\Actions;

class ListClientPaymentMethods extends ListRecords
{
    protected static string $resource = ClientPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Payment Method'),
        ];
    }
}