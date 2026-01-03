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
            // מידע נוסף (כמו בצילום)
            // =========================
            Section::make('מידע נוסף')
                ->schema([
                    TextEntry::make('document_id')
                        ->label('מזהה מסמך'),

                    TextEntry::make('customer_id')
                        ->label('מזהה לקוח'),

                    TextEntry::make('environment')
                        ->label('סביבה')
                        ->badge(),

                    IconEntry::make('is_test')
                        ->label('מצב בדיקות')
                        ->boolean(),

                    TextEntry::make('status_description')
                        ->label('תיאור סטטוס'),

                    TextEntry::make('error_message')
                        ->label('הודעת שגיאה')
                        ->visible(fn ($record) => filled($record->error_message)),
                ])
                ->columns(2)
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
                        ->label('Request'),

                    ViewEntry::make('raw_response')
                        ->view('officeguy::filament.components.api-payload')
                        ->label('Response'),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ]);
    }
}