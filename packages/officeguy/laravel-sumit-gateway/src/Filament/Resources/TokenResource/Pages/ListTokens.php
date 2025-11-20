<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;

use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
