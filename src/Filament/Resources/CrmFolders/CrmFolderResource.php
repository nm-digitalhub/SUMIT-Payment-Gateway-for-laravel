<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages\CreateCrmFolder;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages\EditCrmFolder;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Pages\ListCrmFolders;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Schemas\CrmFolderForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmFolders\Tables\CrmFoldersTable;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CrmFolderResource extends Resource
{
    protected static ?string $model = CrmFolder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT CRM';

    protected static ?string $navigationLabel = 'CRM Folders';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CrmFolderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmFoldersTable::configure($table);
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
            'index' => ListCrmFolders::route('/'),
            'create' => CreateCrmFolder::route('/create'),
            'edit' => EditCrmFolder::route('/{record}/edit'),
        ];
    }
}
