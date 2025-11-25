<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Pages;
use Filament\Support\Colors\Color;

class TransactionResource extends Resource
{
    protected static ?string $model = OfficeGuyTransaction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Transactions';

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('payment_id')
                            ->label('Payment ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('auth_number')
                            ->label('Authorization Number')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method')
                            ->label('Payment Method')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Card Details')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_digits')
                            ->label('Last 4 Digits')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiration_month')
                            ->label('Expiry Month')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiration_year')
                            ->label('Expiry Year')
                            ->disabled(),
                    ])->columns(4),

                Forms\Components\Section::make('Installments')
                    ->schema([
                        Forms\Components\TextInput::make('payments_count')
                            ->label('Number of Payments')
                            ->disabled(),
                        Forms\Components\TextInput::make('first_payment_amount')
                            ->label('First Payment Amount')
                            ->disabled(),
                        Forms\Components\TextInput::make('non_first_payment_amount')
                            ->label('Other Payments Amount')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\TextInput::make('document_id')
                            ->label('Document ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('environment')
                            ->disabled(),
                        Forms\Components\Checkbox::make('is_test')
                            ->label('Test Mode')
                            ->disabled(),
                        Forms\Components\Textarea::make('status_description')
                            ->label('Status Description')
                            ->disabled()
                            ->rows(2),
                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2),

                Forms\Components\Section::make('Raw Data')
                    ->schema([
                        Forms\Components\KeyValue::make('raw_request')
                            ->label('Request Data')
                            ->disabled(),
                        Forms\Components\KeyValue::make('raw_response')
                            ->label('Response Data')
                            ->disabled(),
                    ])->collapsed(),
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
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        'refunded' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_digits')
                    ->label('Card')
                    ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-'),
                Tables\Columns\TextColumn::make('vendor_id')
                    ->label('Vendor')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscription_id')
                    ->label('Subscription')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_donation')
                    ->label('Donation')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_upsell')
                    ->label('Upsell')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('auth_number')
                    ->label('Auth #')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_test')
                    ->label('Test')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'pending' => 'Pending',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'card' => 'Credit Card',
                        'bit' => 'Bit',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('currency')
                    ->options([
                        'ILS' => 'ILS',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                    ])
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_donation')
                    ->label('Donation Transactions'),
                Tables\Filters\TernaryFilter::make('is_upsell')
                    ->label('Upsell Transactions'),
                Tables\Filters\Filter::make('has_vendor')
                    ->label('Vendor Transactions')
                    ->query(fn ($query) => $query->whereNotNull('vendor_id')),
                Tables\Filters\Filter::make('has_subscription')
                    ->label('Subscription Transactions')
                    ->query(fn ($query) => $query->whereNotNull('subscription_id')),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->numeric()
                            ->label('Minimum Amount'),
                        Forms\Components\TextInput::make('amount_to')
                            ->numeric()
                            ->label('Maximum Amount'),
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
                Tables\Filters\TernaryFilter::make('is_test')
                    ->label('Test Transactions'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('create_donation_receipt')
                    ->label('Create Donation Receipt')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->visible(fn ($record) => $record->is_donation && $record->status === 'completed')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        // Trigger donation receipt creation
                        Notification::make()
                            ->title('Donation receipt requested')
                            ->body('Check documents for the new receipt.')
                            ->success()
                            ->send();
                    }),
                Action::make('resend_receipt')
                    ->label('Resend Receipt')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->visible(fn ($record) => $record->status === 'completed' && $record->document_id)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Notification::make()
                            ->title('Receipt resend requested')
                            ->body('The receipt will be sent to the customer.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
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
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
