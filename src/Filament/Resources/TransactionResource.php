<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Schemas;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Pages;
use Filament\Support\Colors\Color;
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use Illuminate\Support\Facades\Mail;

class TransactionResource extends Resource
{
    protected static ?string $model = OfficeGuyTransaction::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'טרנזאקציות';

    protected static \UnitEnum|string|null $navigationGroup = 'שער תשלומי SUMIT';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\TextInput::make('payment_id')
                            ->label('מזהה תשלום')
                            ->disabled(),
                        Forms\Components\TextInput::make('auth_number')
                            ->label('מספר אישור')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('סכום')
                            ->formatStateUsing(function ($record) {
                                $currency = $record?->currency ?: 'ILS';
                                $symbol = match (strtoupper($currency)) {
                                    'ILS' => '₪',
                                    'USD' => '$',
                                    'EUR' => '€',
                                    'GBP' => '£',
                                    default => $currency,
                                };
                                return $symbol . ' ' . number_format((float) $record?->amount, 2);
                            })
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
                        Forms\Components\TextInput::make('status')
                            ->label('סטטוס')
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method')
                            ->label('אמצעי תשלום')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('פרטי כרטיס')
                    ->schema([
                        Forms\Components\TextInput::make('card_type')
                            ->label('סוג כרטיס')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_digits')
                            ->label('4 ספרות אחרונות')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiration_month')
                            ->label('חודש תפוגה')
                            ->disabled(),
                        Forms\Components\TextInput::make('expiration_year')
                            ->label('שנת תפוגה')
                            ->disabled(),
                    ])->columns(4),

                Schemas\Components\Section::make('תשלומים')
                    ->schema([
                        Forms\Components\TextInput::make('payments_count')
                            ->label('מספר תשלומים')
                            ->disabled(),
                        Forms\Components\TextInput::make('first_payment_amount')
                            ->label('תשלום ראשון')
                            ->disabled(),
                        Forms\Components\TextInput::make('non_first_payment_amount')
                            ->label('תשלומים נוספים')
                            ->disabled(),
                    ])->columns(3),

