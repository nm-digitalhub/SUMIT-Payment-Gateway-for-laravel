<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource;

class ViewClientSubscription extends ViewRecord
{
    protected static string $resource = ClientSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - read-only for clients
        ];
    }
}
