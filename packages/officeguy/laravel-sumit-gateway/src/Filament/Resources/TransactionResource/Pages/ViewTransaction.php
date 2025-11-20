<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource;
use Filament\Notifications\Notification;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_document')
                ->label('View Document')
                ->icon('heroicon-o-document-text')
                ->visible(fn ($record) => !empty($record->document_id))
                ->url(fn ($record) => route('filament.admin.resources.documents.view', ['record' => $record->document_id]))
                ->openUrlInNewTab(),
            
            Actions\Action::make('refresh_status')
                ->label('Refresh Status')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function ($record) {
                    // TODO: Implement status refresh via API
                    Notification::make()
                        ->title('Status refresh not yet implemented')
                        ->info()
                        ->send();
                }),
        ];
    }
}
