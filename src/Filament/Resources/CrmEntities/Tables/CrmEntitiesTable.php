<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmEntities\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

class CrmEntitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sumit_entity_id')
                    ->label('Entity ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('entity_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn ($record) => $record->folder?->name),

                Tables\Columns\TextColumn::make('folder.name')
                    ->label('Folder')
                    ->badge()
                    ->color(fn ($record) => match ($record->folder?->entity_type) {
                        'contact' => 'success',
                        'lead' => 'warning',
                        'company' => 'info',
                        'deal' => 'primary',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('assigned.name')
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('activities_count')
                    ->label('Activities')
                    ->counts('activities')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('deleted_at')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record) => $record->deleted_at === null)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('crm_folder_id')
                    ->label('Folder')
                    ->relationship('folder', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('owner_user_id')
                    ->label('Owner')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\SelectFilter::make('assigned_user_id')
                    ->label('Assigned To')
                    ->relationship('assigned', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('sync_from_sumit')
                    ->label('Sync from SUMIT')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sync Entity from SUMIT')
                    ->modalDescription('This will fetch the latest data from SUMIT CRM and update this entity.')
                    ->action(function ($record) {
                        try {
                            $result = CrmDataService::syncEntityFromSumit((int) $record->sumit_entity_id);

                            Notification::make()
                                ->title('Entity synced successfully')
                                ->body("Updated: {$record->entity_name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => $record->sumit_entity_id !== null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc')
            ->deferFilters(false); // Instant filtering
    }
}
