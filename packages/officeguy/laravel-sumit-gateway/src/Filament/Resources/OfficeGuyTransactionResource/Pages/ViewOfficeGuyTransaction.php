<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource;

class ViewOfficeGuyTransaction extends ViewRecord
{
    protected static string $resource = OfficeGuyTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh_status')
                ->label('Refresh Status')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    // Placeholder for refresh logic
                    \Filament\Notifications\Notification::make()
                        ->title('Status refresh not yet implemented')
                        ->body('This would query the SUMIT API to update the transaction status.')
                        ->warning()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
