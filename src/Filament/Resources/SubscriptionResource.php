<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource\Pages;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static \UnitEnum|string|null $navigationGroup = 'SUMIT Gateway';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Subscription Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Subscription Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->prefix(fn ($record) => $record?->currency ?? '')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Billing Cycle')
                    ->schema([
                        Forms\Components\TextInput::make('interval_months')
                            ->label('Interval (Months)')
                            ->disabled(),
                        Forms\Components\TextInput::make('total_cycles')
                            ->label('Total Cycles')
                            ->placeholder('Unlimited')
                            ->disabled(),
                        Forms\Components\TextInput::make('completed_cycles')
                            ->label('Completed Cycles')
                            ->disabled(),
                        Forms\Components\TextInput::make('recurring_id')
                            ->label('SUMIT Recurring ID')
                            ->disabled(),
                    ])->columns(4),

                Forms\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('next_charge_at')
                            ->label('Next Charge')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_charged_at')
                            ->label('Last Charged')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires')
                            ->disabled(),
                    ])->columns(4),

                Forms\Components\Section::make('Subscriber Information')
                    ->schema([
                        Forms\Components\TextInput::make('subscriber_type')
                            ->label('Subscriber Type')
                            ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '-')
                            ->disabled(),
                        Forms\Components\TextInput::make('subscriber_id')
                            ->label('Subscriber ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('payment_method_token')
                            ->label('Payment Token ID')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Cancellation')
                    ->schema([
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->label('Cancelled At')
                            ->disabled(),
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->disabled()
                            ->rows(2),
                    ])->columns(2)
                    ->visible(fn ($record) => $record?->cancelled_at !== null),

                Forms\Components\Section::make('Metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Data')
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending', 'paused' => 'warning',
                        'cancelled', 'failed', 'expired' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'paused' => 'heroicon-o-pause-circle',
                        'cancelled' => 'heroicon-o-x-circle',
                        'failed' => 'heroicon-o-exclamation-circle',
                        'expired' => 'heroicon-o-calendar',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('interval_months')
                    ->label('Interval')
                    ->formatStateUsing(fn ($record) => $record->getIntervalDescription())
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_cycles')
                    ->label('Cycles')
                    ->formatStateUsing(fn ($record) => 
                        $record->completed_cycles . '/' . ($record->total_cycles ?? '∞')
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_charge_at')
                    ->label('Next Charge')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recurring_id')
                    ->label('Recurring ID')
                    ->searchable()
                    ->toggleable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                        'failed' => 'Failed',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('due_for_charge')
                    ->label('Due for Charge')
                    ->query(fn ($query) => $query->due()),
                Tables\Filters\Filter::make('amount')
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->numeric()
                            ->label('Minimum Amount'),
                        Forms\Components\TextInput::make('amount_to')
                            ->numeric()
                            ->label('Maximum Amount'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn ($query, $amount) => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn ($query, $amount) => $query->where('amount', '<=', $amount),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) =>
                        config('officeguy.subscriptions.enabled', true) &&
                        $record->status === Subscription::STATUS_PENDING
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->activate();
                        Notification::make()
                            ->title('Subscription activated')
                            ->success()
                            ->send();
                    }),
                Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) =>
                        config('officeguy.subscriptions.enabled', true) &&
                        $record->isActive()
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->pause();
                        Notification::make()
                            ->title('Subscription paused')
                            ->success()
                            ->send();
                    }),
                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn ($record) =>
                        config('officeguy.subscriptions.enabled', true) &&
                        $record->isPaused()
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->resume();
                        Notification::make()
                            ->title('Subscription resumed')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn ($record) =>
                        config('officeguy.subscriptions.enabled', true) &&
                        in_array($record->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAUSED])
                    )
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->action(function ($record, array $data) {
                        SubscriptionService::cancel($record, $data['reason'] ?? null);
                        Notification::make()
                            ->title('Subscription cancelled')
                            ->success()
                            ->send();
                    }),
                Action::make('charge_now')
                    ->label('Charge Now')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('primary')
                    ->visible(fn ($record) =>
                        config('officeguy.subscriptions.enabled', true) &&
                        $record->isActive() &&
                        $record->recurring_id
                    )
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $result = SubscriptionService::processRecurringCharge($record);

                        if ($result['success']) {
                            Notification::make()
                                ->title('Charge successful')
                                ->body('Payment processed successfully')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Charge failed')
                                ->body($result['message'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('cancel_selected')
                        ->label('Cancel Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (in_array($record->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAUSED])) {
                                    $record->cancel('Bulk cancellation');
                                }
                            }
                            Notification::make()
                                ->title('Subscriptions cancelled')
                                ->success()
                                ->send();
                        }),
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
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
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
        $dueCount = static::getModel()::due()->count();
        return $dueCount > 0 ? (string) $dueCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
