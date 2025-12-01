<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\CrmFolderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmFolder extends CreateRecord
{
    protected static string $resource = CrmFolderResource::class;
}
