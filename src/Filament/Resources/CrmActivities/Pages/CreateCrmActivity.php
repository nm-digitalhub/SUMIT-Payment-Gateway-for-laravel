<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages;

use Filament\Resources\Pages\CreateRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\CrmActivityResource;

class CreateCrmActivity extends CreateRecord
{
    protected static string $resource = CrmActivityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set user_id to current user if not specified
        if (empty($data['user_id'])) {
            $data['user_id'] = auth()->id();
        }

        // Set default status if not specified
        if (empty($data['status'])) {
            $data['status'] = 'planned';
        }

        // Set default priority if not specified
        if (empty($data['priority'])) {
            $data['priority'] = 'normal';
        }

        return $data;
    }
}
