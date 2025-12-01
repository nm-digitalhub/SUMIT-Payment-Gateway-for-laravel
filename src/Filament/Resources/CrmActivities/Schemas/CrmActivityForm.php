<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas;

use Filament\Schemas\Schema;

class CrmActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Activities are not created manually through forms
                // They are created programmatically or through SUMIT sync
            ]);
    }
}
