<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource;
use Filament\Notifications\Notification;

class ViewToken extends ViewRecord
{
    protected static string $resource = TokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon('heroicon-o-star')
                ->visible(fn ($record) => !$record->is_default)
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->setAsDefault();
                    Notification::make()
                        ->title('Token set as default')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
