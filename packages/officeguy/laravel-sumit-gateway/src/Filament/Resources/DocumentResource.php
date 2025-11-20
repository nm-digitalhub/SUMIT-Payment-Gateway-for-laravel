<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource\Pages;

class DocumentResource extends Resource
{
    protected static ?string $model = OfficeGuyDocument::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Documents';

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
                        Forms\Components\TextInput::make('document_type')
                            ->label('Document Type')
                            ->formatStateUsing(fn ($record) => $record?->getDocumentTypeName())
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_id')
                            ->label('Customer ID')
                            ->disabled(),
                        Forms\Components\Checkbox::make('is_draft')
                            ->label('Is Draft')
                            ->disabled(),
                        Forms\Components\Checkbox::make('emailed')
                            ->label('Emailed to Customer')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Financial Details')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('language')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('order_id')
                            ->label('Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('order_type')
                            ->label('Order Type')
                            ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '-')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->disabled()
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Raw Response')
                    ->schema([
                        Forms\Components\KeyValue::make('raw_response')
                            ->label('API Response Data')
                            ->disabled(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('document_id')
                    ->label('Document ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
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
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_draft')
                    ->label('Draft')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('emailed')
                    ->label('Emailed')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('language')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_id')
                    ->label('Customer')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Document Type')
                    ->options([
                        '1' => 'Invoice',
                        '8' => 'Order',
                        'DonationReceipt' => 'Donation Receipt',
                    ]),
                Tables\Filters\TernaryFilter::make('is_draft')
                    ->label('Draft Documents'),
                Tables\Filters\TernaryFilter::make('emailed')
                    ->label('Emailed Documents'),
                Tables\Filters\SelectFilter::make('currency')
                    ->options([
                        'ILS' => 'ILS',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                    ])
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_draft', true)->count() ?: null;
    }
}
