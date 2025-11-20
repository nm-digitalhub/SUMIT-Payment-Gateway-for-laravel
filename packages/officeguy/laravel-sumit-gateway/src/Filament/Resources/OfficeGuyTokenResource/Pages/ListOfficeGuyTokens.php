<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTokenResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTokenResource;

class ListOfficeGuyTokens extends ListRecords
{
    protected static string $resource = OfficeGuyTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - tokens are created automatically
        ];
    }
}
