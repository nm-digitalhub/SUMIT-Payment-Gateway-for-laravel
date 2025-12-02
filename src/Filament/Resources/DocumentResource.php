<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Schemas;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource\Pages;
use App\Models\Client;
use App\Models\Order;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DocumentResource extends Resource
{
    protected static ?string $model = OfficeGuyDocument::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'מסמכים';

    protected static \UnitEnum|string|null $navigationGroup = 'שער תשלומי SUMIT';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('פרטי מסמך')
                    ->schema([
                        Forms\Components\TextInput::make('document_id')
                            ->label('מזהה מסמך')
                            ->disabled(),
                        Forms\Components\TextInput::make('document_type')
                            ->label('סוג מסמך')
                            ->formatStateUsing(fn ($record) => $record?->getDocumentTypeName())
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('מזהה לקוח')
                            ->disabled(),
                        Forms\Components\Checkbox::make('is_draft')
                            ->label('טיוטה')
                            ->disabled(),
                        Forms\Components\Checkbox::make('emailed')
                            ->label('נשלח במייל ללקוח')
                            ->disabled(),
                    ])->columns(3),

                Schemas\Components\Section::make('פרטים כספיים')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('סכום')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->label('מטבע')
                            ->formatStateUsing(fn ($state) => match (strtoupper((string) $state)) {
                                '', '0', 'ILS' => '₪ ILS',
                                'USD' => '$ USD',
                                'EUR' => '€ EUR',
                                'GBP' => '£ GBP',
                                default => strtoupper((string) $state),
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('language')
                            ->label('שפה')
                            ->formatStateUsing(fn ($state) => match (strtolower((string) $state)) {
                                '', '0', 'he', 'he-il', 'he_il', 'heb', 'hebrew' => 'עברית',
                                'en', 'en-us', 'en_il' => 'English',
                                default => strtoupper((string) $state),
                            })
                            ->disabled(),
                    ])->columns(3),

                Schemas\Components\Section::make('פרטי הזמנה')
                    ->schema([
                        Forms\Components\TextInput::make('order_id')
                            ->label('מזהה הזמנה')
                            ->formatStateUsing(function ($record) {
                                $orderId = $record?->order_id;
                                if (empty($orderId) || $orderId === '0') {
                                    return '—';
                                }

                                // Try to resolve local Order model
                                $order = Order::find($orderId);
                                return $order?->order_number ?? $orderId;
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('order_type')
                            ->label('סוג הזמנה')
                            ->formatStateUsing(function ($state) {
                                if (empty($state) || $state === '0') {
                                    return 'לא זמין';
                                }
                                return class_basename($state);
                            })
                            ->disabled(),
                        Forms\Components\TextInput::make('subscription_id')
                            ->label('מנוי קשור')
                            ->formatStateUsing(function ($record) {
                                if (! $record?->subscription_id) {
                                    return null;
                                }

                                $sub = Subscription::find($record->subscription_id);
                                return $sub?->name ?? $record->subscription_id;
                            })
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('תיאור')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->disabled()
                            ->rows(3),
                    ]),

                Schemas\Components\Section::make('תגובת API גולמית')
                    ->schema([
                        Forms\Components\KeyValue::make('raw_response')
                            ->label('נתוני תגובה מה‑API')
                            ->disabled(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('מזהה')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('document_id')
                    ->label('מזהה מסמך')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('סוג מסמך')
                    ->formatStateUsing(fn ($record) => $record->getDocumentTypeName())
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->isInvoice() => 'success',
                        $record->isOrder() => 'info',
                        $record->isDonationReceipt() => 'warning',
                        default => 'secondary',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('סכום')
                    ->formatStateUsing(function ($record) {
                        $currency = $record->currency ?: 'ILS';
                        $symbol = match (strtoupper($currency)) {
                            'ILS' => '₪',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            default => $currency,
                        };

                        return $symbol . ' ' . number_format((float) $record->amount, 2);
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_draft')
                    ->label('טיוטה')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('emailed')
                    ->label('נשלח במייל')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('language')
                    ->label('שפה')
                    ->formatStateUsing(fn ($state) => match (strtolower((string) $state)) {
                        '', '0', 'he', 'he-il', 'he_il', 'heb', 'hebrew' => 'עברית',
                        'en', 'en-us', 'en_il' => 'English',
                        default => strtoupper((string) $state),
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('לקוח')
                    ->formatStateUsing(function ($record) {
                        // Try to resolve to local Client first (by sumit id stored on client)
                        $client = Client::query()
                            ->where('sumit_customer_id', $record->customer_id)
                            ->first();

                        if ($client) {
                            return $client->name;
                        }

                        return $record->customer_id;
                    })
                    ->description(function ($record) {
                        if (! $record->customer_id) {
                            return null;
                        }
                        $balance = Cache::remember(
                            'sumit_balance_' . $record->customer_id,
                            300,
                            fn () => app(DebtService::class)->getCustomerBalanceById((int) $record->customer_id)
                        );
                        return $balance['formatted'] ?? null;
                    })
                    ->url(function ($record) {
                        $client = Client::query()
                            ->where('sumit_customer_id', $record->customer_id)
                            ->first();

                        if ($client) {
                            return route('filament.admin.resources.clients.view', ['record' => $client->id]);
                        }

                        return null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('הזמנה')
                    ->formatStateUsing(function ($record) {
                        if (! $record->order_id) {
                            return null;
                        }
                        $order = Order::find($record->order_id);
                        return $order?->order_number ?? $record->order_id;
                    })
                    ->url(function ($record) {
                        if (! $record->order_id) {
                            return null;
                        }
                        $order = Order::find($record->order_id);
                        return $order ? route('filament.admin.resources.orders.view', ['record' => $order->id]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subscription_id')
                    ->label('מנוי')
                    ->formatStateUsing(function ($record) {
                        if (! $record->subscription_id) {
                            return null;
                        }
                        $sub = Subscription::find($record->subscription_id);
                        return $sub?->name ?? $record->subscription_id;
                    })
                    ->url(function ($record) {
                        if (! $record->subscription_id) {
                            return null;
                        }
                        $sub = Subscription::find($record->subscription_id);
                        return $sub ? route('filament.admin.resources.subscriptions.view', ['record' => $sub->id]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוצר')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('סוג מסמך')
                    ->options([
                        '1' => 'חשבונית',
                        '8' => 'הזמנה',
                        'DonationReceipt' => 'קבלת תרומה',
                    ]),
                Tables\Filters\TernaryFilter::make('is_draft')
                    ->label('מסמכי טיוטה'),
                Tables\Filters\TernaryFilter::make('emailed')
                    ->label('נשלחו במייל'),
                Tables\Filters\SelectFilter::make('currency')
                    ->label('מטבע')
                    ->options([
                        'ILS' => '₪ ILS',
                        'USD' => '$ USD',
                        'EUR' => '€ EUR',
                        'GBP' => '£ GBP',
                    ])
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('download_pdf')
                    ->label('הורדת PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn ($record) => !empty($record->document_download_url))
                    ->url(fn ($record) => $record->document_download_url)
                    ->openUrlInNewTab(),
                Action::make('resend_email')
                    ->label('שליחה חוזרת במייל')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->visible(fn ($record) => !$record->is_draft && !empty($record->customer_id))
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('אימייל (אופציונלי)')
                            ->email()
                            ->helperText('השאר ריק כדי לשלוח לאימייל הרשום ב‑SUMIT'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            // If no email provided, send null to use customer's SUMIT email
                            $email = !empty($data['email']) ? $data['email'] : null;

                            // Pass the full document model (required for DocumentType + DocumentNumber)
                            $result = \OfficeGuy\LaravelSumitGateway\Services\DocumentService::sendByEmail(
                                $record,
                                $email
                            );

                            if ($result['success'] ?? false) {
                                $message = $email
                                    ? 'המסמך נשלח אל ' . $email
                                    : 'המסמך נשלח לאימייל הלקוח הרשום ב‑SUMIT';

                                Notification::make()
                                    ->title('המסמך נשלח בהצלחה')
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception($result['error'] ?? 'Unknown error');
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('שליחת המסמך נכשלה')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
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
            'index' => Pages\ListDocuments::route('/'),
            'view' => Pages\ViewDocument::route('/{record}'),
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
        return static::getModel()::where('is_draft', true)->count() ?: null;
    }
}
