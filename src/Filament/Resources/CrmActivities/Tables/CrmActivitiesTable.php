<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\CrmActivities\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class CrmActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('activity_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'note' => 'info',
                        'call' => 'success',
                        'email' => 'primary',
                        'meeting' => 'warning',
                        'task' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->limit(50),

                Tables\Columns\TextColumn::make('entity.entity_name')
                    ->label('Related To')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('activity_date')
                    ->label('Activity Date')
                    ->dateTime()
                    ->sortable()
                    ->since(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('activity_type')
                    ->label('Type')
                    ->options([
                        'note' => 'Note',
                        'call' => 'Call',
                        'email' => 'Email',
                        'meeting' => 'Meeting',
                        'task' => 'Task',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('crm_entity_id')
                    ->label('Related Entity')
                    ->relationship('entity', 'entity_name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('activity_date', 'desc')
            ->deferFilters(false);
    }
}
