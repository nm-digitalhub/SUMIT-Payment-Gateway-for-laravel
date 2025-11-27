<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;

/**
 * PayableMappingsTableWidget
 *
 * Displays all existing Payable field mappings with actions to view, edit, and delete.
 * Shown at the bottom of the OfficeGuySettings page.
 */
class PayableMappingsTableWidget extends BaseWidget
{
    /**
     * Widget heading.
     */
    protected static ?string $heading = 'מיפויי Payable קיימים';

    /**
     * Widget column span (full width).
     */
    protected int | string | array $columnSpan = 'full';

    /**
     * Configure the table.
     *
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(PayableFieldMapping::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('תווית')
                    ->searchable()
                    ->sortable()
                    ->default('—')
                    ->icon('heroicon-o-tag')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('model_class')
                    ->label('מחלקת מודל')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->description(fn ($record) => $record->model_class)
                    ->copyable()
                    ->copyMessage('הועתק!')
                    ->copyMessageDuration(1500)
                    ->tooltip('לחץ להעתקת המחלקה המלאה'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('פעיל')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('mapped_fields_count')
                    ->label('שדות ממופים')
                    ->state(fn ($record) => count(array_filter($record->field_mappings ?? [])))
                    ->badge()
                    ->color('info')
                    ->suffix(' / 16')
                    ->icon('heroicon-o-arrows-right-left'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('נוצר ב')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->icon('heroicon-o-clock'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('עודכן ב')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('צפה')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn ($record) => "מיפוי: {$record->label}")
                    ->modalDescription(fn ($record) => $record->model_class)
                    ->modalContent(fn ($record) => view('officeguy::components.mapping-details', [
                        'mapping' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('סגור')
                    ->modalWidth('5xl'),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'השבת' : 'הפעל')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->is_active ? 'השבתת מיפוי' : 'הפעלת מיפוי')
                    ->modalDescription(fn ($record) =>
                        $record->is_active
                            ? 'המיפוי יושבת ולא ישמש עבור יצירת Payable wrappers'
                            : 'המיפוי יופעל וישמש עבור יצירת Payable wrappers'
                    )
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'המיפוי הופעל' : 'המיפוי הושבת')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('מחק')
                    ->modalHeading('מחיקת מיפוי')
                    ->modalDescription('האם אתה בטוח שברצונך למחוק את המיפוי? פעולה זו בלתי הפיכה.')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('המיפוי נמחק')
                            ->body('המיפוי נמחק בהצלחה מהמערכת')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('הפעל נבחרים')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);

                            Notification::make()
                                ->title('המיפויים הופעלו')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('השבת נבחרים')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);

                            Notification::make()
                                ->title('המיפויים הושבתו')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('מחיקת מיפויים')
                        ->modalDescription('האם אתה בטוח שברצונך למחוק את המיפויים הנבחרים? פעולה זו בלתי הפיכה.')
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('המיפויים נמחקו')
                                ->body('כל המיפויים הנבחרים נמחקו בהצלחה')
                        ),
                ]),
            ])
            ->emptyStateHeading('אין מיפויים קיימים')
            ->emptyStateDescription('צור מיפוי ראשון על ידי לחיצה על "הוסף מיפוי Payable חדש" למעלה')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
