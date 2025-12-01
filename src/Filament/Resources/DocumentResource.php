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

class DocumentResource extends Resource
{
    protected static ?string $model = OfficeGuyDocument::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Documents';

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make('Document Information')
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

                Schemas\Components\Section::make('Financial Details')
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

                Schemas\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('order_id')
                            ->label('Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('order_type')
                            ->label('Order Type')
                            ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '-')
                            ->disabled(),
                    ])->columns(2),

                Schemas\Components\Section::make('Description')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->disabled()
                            ->rows(3),
                    ]),

                Schemas\Components\Section::make('Raw Response')
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
                ViewAction::make(),
                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn ($record) => !empty($record->document_download_url))
                    ->url(fn ($record) => $record->document_download_url)
                    ->openUrlInNewTab(),
                Action::make('resend_email')
                    ->label('Resend Email')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->visible(fn ($record) => !$record->is_draft && !empty($record->customer_id))
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address (Optional)')
                            ->email()
                            ->helperText('Leave empty to send to customer\'s registered email in SUMIT'),
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
                                    ? 'The document has been sent to ' . $email
                                    : 'The document has been sent to customer\'s registered email';

                                Notification::make()
                                    ->title('Document sent successfully')
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception($result['error'] ?? 'Unknown error');
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to send document')
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
