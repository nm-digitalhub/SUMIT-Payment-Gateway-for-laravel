<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities;

use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages\ListCrmActivities;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Pages\ViewCrmActivity;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas\CrmActivityForm;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Schemas\CrmActivityInfolist;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Tables\CrmActivitiesTable;
use OfficeGuy\LaravelSumitGateway\Models\CrmActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CrmActivityResource extends Resource
{
    protected static ?string $model = CrmActivity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT CRM';

    protected static ?string $navigationLabel = 'Activities';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CrmActivityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CrmActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CrmActivitiesTable::configure($table);
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
            'index' => ListCrmActivities::route('/'),
            'view' => ViewCrmActivity::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        // Activities are typically created through entities or automatically
        return false;
    }

    public static function canEdit($record): bool
    {
        // Activities are usually read-only
        return false;
    }
}
