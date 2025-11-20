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
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\OfficeGuyDocumentResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class OfficeGuyDocumentResource extends Resource
{
    protected static ?string $model = OfficeGuyDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'Document';

    protected static ?string $pluralModelLabel = 'Documents';

    protected static ?string $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Document Information')
                    ->schema([
                        Forms\Components\TextInput::make('document_id')
                            ->label('Document ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('order_id')
                            ->label('Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->disabled(),
                        Forms\Components\Select::make('document_type')
                            ->label('Document Type')
                            ->options([
                                '1' => 'Invoice',
                                '8' => 'Order',
                                'DonationReceipt' => 'Donation Receipt',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('language')
                            ->label('Language')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->label('Currency')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Document Details')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->disabled()
                            ->prefix(fn ($record) => $record?->currency ?? ''),
                        Forms\Components\Toggle::make('is_draft')
                            ->label('Draft')
                            ->disabled(),
                        Forms\Components\Toggle::make('emailed')
                            ->label('Emailed')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->disabled()
                            ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('document_id')
                    ->label('Document ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        '1' => 'Invoice',
                        '8' => 'Order',
                        'DonationReceipt' => 'Donation Receipt',
                        default => 'Document',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '1' => 'success',
                        '8' => 'info',
                        'DonationReceipt' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_draft')
                    ->label('Draft')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('emailed')
                    ->label('Emailed')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('language')
                    ->label('Language')
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
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Document Type')
                    ->options([
                        '1' => 'Invoice',
                        '8' => 'Order',
                        'DonationReceipt' => 'Donation Receipt',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn () => OfficeGuyDocument::query()
                        ->distinct()
                        ->pluck('currency', 'currency')
                        ->toArray()
                    )
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_draft')
                    ->label('Draft Status')
                    ->placeholder('All documents')
                    ->trueLabel('Draft only')
                    ->falseLabel('Final only'),
                Tables\Filters\TernaryFilter::make('emailed')
                    ->label('Email Status')
                    ->placeholder('All documents')
                    ->trueLabel('Emailed')
                    ->falseLabel('Not emailed'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function (OfficeGuyDocument $record) {
                        // Placeholder for PDF download logic
                        \Filament\Notifications\Notification::make()
                            ->title('PDF download not yet implemented')
                            ->body('This would download the document from SUMIT.')
                            ->warning()
                            ->send();
                    }),
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
                Infolists\Components\Section::make('Document Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('document_id')
                            ->label('Document ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('order_id')
                            ->label('Order ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('order_type')
                            ->label('Order Type'),
                        Infolists\Components\TextEntry::make('customer_id')
                            ->label('Customer ID')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('document_type')
                            ->label('Document Type')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                '1' => 'Invoice',
                                '8' => 'Order',
                                'DonationReceipt' => 'Donation Receipt',
                                default => 'Document',
                            })
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                '1' => 'success',
                                '8' => 'info',
                                'DonationReceipt' => 'warning',
                                default => 'gray',
                            }),
                    ])->columns(3),

                Infolists\Components\Section::make('Financial Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Amount')
                            ->money(fn ($record) => $record->currency),
                        Infolists\Components\TextEntry::make('currency')
                            ->label('Currency')
                            ->badge(),
                        Infolists\Components\TextEntry::make('language')
                            ->label('Language')
                            ->placeholder('-'),
                    ])->columns(3),

                Infolists\Components\Section::make('Document Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('is_draft')
                            ->label('Draft Document')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('emailed')
                            ->label('Email Sent')
                            ->boolean(),
                    ])->columns(2),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(fn ($record) => empty($record->description)),

                Infolists\Components\Section::make('Raw Response')
                    ->schema([
                        Infolists\Components\TextEntry::make('raw_response')
                            ->label('Response Data')
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
            'index' => Pages\ListOfficeGuyDocuments::route('/'),
            'view' => Pages\ViewOfficeGuyDocument::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_draft', true)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
