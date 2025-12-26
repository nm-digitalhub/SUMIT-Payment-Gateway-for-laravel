<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\TransactionResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\DocumentResource;
use OfficeGuy\LaravelSumitGateway\Filament\Resources\SubscriptionResource;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Schemas;
use Filament\Schemas\Components\Utilities\Get;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use App\Models\Client;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_document')
                ->label('צפה במסמך')
                ->icon('heroicon-o-document-text')
                ->visible(fn ($record) => !empty($record->document_id))
                ->url(fn ($record) => DocumentResource::getUrl('view', ['record' => $record->document_id]))
                ->openUrlInNewTab(),
            
            Actions\Action::make('refresh_status')
                ->label('רענן סטטוס')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function ($record) {
                    // TODO: Implement status refresh via API
                    Notification::make()
                        ->title('עדכון סטטוס טרם יושם')
                        ->info()
                        ->send();
                }),

            Actions\Action::make('open_subscription')
                ->label('פתח מנוי')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn ($record) => $record->subscription_id && Subscription::find($record->subscription_id))
                ->url(function ($record) {
                    $sub = Subscription::find($record->subscription_id);
                    return $sub ? SubscriptionResource::getUrl('view', ['record' => $sub->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('open_client')
                ->label('פתח לקוח')
                ->icon('heroicon-o-user')
                ->color('primary')
                ->visible(function ($record) {
                    return Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->exists();
                })
                ->url(function ($record) {
                    $client = Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->first();
                    return $client ? route('filament.admin.resources.clients.view', ['record' => $client->id]) : null;
                })
                ->openUrlInNewTab(),

            Actions\Action::make('process_refund')
                ->label('זיכוי כספי')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn ($record) => $record->status === 'completed' && $record->amount > 0)
                ->form([
                    Schemas\Components\Section::make('סוג הזיכוי')
                        ->description('בחר את סוג הזיכוי שברצונך לבצע')
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Radio::make('refund_type')
                                ->label('סוג זיכוי')
                                ->options(fn ($record) => [
                                    'full_refund' => 'החזר כספי מלא לכרטיס אשראי (₪' . number_format((float) ($record->amount ?? 0), 2) . ')',
                                    'partial_refund' => 'החזר כספי חלקי לכרטיס אשראי',
                                    'credit_note' => 'תעודת זיכוי חשבונאית (ללא החזר פיזי)',
                                ])
                                ->default('full_refund')
                                ->required()
                                ->live(),

                            Forms\Components\TextInput::make('amount')
                                ->label('סכום זיכוי')
                                ->numeric()
                                ->prefix('₪')
                                ->minValue(0.01)
                                ->maxValue(fn ($get, $record) => (float) $record->amount)
                                ->default(fn ($record) => (float) $record->amount)
                                ->visible(fn (Get $get) => $get('refund_type') !== 'full_refund')
                                ->required(fn (Get $get) => $get('refund_type') !== 'full_refund')
                                ->helperText(fn ($record) => 'סכום מקורי: ₪' . number_format((float) $record->amount, 2)),

                            Forms\Components\Textarea::make('reason')
                                ->label('סיבת זיכוי')
                                ->default('החזר כספי ללקוח')
                                ->required()
                                ->rows(2),
                        ]),
                ])
                ->requiresConfirmation()
                ->modalHeading('זיכוי כספי')
                ->modalDescription(fn ($record) => 'זיכוי עבור תשלום #' . $record->id . ' - מזהה תשלום: ' . $record->payment_id)
                ->modalSubmitActionLabel('בצע זיכוי')
                ->action(function ($record, array $data) {
                    \Log::info('Refund action started', [
                        'record_id' => $record->id,
                        'customer_id' => $record->customer_id,
                        'data' => $data,
                    ]);

                    $client = Client::query()
                        ->where('sumit_customer_id', $record->customer_id)
                        ->first();

                    if (!$client) {
                        \Log::warning('Client not found', ['customer_id' => $record->customer_id]);
                        Notification::make()
                            ->title('שגיאה')
                            ->body('לקוח לא נמצא במערכת')
                            ->danger()
                            ->send();
                        return;
                    }

                    $refundType = $data['refund_type'];
                    $amount = (float) ($refundType === 'full_refund' ? $record->amount : $data['amount']);
                    $reason = $data['reason'];

                    \Log::info('Processing refund', [
                        'type' => $refundType,
                        'amount' => $amount,
                        'reason' => $reason,
                    ]);

                    try {
                        if ($refundType === 'credit_note') {
                            // תעודת זיכוי חשבונאית
                            $result = DocumentService::createCreditNote(
                                $client,
                                $amount,
                                $reason,
                                $record->document_id
                            );
                        } else {
                            // החזר כספי פיזי
                            $result = PaymentService::processRefund(
                                $client,
                                $record->payment_id,
                                $amount,
                                $reason
                            );
                        }

                        \Log::info('Refund result', ['result' => $result]);

                        if (isset($result['success']) && $result['success'] === true) {
                            // ProcessRefund already updated the original transaction status
                            // and created the refund transaction record with proper links

                            $refundRecordId = $result['refund_record']->id ?? null;
                            $refundUrl = $refundRecordId
                                ? route('filament.admin.resources.transactions.view', ['record' => $refundRecordId])
                                : null;

                            Notification::make()
                                ->title('זיכוי בוצע בהצלחה')
                                ->body(match($refundType) {
                                    'credit_note' => 'תעודת זיכוי נוצרה: ' . ($result['document_id'] ?? 'N/A'),
                                    default => 'החזר כספי בוצע: ₪' . number_format($amount, 2) .
                                        ($refundRecordId
                                            ? ' | מזהה עסקה: ' . $refundRecordId
                                            : ''
                                        ) .
                                        ($result['transaction_id']
                                            ? ' | מזהה SUMIT: ' . $result['transaction_id']
                                            : ''
                                        ),
                                })
                                ->success()
                                ->persistent()
                                ->actions($refundUrl ? [
                                    \Filament\Notifications\Actions\Action::make('view_refund')
                                        ->label('צפה בעסקת הזיכוי')
                                        ->url($refundUrl)
                                        ->openUrlInNewTab(),
                                ] : [])
                                ->send();

                            // Refresh the current record to show updated status
                            $record->refresh();
                        } else {
                            \Log::error('Refund failed', ['error' => $result['error'] ?? 'Unknown']);
                            Notification::make()
                                ->title('שגיאה בביצוע זיכוי')
                                ->body($result['error'] ?? 'שגיאה לא ידועה')
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        \Log::error('Refund exception', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        Notification::make()
                            ->title('שגיאה')
                            ->body('שגיאה בביצוע הזיכוי: ' . $e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
