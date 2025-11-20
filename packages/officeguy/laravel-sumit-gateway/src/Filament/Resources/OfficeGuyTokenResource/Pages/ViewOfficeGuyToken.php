<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTokenResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTokenResource;

class ViewOfficeGuyToken extends ViewRecord
{
    protected static string $resource = OfficeGuyTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->visible(fn () => !$this->record->is_default)
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->setAsDefault();
                    \Filament\Notifications\Notification::make()
                        ->title('Token set as default')
                        ->success()
                        ->send();
                    $this->refreshFormData(['is_default']);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
