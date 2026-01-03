<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // =========================
            // פרטי עסקה
            // =========================
            Section::make('פרטי עסקה')
                ->schema([
                    TextEntry::make('payment_id')
                        ->label('מזהה תשלום')
                        ->copyable()
                        ->icon('heroicon-o-credit-card'),

                    TextEntry::make('auth_number')
                        ->label('מספר אישור')
                        ->copyable()
                        ->icon('heroicon-o-check-badge'),

                    TextEntry::make('amount')
                        ->label('סכום')
                        ->money(fn ($record) => $record->currency ?: 'ILS')
                        ->icon('heroicon-o-banknotes'),

                    TextEntry::make('currency')
                        ->label('מטבע')
                        ->badge()
                        ->formatStateUsing(fn ($state) => match (strtoupper((string) $state)) {
                            '', '0', 'ILS' => '₪ ILS',
                            'USD' => '$ USD',
                            'EUR' => '€ EUR',
                            'GBP' => '£ GBP',
                            default => strtoupper((string) $state),
                        }),

                    TextEntry::make('status')
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
                        }),

                    TextEntry::make('payment_method')
                        ->label('אמצעי תשלום')
                        ->badge()
                        ->icon('heroicon-o-credit-card'),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // =========================
            // פרטי כרטיס
            // =========================
            Section::make('פרטי כרטיס')
                ->schema([
                    TextEntry::make('card_type')
                        ->label('סוג כרטיס')
                        ->badge()
                        ->icon('heroicon-o-credit-card'),

                    TextEntry::make('last_digits')
                        ->label('4 ספרות אחרונות')
                        ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-'),

                    TextEntry::make('expiration_month')
                        ->label('חודש תפוגה')
                        ->formatStateUsing(fn ($state, $record) =>
                            $state && $record->expiration_year
                                ? $state . '/' . $record->expiration_year
                                : ($state ?: '-')
                        ),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn ($record) => !empty($record->card_type) || !empty($record->last_digits)),

            // =========================
            // תשלומים
            // =========================
            Section::make('תשלומים')
                ->schema([
                    TextEntry::make('payments_count')
                        ->label('מספר תשלומים')
                        ->badge()
                        ->color('primary')
                        ->icon('heroicon-o-calculator'),

                    TextEntry::make('first_payment_amount')
                        ->label('תשלום ראשון')
                        ->money(fn ($record) => $record->currency ?: 'ILS'),

                    TextEntry::make('non_first_payment_amount')
                        ->label('תשלומים נוספים')
                        ->money(fn ($record) => $record->currency ?: 'ILS'),
                ])
                ->columns(3)
                ->columnSpanFull()
                ->visible(fn ($record) => !empty($record->payments_count) && $record->payments_count > 1),

            // =========================
            // מידע נוסף
            // =========================
            Section::make('מידע נוסף')
                ->schema([
                    TextEntry::make('document_id')
                        ->label('מזהה מסמך')
                        ->icon('heroicon-o-document-text')
                        ->copyable(),

                    TextEntry::make('customer_id')
                        ->label('מזהה לקוח')
                        ->icon('heroicon-o-user')
                        ->copyable(),

                    TextEntry::make('subscription_id')
                        ->label('מזהה מנוי')
                        ->icon('heroicon-o-arrow-path')
                        ->copyable()
                        ->visible(fn ($record) => !empty($record->subscription_id)),

                    TextEntry::make('vendor_id')
                        ->label('מזהה ספק')
                        ->icon('heroicon-o-building-storefront')
                        ->copyable()
                        ->visible(fn ($record) => !empty($record->vendor_id)),

                    TextEntry::make('environment')
                        ->label('סביבה')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'www' => 'success',
                            'dev' => 'warning',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'www' => 'ייצור (Production)',
                            'dev' => 'פיתוח (Development)',
                            default => $state,
                        }),

                    IconEntry::make('is_test')
                        ->label('מצב בדיקות')
                        ->boolean()
                        ->trueIcon('heroicon-o-beaker')
                        ->falseIcon('heroicon-o-check-circle')
                        ->trueColor('warning')
                        ->falseColor('success'),

                    IconEntry::make('is_donation')
                        ->label('תרומה')
                        ->boolean()
                        ->visible(fn ($record) => !empty($record->is_donation)),

                    IconEntry::make('is_upsell')
                        ->label('Upsell')
                        ->boolean()
                        ->visible(fn ($record) => !empty($record->is_upsell)),

                    TextEntry::make('status_description')
                        ->label('תיאור סטטוס')
                        ->icon('heroicon-o-information-circle')
                        ->columnSpanFull()
                        ->visible(fn ($record) => filled($record->status_description)),

                    TextEntry::make('error_message')
                        ->label('הודעת שגיאה')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn ($record) => filled($record->error_message)),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // =========================
            // קישור למסמך להורדה ⭐⭐⭐
            // =========================
            Section::make('מסמך להורדה')
                ->schema([
                    TextEntry::make('document_download_url')
                        ->label('קישור למסמך')
                        ->state(fn ($record) =>
                            data_get($record->raw_response, 'Data.DocumentDownloadURL')
                        )
                        ->url(fn ($state) => $state)
                        ->openUrlInNewTab()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn ($record) =>
                            filled(data_get($record->raw_response, 'Data.DocumentDownloadURL'))
                        ),
                ])
                ->columnSpanFull(),

            // =========================
            // נתוני API גולמיים
            // =========================
            Section::make('נתוני API גולמיים')
                ->schema([
                    ViewEntry::make('raw_request')
                        ->view('officeguy::filament.components.api-payload')
                        ->label('נתוני בקשה (Request)'),

                    ViewEntry::make('raw_response')
                        ->view('officeguy::filament.components.api-payload')
                        ->label('נתוני תגובה (Response)'),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),

            // =========================
            // השוואת Request ל-Response
            // =========================
            Section::make('השוואת Request ל-Response')
                ->schema([
                    ViewEntry::make('api_diff')
                        ->view('officeguy::filament.components.api-payload-diff')
                        ->label(null),
                ])
                ->collapsible()
                ->collapsed()
                ->description('השוואה מפורטת בין נתוני ה-Request לנתוני ה-Response')
                ->columnSpanFull(),
        ]);
    }
}