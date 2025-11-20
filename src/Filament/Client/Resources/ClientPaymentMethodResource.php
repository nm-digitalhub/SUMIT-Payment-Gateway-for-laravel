<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;
use Filament\Notifications\Notification;

class ClientPaymentMethodResource extends Resource
{
    protected static ?string $model = OfficeGuyToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'My Payment Methods';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Payment Method';

    protected static ?string $pluralModelLabel = 'Payment Methods';

    public static function getEloquentQuery(): Builder
    {
        // Filter to only show tokens for the authenticated user
        return parent::getEloquentQuery()
            ->where('owner_type', get_class(auth()->user()))
            ->where('owner_id', auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Card Information')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('Card Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_four')
                            ->label('Card Number')
                            ->formatStateUsing(fn ($state) => '**** **** **** ' . $state)
                            ->disabled(),
                        Forms\Components\TextInput::make('expiry_month')
                            ->label('Expiry Date')
                            ->formatStateUsing(fn ($record) => 
                                $record->expiry_month . '/' . $record->expiry_year
                            )
                            ->disabled(),
                        Forms\Components\Checkbox::make('is_default')
                            ->label('Default Payment Method')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Placeholder::make('status')
                            ->label('Card Status')
                            ->content(fn ($record) => 
                                $record->isExpired() ? '⚠️ Expired' : '✓ Active'
                            ),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Added On')
                            ->content(fn ($record) => $record->created_at->format('M d, Y')),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('card_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('last_four')
                    ->label('Card Number')
                    ->formatStateUsing(fn ($state) => '**** **** **** ' . $state),
                Tables\Columns\TextColumn::make('expiry_month')
                    ->label('Expires')
                    ->formatStateUsing(fn ($record) => 
                        $record->expiry_month . '/' . substr($record->expiry_year, -2)
                    )
                    ->badge()
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default Method'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->visible(fn ($record) => !$record->is_default && !$record->isExpired())
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->setAsDefault();
                        Notification::make()
                            ->title('Payment method set as default')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Payment Method')
                    ->modalDescription('Are you sure you want to delete this saved payment method? This action cannot be undone.')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Payment method deleted')
                            ->body('The payment method has been removed from your account.')
                    ),
            ])
            ->emptyStateHeading('No saved payment methods')
            ->emptyStateDescription('You have not saved any payment methods yet. Save a card during checkout to see it here.')
            ->emptyStateIcon('heroicon-o-credit-card')
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
            'index' => Pages\ListClientPaymentMethods::route('/'),
            'view' => Pages\ViewClientPaymentMethod::route('/{record}'),
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
        $expiredCount = static::getEloquentQuery()
            ->get()
            ->filter(fn ($token) => $token->isExpired())
            ->count();
        
        return $expiredCount > 0 ? (string)$expiredCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : null;
    }
}
