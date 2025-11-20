<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTransactionResource\Pages;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Enums\FontWeight;

class OfficeGuyTransactionResource extends Resource
{
    protected static ?string $model = OfficeGuyTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Transactions';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transactions';

    protected static ?string $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Information')
                    ->schema([
                        Forms\Components\TextInput::make('payment_id')
                            ->label('Payment ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('order_id')
                            ->label('Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('auth_number')
                            ->label('Authorization Number')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->disabled()
                            ->prefix(fn ($record) => $record?->currency ?? ''),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Payment Details')
                    ->schema([
                        Forms\Components\TextInput::make('payment_method')
                            ->label('Payment Method')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_digits')
                            ->label('Card Last 4 Digits')
                            ->disabled(),
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiration_month')
                            ->label('Expiry Month')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiration_year')
                            ->label('Expiry Year')
                            ->disabled(),
                        Forms\Components\TextInput::make('payments_count')
                            ->label('Installments')
                            ->numeric()
                            ->disabled(),
                    ])->columns(3),
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
                Tables\Columns\TextColumn::make('payment_id')
                    ->label('Payment ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_digits')
                    ->label('Card')
                    ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-'),
                Tables\Columns\IconColumn::make('is_test')
                    ->label('Test')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn () => OfficeGuyTransaction::query()
                        ->distinct()
                        ->pluck('currency', 'currency')
                        ->toArray()
                    )
                    ->multiple(),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'card' => 'Card',
                        'bit' => 'Bit',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label('Amount From')
                            ->numeric(),
                        Forms\Components\TextInput::make('amount_to')
                            ->label('Amount To')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
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
                Tables\Filters\TernaryFilter::make('is_test')
                    ->label('Test Transactions')
                    ->placeholder('All transactions')
                    ->trueLabel('Only test')
                    ->falseLabel('Only live'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('refresh')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (OfficeGuyTransaction $record) {
                        // Placeholder for refresh logic
                        // This would call the SUMIT API to get updated transaction status
                        // For now, just show a notification
                        \Filament\Notifications\Notification::make()
                            ->title('Status refresh not yet implemented')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_id')
                            ->label('Payment ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('order_id')
                            ->label('Order ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('order_type')
                            ->label('Order Type'),
                        Infolists\Components\TextEntry::make('document_id')
                            ->label('Document ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('customer_id')
                            ->label('Customer ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('auth_number')
                            ->label('Authorization Number')
                            ->copyable(),
                    ])->columns(3),

                Infolists\Components\Section::make('Payment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Total Amount')
                            ->money(fn ($record) => $record->currency)
                            ->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('currency')
                            ->label('Currency')
                            ->badge(),
                        Infolists\Components\TextEntry::make('payments_count')
                            ->label('Installments'),
                        Infolists\Components\TextEntry::make('first_payment_amount')
                            ->label('First Payment')
                            ->money(fn ($record) => $record->currency)
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('non_first_payment_amount')
                            ->label('Subsequent Payments')
                            ->money(fn ($record) => $record->currency)
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                'refunded' => 'info',
                                'cancelled' => 'gray',
                                default => 'gray',
                            }),
                    ])->columns(3),

                Infolists\Components\Section::make('Card Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->badge(),
                        Infolists\Components\TextEntry::make('last_digits')
                            ->label('Card Number')
                            ->formatStateUsing(fn ($state) => $state ? '**** **** **** ' . $state : '-'),
                        Infolists\Components\TextEntry::make('card_type')
                            ->label('Card Type')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('expiration_month')
                            ->label('Expiry Month')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('expiration_year')
                            ->label('Expiry Year')
                            ->placeholder('-'),
                    ])->columns(3)
                    ->visible(fn ($record) => $record->payment_method === 'card'),

                Infolists\Components\Section::make('Status Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('status_description')
                            ->label('Status Description')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error Message')
                            ->placeholder('-')
                            ->color('danger'),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Environment')
                    ->schema([
                        Infolists\Components\TextEntry::make('environment')
                            ->label('Environment')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'www' => 'success',
                                'dev' => 'warning',
                                'test' => 'info',
                                default => 'gray',
                            }),
                        Infolists\Components\IconEntry::make('is_test')
                            ->label('Test Transaction')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])->columns(4),

                Infolists\Components\Section::make('Raw Data')
                    ->schema([
                        Infolists\Components\TextEntry::make('raw_request')
                            ->label('Request Data')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->copyable(),
                        Infolists\Components\TextEntry::make('raw_response')
                            ->label('Response Data')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->copyable(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ]);
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
            'index' => Pages\ListOfficeGuyTransactions::route('/'),
            'view' => Pages\ViewOfficeGuyTransaction::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
