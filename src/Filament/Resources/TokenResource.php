<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TokenResource\Pages;
use Filament\Notifications\Notification;

class TokenResource extends Resource
{
    protected static ?string $model = OfficeGuyToken::class;

    protected static string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Payment Tokens';

    protected static string|\UnitEnum|null $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Token Information')
                    ->schema([
                        Forms\Components\TextInput::make('token')
                            ->label('Token')
                            ->disabled(),
                        Forms\Components\TextInput::make('gateway_id')
                            ->label('Gateway')
                            ->disabled(),
                        Forms\Components\Checkbox::make('is_default')
                            ->label('Default Token')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Card Details')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_four')
                            ->label('Last 4 Digits')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiry_month')
                            ->label('Expiry Month')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiry_year')
                            ->label('Expiry Year')
                            ->disabled(),
                        Forms\Components\TextInput::make('citizen_id')
                            ->label('Citizen ID')
                            ->disabled(),
                    ])->columns(5),

                Forms\Components\Section::make('Owner Information')
                    ->schema([
                        Forms\Components\TextInput::make('owner_type')
                            ->label('Owner Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('owner_id')
                            ->label('Owner ID')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data')
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
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('card_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_four')
                    ->label('Card Number')
                    ->formatStateUsing(fn ($state) => '**** **** **** ' . $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiry_month')
                    ->label('Expiry')
                    ->formatStateUsing(fn ($record) => 
                        $record->expiry_month . '/' . substr($record->expiry_year, -2)
                    )
                    ->badge()
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Tokens'),
                Tables\Filters\SelectFilter::make('card_type')
                    ->label('Card Type')
                    ->options([
                        'card' => 'Card',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('set_default')
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
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTokens::route('/'),
            'view' => Pages\ViewToken::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $expiredCount = static::getModel()::query()
            ->get()
            ->filter(fn ($token) => $token->isExpired())
            ->count();
        
        return $expiredCount > 0 ? (string)$expiredCount : null;
    }
}
