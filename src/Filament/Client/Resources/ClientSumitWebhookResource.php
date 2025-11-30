<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;
use OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSumitWebhookResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class ClientSumitWebhookResource extends Resource
{
    protected static ?string $model = SumitWebhook::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'SUMIT Webhooks (נכנסים)';

    protected static \UnitEnum|string|null $navigationGroup = 'תשלומים';

    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Show only webhooks related to the current user
        if (auth()->check()) {
            $query->where(function ($q) {
                // Match by customer email or ID in payload
                $q->whereJsonContains('payload->customer->email', auth()->user()->email)
                  ->orWhereJsonContains('payload->customer_id', (string) auth()->id())
                  ->orWhereJsonContains('payload->customer_id', auth()->id());
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('פרטי Webhook')
                    ->schema([
                        Forms\Components\TextInput::make('event_type')
                            ->label('סוג אירוע')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->label('סטטוס עיבוד')
                            ->disabled(),
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('מזהה טרנזקציה')
                            ->disabled(),
                        Forms\Components\TextInput::make('subscription_id')
                            ->label('מזהה מנוי')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Payload')
                    ->schema([
                        Forms\Components\Textarea::make('payload')
                            ->label('נתונים')
                            ->rows(15)
                            ->disabled()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state),
                    ]),

                Forms\Components\Section::make('אימות')
                    ->schema([
                        Forms\Components\TextInput::make('signature_verified')
                            ->label('חתימה מאומתת')
                            ->formatStateUsing(fn ($state) => $state ? 'כן' : 'לא')
                            ->disabled(),
                        Forms\Components\TextInput::make('processed_at')
                            ->label('עובד בתאריך')
                            ->disabled(),
                        Forms\Components\TextInput::make('created_at')
                            ->label('התקבל בתאריך')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('שגיאות')
                    ->visible(fn ($record) => !empty($record?->error_message))
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label('הודעת שגיאה')
                            ->rows(5)
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('סוג אירוע')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'transaction.completed' => 'תשלום הושלם',
                        'transaction.failed' => 'תשלום נכשל',
                        'subscription.created' => 'מנוי נוצר',
                        'subscription.renewed' => 'מנוי חודש',
                        'subscription.cancelled' => 'מנוי בוטל',
                        'subscription.expired' => 'מנוי פג',
                        'refund.processed' => 'החזר בוצע',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'processed' => 'עובד',
                        'pending' => 'ממתין',
                        'failed' => 'נכשל',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('signature_verified')
                    ->label('מאומת')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('מזהה')
                    ->limit(20)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('התקבל')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('עובד')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options([
                        'processed' => 'עובד',
                        'pending' => 'ממתין',
                        'failed' => 'נכשל',
                    ]),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('סוג אירוע')
                    ->options([
                        'transaction.completed' => 'תשלום הושלם',
                        'transaction.failed' => 'תשלום נכשל',
                        'subscription.created' => 'מנוי נוצר',
                        'subscription.renewed' => 'מנוי חודש',
                        'subscription.cancelled' => 'מנוי בוטל',
                        'subscription.expired' => 'מנוי פג',
                        'refund.processed' => 'החזר בוצע',
                    ]),

                Tables\Filters\TernaryFilter::make('signature_verified')
                    ->label('חתימה מאומתת')
                    ->placeholder('הכל')
                    ->trueLabel('מאומת')
                    ->falseLabel('לא מאומת'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('צפייה'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('אין SUMIT Webhooks')
            ->emptyStateDescription('לא נמצאו webhooks נכנסים מ-SUMIT');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientSumitWebhooks::route('/'),
            'view' => Pages\ViewClientSumitWebhook::route('/{record}'),
        ];
    }
}
