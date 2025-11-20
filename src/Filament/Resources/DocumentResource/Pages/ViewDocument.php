<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
