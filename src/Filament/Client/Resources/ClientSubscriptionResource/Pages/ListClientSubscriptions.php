<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource;

class ListClientSubscriptions extends ListRecords
{
    protected static string $resource = ClientSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - subscriptions are created via payment flow
        ];
    }
}
