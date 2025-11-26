<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\WebhookEventResource\Pages;

class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Webhook Events';

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'event_type';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Event Information')
                    ->schema([
                        Forms\Components\Select::make('event_type')
                            ->label('Event Type')
                            ->options(WebhookEvent::getEventTypes())
                            ->required()
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->options(WebhookEvent::getStatuses())
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->disabled(),
                        Forms\Components\TextInput::make('http_status_code')
                            ->label('HTTP Status')
                            ->numeric()
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Related Resources')
                    ->description('Connected resources for automation workflows')
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Transaction ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('document_id')
                            ->label('Document ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('token_id')
                            ->label('Token ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('subscription_id')
                            ->label('Subscription ID')
                            ->disabled(),
                    ])->columns(4),

                Forms\Components\Section::make('Customer & Amount')
                    ->schema([
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Customer Email')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->label('Currency')
                            ->disabled(),
                    ])->columns(4),

                Forms\Components\Section::make('Retry Information')
                    ->schema([
                        Forms\Components\TextInput::make('retry_count')
                            ->label('Retry Count')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('next_retry_at')
                            ->label('Next Retry At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Error Details')
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(3)
                            ->disabled(),
                    ])
                    ->visible(fn ($record) => !empty($record?->error_message)),

                Forms\Components\Section::make('Payload & Response')
                    ->schema([
                        Forms\Components\KeyValue::make('payload')
                            ->label('Request Payload')
                            ->disabled(),
                        Forms\Components\KeyValue::make('response')
                            ->label('Response Data')
                            ->disabled(),
                    ])->collapsed(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
{
    return $schema->components([
        Infolists\Components\Section::make('Event Details')
            ->schema([
                Infolists\Components\TextEntry::make('event_type')
                    ->label('Event Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'payment_completed', 'bit_payment_completed' => 'success',
                        'payment_failed' => 'danger',
                        'document_created' => 'info',
                        'subscription_created', 'subscription_charged' => 'warning',
                        'stock_synced' => 'gray',
                        default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'retrying' => 'info',
                        default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('webhook_url')
                    ->label('Webhook URL')
                    ->copyable()
                    ->url(fn ($record) => $record->webhook_url, shouldOpenInNewTab: true),
                Infolists\Components\TextEntry::make('http_status_code')
                    ->label('HTTP Status')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('created_at')
                    ->label('Created')
                    ->dateTime(),
                Infolists\Components\TextEntry::make('sent_at')
                    ->label('Sent At')
                    ->dateTime()
                    ->placeholder('Not sent yet'),
            ])->columns(3),

        Infolists\Components\Section::make('Connected Resources')
            ->description('Click to navigate to related records')
            ->schema([
                Infolists\Components\TextEntry::make('transaction.payment_id')
                    ->label('Transaction')
                    ->placeholder('No transaction')
                    ->url(fn ($record) => $record->transaction_id 
                        ? TransactionResource::getUrl('view', ['record' => $record->transaction_id])
                        : null)
                    ->color('primary'),
                Infolists\Components\TextEntry::make('document.document_number')
                    ->label('Document')
                    ->placeholder('No document')
                    ->url(fn ($record) => $record->document_id 
                        ? DocumentResource::getUrl('view', ['record' => $record->document_id])
                        : null)
                    ->color('primary'),
                Infolists\Components\TextEntry::make('token.last_digits')
                    ->label('Token')
                    ->formatStateUsing(fn ($state) => $state ? '****' . $state : null)
                    ->placeholder('No token')
                    ->url(fn ($record) => $record->token_id 
                        ? TokenResource::getUrl('view', ['record' => $record->token_id])
                        : null)
                    ->color('primary'),
                Infolists\Components\TextEntry::make('subscription.name')
                    ->label('Subscription')
                    ->placeholder('No subscription')
                    ->url(fn ($record) => $record->subscription_id 
                        ? SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])
                        : null)
                    ->color('primary'),
            ])->columns(4),

        Infolists\Components\Section::make('Customer & Payment')
            ->schema([
                Infolists\Components\TextEntry::make('customer_email')
                    ->label('Customer Email')
                    ->copyable()
                    ->icon('heroicon-o-envelope'),
                Infolists\Components\TextEntry::make('customer_id')
                    ->label('Customer ID')
                    ->copyable(),
                Infolists\Components\TextEntry::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency ?? 'ILS'),
                Infolists\Components\TextEntry::make('currency')
                    ->badge(),
            ])->columns(4),

        Infolists\Components\Section::make('Retry Status')
            ->schema([
                Infolists\Components\TextEntry::make('retry_count')
                    ->label('Retry Attempts')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state < 3 => 'warning',
                        default => 'danger',
                    }),
                Infolists\Components\TextEntry::make('next_retry_at')
                    ->label('Next Retry')
                    ->dateTime()
                    ->placeholder('No retry scheduled'),
            ])->columns(2)
            ->visible(fn ($record) => $record->retry_count > 0 || $record->next_retry_at),

        Infolists\Components\Section::make('Error Information')
            ->schema([
                Infolists\Components\TextEntry::make('error_message')
                    ->label('Error Message')
                    ->columnSpanFull(),
            ])
            ->visible(fn ($record) => !empty($record->error_message)),

        Infolists\Components\Section::make('Payload')
            ->schema([
                Infolists\Components\KeyValueEntry::make('payload')
                    ->label('Request Payload'),
            ])
            ->collapsed(),

        Infolists\Components\Section::make('Response')
            ->schema([
                Infolists\Components\KeyValueEntry::make('response')
                    ->label('Response Data'),
            ])
            ->collapsed()
            ->visible(fn ($record) => !empty($record->response)),
    ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'payment_completed', 'bit_payment_completed' => 'success',
                        'payment_failed' => 'danger',
                        'document_created' => 'info',
                        'subscription_created', 'subscription_charged' => 'warning',
                        'stock_synced' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($record) => $record->getEventTypeLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'retrying' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'sent' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        'retrying' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('http_status_code')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency ?? 'ILS')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction.payment_id')
                    ->label('Transaction')
                    ->url(fn ($record) => $record->transaction_id 
                        ? TransactionResource::getUrl('view', ['record' => $record->transaction_id])
                        : null)
                    ->color('primary')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscription.name')
                    ->label('Subscription')
                    ->url(fn ($record) => $record->subscription_id 
                        ? SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])
                        : null)
                    ->color('primary')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Retries')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'success',
                        $state < 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(WebhookEvent::getEventTypes())
                    ->multiple(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(WebhookEvent::getStatuses())
                    ->multiple(),
                Tables\Filters\Filter::make('has_transaction')
                    ->label('Has Transaction')
                    ->query(fn ($query) => $query->whereNotNull('transaction_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('has_subscription')
                    ->label('Has Subscription')
                    ->query(fn ($query) => $query->whereNotNull('subscription_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('has_document')
                    ->label('Has Document')
                    ->query(fn ($query) => $query->whereNotNull('document_id'))
                    ->toggle(),
                Tables\Filters\Filter::make('failed_deliveries')
                    ->label('Failed Deliveries')
                    ->query(fn ($query) => $query->where('status', 'failed'))
                    ->toggle(),
                Tables\Filters\Filter::make('pending_retry')
                    ->label('Pending Retry')
                    ->query(fn ($query) => $query->where('status', 'retrying')
                        ->where('next_retry_at', '<=', now()))
                    ->toggle(),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->canRetry())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $webhookService = app(WebhookService::class);
                        $success = $webhookService->send($record->event_type, $record->payload ?? []);
                        
                        if ($success) {
                            $record->markAsSent(200);
                            Notification::make()
                                ->title('Webhook resent successfully')
                                ->success()
                                ->send();
                        } else {
                            $record->scheduleRetry();
                            Notification::make()
                                ->title('Webhook retry failed')
                                ->body('Scheduled for automatic retry.')
                                ->warning()
                                ->send();
                        }
                    }),
                Action::make('copy_payload')
                    ->label('Copy Payload')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function ($record) {
                        // The actual copy is handled client-side
                        Notification::make()
                            ->title('Payload copied to clipboard')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->status !== 'pending'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('retry_all')
                        ->label('Retry Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $webhookService = app(WebhookService::class);
                            $successCount = 0;
                            $failCount = 0;
                            
                            foreach ($records as $record) {
                                if ($record->canRetry()) {
                                    $success = $webhookService->send($record->event_type, $record->payload ?? []);
                                    if ($success) {
                                        $record->markAsSent(200);
                                        $successCount++;
                                    } else {
                                        $record->scheduleRetry();
                                        $failCount++;
                                    }
                                }
                            }
                            
                            Notification::make()
                                ->title('Bulk retry completed')
                                ->body("Success: {$successCount}, Scheduled for retry: {$failCount}")
                                ->success()
                                ->send();
                        }),
                    BulkAction::make('mark_as_failed')
                        ->label('Mark as Failed')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                $record->markAsFailed('Manually marked as failed');
                            }
                            
                            Notification::make()
                                ->title('Events marked as failed')
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookEvents::route('/'),
            'view' => Pages\ViewWebhookEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereIn('status', ['pending', 'retrying', 'failed'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $failedCount = static::getModel()::where('status', 'failed')->count();
        return $failedCount > 0 ? 'danger' : 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['customer_email', 'customer_id', 'event_type'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Event' => $record->getEventTypeLabel(),
            'Status' => ucfirst($record->status),
            'Customer' => $record->customer_email,
        ];
    }
}
