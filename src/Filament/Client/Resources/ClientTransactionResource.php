<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientTransactionResource\Pages;

class ClientTransactionResource extends Resource
{
    protected static ?string $model = OfficeGuyTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'My Transactions';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        // Filter to only show transactions for the authenticated user
        return parent::getEloquentQuery()
            ->where('customer_id', auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('created_at')
                            ->label('Date')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Card Information')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_digits')
                            ->label('Card Number')
                            ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Installments')
                    ->visible(fn ($record) => $record?->payments_count > 1)
                    ->schema([
                        Forms\Components\TextInput::make('payments_count')
                            ->label('Number of Payments')
                            ->disabled(),
                        Forms\Components\TextInput::make('first_payment_amount')
                            ->label('First Payment')
                            ->disabled(),
                        Forms\Components\TextInput::make('non_first_payment_amount')
                            ->label('Other Payments')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => 'failed',
                        'secondary' => 'refunded',
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_digits')
                    ->label('Card')
                    ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-'),
                Tables\Columns\TextColumn::make('payments_count')
                    ->label('Installments')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListClientTransactions::route('/'),
            'view' => Pages\ViewClientTransaction::route('/{record}'),
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

    public static function canDelete($record): bool
    {
        return false;
    }
}
