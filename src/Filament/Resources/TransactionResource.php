<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Pages;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Schemas\TransactionForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Tables\TransactionsTable;
use OfficeGuy\LaravelSumitGateway\Filament\Clusters\SumitGateway;

class TransactionResource extends Resource
{
    protected static ?string $model = OfficeGuyTransaction::class;

    protected static ?string $cluster = SumitGateway::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'טרנזאקציות';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
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
