<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyDocumentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyDocumentResource;

class ListOfficeGuyDocuments extends ListRecords
{
    protected static string $resource = OfficeGuyDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - documents are created automatically
        ];
    }
}