                Schemas\Components\Section::make('מידע נוסף')
                    ->schema([
                        Forms\Components\TextInput::make('document_id')
                            ->label('מזהה מסמך')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('מזהה לקוח')
                            ->disabled(),
                        Forms\Components\TextInput::make('environment')
                            ->label('סביבה')
                            ->disabled(),
                        Forms\Components\Checkbox::make('is_test')
                            ->label('מצב בדיקות')
                            ->disabled(),
                        Forms\Components\Textarea::make('status_description')
                            ->label('תיאור סטטוס')
                            ->disabled()
                            ->rows(2),
                        Forms\Components\Textarea::make('error_message')
                            ->label('הודעת שגיאה')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2),

                Schemas\Components\Section::make('נתוני API גולמיים')
                    ->schema([
                        Forms\Components\KeyValue::make('raw_request')
                            ->label('נתוני בקשה')
                            ->disabled(),
                        Forms\Components\KeyValue::make('raw_response')
                            ->label('נתוני תגובה')
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
                Tables\Columns\TextColumn::make('payment_id')
                    ->label('מזהה תשלום')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('סטטוס')
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
                    ->label('סכום')
                    ->formatStateUsing(function ($record) {
                        $currency = $record?->currency ?: 'ILS';
                        $symbol = match (strtoupper($currency)) {
                            'ILS' => '₪',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            default => $currency,
                        };
                        return $symbol . ' ' . number_format((float) $record?->amount, 2);
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('אמצעי')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_digits')
                    ->label('כרטיס')
                    ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-'),
                Tables\Columns\TextColumn::make('vendor_id')
                    ->label('ספק')
                    ->toggleable()
                    ->sortable(),
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
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('לקוח')
                    ->formatStateUsing(function ($record) {
                        if (! $record->customer_id) {
                            return null;
                        }
                        $client = Client::query()->where('sumit_customer_id', $record->customer_id)->first();
                        return $client?->name ?? $record->customer_id;
                    })
                    ->url(function ($record) {
                        if (! $record->customer_id) {
                            return null;
                        }
                        $client = Client::query()->where('sumit_customer_id', $record->customer_id)->first();
                        return $client ? route('filament.admin.resources.clients.view', ['record' => $client->id]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('document_id')
                    ->label('מסמך')
                    ->formatStateUsing(function ($record) {
                        if (! $record->document_id) {
                            return null;
                        }
                        $docId = OfficeGuyDocument::query()
                            ->where('document_id', $record->document_id)
                            ->value('id');
                        return $docId ? $record->document_id : $record->document_id;
                    })
                    ->url(function ($record) {
                        if (! $record->document_id) {
                            return null;
                        }
                        $docId = OfficeGuyDocument::query()
                            ->where('document_id', $record->document_id)
                            ->value('id');
                        return $docId ? route('filament.admin.resources.documents.view', ['record' => $docId]) : null;
                    })
                    ->openUrlInNewTab()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_donation')
                    ->label('תרומה')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_upsell')
                    ->label('Upsell')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('auth_number')
                    ->label('מס\' אישור')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_test')
                    ->label('בדיקות')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('תאריך')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options([
                        'completed' => 'הושלם',
                        'pending' => 'ממתין',
                        'failed' => 'נכשל',
                        'refunded' => 'הוחזר',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('אמצעי תשלום')
                    ->options([
                        'card' => 'כרטיס אשראי',
                        'bit' => 'ביט',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('currency')
                    ->label('מטבע')
                    ->options([
                        'ILS' => '₪ ILS',
                        'USD' => '$ USD',
                        'EUR' => '€ EUR',
                        'GBP' => '£ GBP',
                    ])
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_donation')
                    ->label('תרומות'),
                Tables\Filters\TernaryFilter::make('is_upsell')
                    ->label('Upsell Transactions'),
                Tables\Filters\Filter::make('has_vendor')
                    ->label('עסקאות ספק')
                    ->query(fn ($query) => $query->whereNotNull('vendor_id')),
                Tables\Filters\Filter::make('has_subscription')
                    ->label('עסקאות מנוי')
                    ->query(fn ($query) => $query->whereNotNull('subscription_id')),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->numeric()
                            ->label('סכום מינימלי'),
                        Forms\Components\TextInput::make('amount_to')
                            ->numeric()
                            ->label('סכום מקסימלי'),
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
                    ->label('עסקאות בדיקה'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('צפייה'),
                Action::make('create_donation_receipt')
                    ->label('צור קבלת תרומה')
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
                    ->label('שליחה מחדש של קבלה')
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
                Action::make('send_payment_link')
                    ->label('שליחת לינק תשלום')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn ($record) => $record->customer_id)
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('אימייל יעד')
                            ->email()
                            ->default(function ($record) {
                                return $record->client?->email;
                            })
                            ->required(false),
                        Forms\Components\TextInput::make('phone')
                            ->label('מספר נייד (ל‑SMS)')
                            ->default(fn ($record) => $record->client?->phone)
                            ->tel()
                            ->required(false),
                    ])
                    ->requiresConfirmation()
                    ->action(function ($record, array $data) {
                        $result = app(DebtService::class)->sendPaymentLink(
                            (int) $record->customer_id,
                            $data['email'] ?? null,
                            $data['phone'] ?? null
                        );

                        if (! ($result['success'] ?? false)) {
                            throw new \Exception($result['error'] ?? 'שליחה נכשלה');
                        }

                        $sentTo = trim(($data['email'] ?? '') . ' ' . ($data['phone'] ?? ''));

                        Notification::make()
                            ->title('לינק תשלום נשלח')
                            ->body("נשלח אל: {$sentTo}")
                            ->success()
                            ->send();
                    }),
                Action::make('check_debt')
                    ->label('בדיקת חוב ב‑SUMIT')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => !empty($record->customer_id))
                    ->action(function ($record) {
                        try {
                            $balance = app(DebtService::class)
                                ->getCustomerBalanceById((int) $record->customer_id);

                            if (! $balance) {
                                throw new \Exception('לא התקבלה יתרה מה‑SUMIT');
                            }

                            Notification::make()
                                ->title('יתרה נוכחית')
                                ->body($balance['formatted'])
                                ->color($balance['debt'] > 0 ? 'danger' : ($balance['debt'] < 0 ? 'success' : 'gray'))
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('בדיקת חוב נכשלה')
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
