<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages\CreateCrmEntity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages\EditCrmEntity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Pages\ListCrmEntities;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\RelationManagers\ActivitiesRelationManager;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Schemas\CrmEntityForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Tables\CrmEntitiesTable;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CrmEntityResource extends Resource
{
    protected static ?string $model = CrmEntity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT CRM';

    protected static ?string $navigationLabel = 'CRM Entities';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return CrmEntityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmEntitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmEntities::route('/'),
            'create' => CreateCrmEntity::route('/create'),
            'edit' => EditCrmEntity::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        // Can only create if there are folders synced
        return \OfficeGuy\LaravelSumitGateway\Models\CrmFolder::count() > 0;
    }
}
