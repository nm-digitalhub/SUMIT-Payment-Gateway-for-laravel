<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource;
use Filament\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use App\Models\Client;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_document')
                ->label('צפה במסמך')
                ->icon('heroicon-o-document-text')
                ->visible(fn ($record) => !empty($record->document_id))
                ->url(fn ($record) => route('filament.admin.resources.documents.view', ['record' => $record->document_id]))
                ->openUrlInNewTab(),
            
            Actions\Action::make('refresh_status')
                ->label('רענן סטטוס')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function ($record) {
                    // TODO: Implement status refresh via API
                    Notification::make()
                        ->title('עדכון סטטוס טרם יושם')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('open_subscription')
                ->label('פתח מנוי')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn ($record) => $record->subscription_id && Subscription::find($record->subscription_id))
                ->url(function ($record) {
                    $sub = Subscription::find($record->subscription_id);
                    return $sub ? route('filament.admin.resources.subscriptions.view', ['record' => $sub->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('open_client')
                ->label('פתח לקוח')
                ->icon('heroicon-o-user')
                ->color('primary')
                ->visible(function ($record) {
                    return Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->exists();
                })
                ->url(function ($record) {
                    $client = Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->first();
                    return $client ? route('filament.admin.resources.clients.view', ['record' => $client->id]) : null;
                })
                ->openUrlInNewTab(),
        ];
    }
}
