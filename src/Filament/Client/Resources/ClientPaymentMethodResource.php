<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Checkbox;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientPaymentMethodResource\Pages;

class ClientPaymentMethodResource extends Resource
{
    protected static ?string $model = OfficeGuyToken::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'My Payment Methods';

    protected static \BackedEnum|string|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Payment Method';

    protected static ?string $pluralModelLabel = 'Payment Methods';

    /**
     * מציג רק כרטיסים של המשתמש המחובר
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('owner_type', get_class(auth()->user()))
            ->where('owner_id', auth()->id());
    }

    /**
     * משמש רק להצגת דף View (לא Create)
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Card Information')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('card_type')
                        ->label('Card Type')
                        ->disabled(),

                    TextInput::make('last_four')
                        ->label('Card Number')
                        ->formatStateUsing(fn ($state) => '**** **** **** ' . $state)
                        ->disabled(),

                    Placeholder::make('expiry')
                        ->label('Expiry Date')
                        ->content(fn ($record) =>
                            $record?->expiry_month . '/' . $record?->expiry_year
                        ),

                    Checkbox::make('is_default')
                        ->label('Default Payment Method')
                        ->disabled(),
                ])
                ->columns(2),

            Section::make('Status')
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('status')
                        ->label('Card Status')
                        ->content(fn ($record) =>
                            $record?->isExpired() ? '⚠️ Expired' : '✓ Active'
                        ),

                    Placeholder::make('created_at')
                        ->label('Added On')
                        ->content(fn ($record) =>
                            $record?->created_at?->format('M d, Y')
                        ),
                ])
                ->columns(2),
        ]);
    }

    /**
     * טבלת אמצעי תשלום
     */
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
                ViewAction::make(),

                Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->visible(fn ($record) => !$record->is_default && !$record->isExpired())
                    ->requiresConfirmation()
                    ->action(function (OfficeGuyToken $record) {
                        $record->setAsDefault();

                        Notification::make()
                            ->title('Payment method set as default')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Payment Method')
                    ->modalDescription('Are you sure you want to delete this saved payment method?')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Payment method deleted')
                    ),
            ])
            ->emptyStateHeading('No saved payment methods')
            ->emptyStateDescription('You have not saved any payment methods yet. Save a card during checkout or via the add-card form.')
            ->emptyStateIcon('heroicon-o-credit-card')
            ->defaultSort('created_at', 'desc');
    }

    /**
     * עמודים המשויכים ל-Resource
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClientPaymentMethods::route('/'),
            'create' => Pages\CreateClientPaymentMethod::route('/create'),
            'view'   => Pages\ViewClientPaymentMethod::route('/{record}'),
        ];
    }

    /**
     * אין עריכת כרטיס קיים
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * מספר הכרטיסים שפג תוקפם
     */
    public static function getNavigationBadge(): ?string
    {
        $expiredCount = static::getEloquentQuery()
            ->get()
            ->filter(fn (OfficeGuyToken $token) => $token->isExpired())
            ->count();

        return $expiredCount > 0 ? (string) $expiredCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getNavigationBadge() ? 'warning' : null;
    }
}
