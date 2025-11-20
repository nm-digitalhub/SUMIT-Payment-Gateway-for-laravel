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
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyTokenResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class OfficeGuyTokenResource extends Resource
{
    protected static ?string $model = OfficeGuyToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Payment Tokens';

    protected static ?string $modelLabel = 'Payment Token';

    protected static ?string $pluralModelLabel = 'Payment Tokens';

    protected static ?string $navigationGroup = 'SUMIT Gateway';

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
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Token')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Card Information')
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
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner_type')
                    ->label('Owner Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_four')
                    ->label('Card')
                    ->formatStateUsing(fn ($state) => '**** **** **** ' . $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_month')
                    ->label('Expiry')
                    ->formatStateUsing(fn ($record) => 
                        $record->expiry_month . '/' . substr($record->expiry_year, -2)
                    )
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : 'success')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gateway_id')
                    ->label('Gateway')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('card_type')
                    ->label('Card Type')
                    ->options(fn () => OfficeGuyToken::query()
                        ->distinct()
                        ->pluck('card_type', 'card_type')
                        ->toArray()
                    )
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Token')
                    ->placeholder('All tokens')
                    ->trueLabel('Default only')
                    ->falseLabel('Non-default only'),
                Tables\Filters\Filter::make('expired')
                    ->label('Expired Tokens')
                    ->query(fn (Builder $query): Builder => 
                        $query->where(function ($q) {
                            $now = now();
                            $q->where(function ($sq) use ($now) {
                                $sq->where('expiry_year', '<', $now->year);
                            })->orWhere(function ($sq) use ($now) {
                                $sq->where('expiry_year', '=', $now->year)
                                   ->where('expiry_month', '<', $now->month);
                            });
                        })
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('set_default')
                    ->label('Set Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (OfficeGuyToken $record) => !$record->is_default)
                    ->requiresConfirmation()
                    ->action(function (OfficeGuyToken $record) {
                        $record->setAsDefault();
                        \Filament\Notifications\Notification::make()
                            ->title('Token set as default')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
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
                Infolists\Components\Section::make('Token Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('token')
                            ->label('Token')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('gateway_id')
                            ->label('Gateway')
                            ->badge(),
                        Infolists\Components\IconEntry::make('is_default')
                            ->label('Default Token')
                            ->boolean(),
                    ])->columns(3),

                Infolists\Components\Section::make('Owner Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('owner_type')
                            ->label('Owner Type')
                            ->formatStateUsing(fn ($state) => class_basename($state)),
                        Infolists\Components\TextEntry::make('owner_id')
                            ->label('Owner ID')
                            ->copyable(),
                    ])->columns(2),

                Infolists\Components\Section::make('Card Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_four')
                            ->label('Card Number')
                            ->formatStateUsing(fn ($state) => '**** **** **** ' . $state),
                        Infolists\Components\TextEntry::make('card_type')
                            ->label('Card Type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('expiry_month')
                            ->label('Expiry Month'),
                        Infolists\Components\TextEntry::make('expiry_year')
                            ->label('Expiry Year'),
                        Infolists\Components\TextEntry::make('citizen_id')
                            ->label('Citizen ID')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('isExpired')
                            ->label('Status')
                            ->formatStateUsing(fn ($record) => $record->isExpired() ? 'Expired' : 'Active')
                            ->badge()
                            ->color(fn ($record) => $record->isExpired() ? 'danger' : 'success'),
                    ])->columns(3),

                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('Additional Data')
                            ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                            ->copyable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime()
                            ->placeholder('-'),
                    ])->columns(3),
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
            'index' => Pages\ListOfficeGuyTokens::route('/'),
            'view' => Pages\ViewOfficeGuyToken::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $expiredCount = static::getModel()::query()
            ->where(function ($q) {
                $now = now();
                $q->where(function ($sq) use ($now) {
                    $sq->where('expiry_year', '<', $now->year);
                })->orWhere(function ($sq) use ($now) {
                    $sq->where('expiry_year', '=', $now->year)
                       ->where('expiry_month', '<', $now->month);
                });
            })
            ->count();

        return $expiredCount > 0 ? (string) $expiredCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
