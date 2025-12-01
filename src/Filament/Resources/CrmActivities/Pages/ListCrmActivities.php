<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\CrmActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListCrmActivities extends ListRecords
{
    protected static string $resource = CrmActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - activities are created through entities
        ];
    }
}
