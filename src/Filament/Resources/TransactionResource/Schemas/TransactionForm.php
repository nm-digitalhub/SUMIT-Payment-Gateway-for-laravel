<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Schemas;

use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
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
}
