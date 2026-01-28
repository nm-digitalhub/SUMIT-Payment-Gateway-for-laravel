<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class PaymentCompletedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string | int $orderId,
        public readonly array $payment,
        public readonly array $response,
        public readonly ?OfficeGuyTransaction $transaction = null,
        public readonly ?object $payable = null
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('officeguy::notifications.payment_completed.title'),
            'message' => __('officeguy::notifications.payment_completed.message', [
                'amount' => number_format($this->payment['amount'] ?? 0, 2),
                'order_id' => $this->orderId,
            ]),
            'icon' => 'heroicon-o-check-circle',
            'icon_color' => 'success',
            'data' => [
                'order_id' => $this->orderId,
                'transaction_id' => $this->transaction?->id,
                'amount' => $this->payment['amount'] ?? 0,
                'currency' => $this->payment['currency'] ?? 'ILS',
                'success' => $this->response['success'] ?? true,
            ],
            'actions' => [
                [
                    'label' => __('officeguy::notifications.payment_completed.view_transaction'),
                    'url' => $this->transaction instanceof \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction
                        ? route('filament.admin.resources.office-guy-transactions.view', $this->transaction)
                        : null,
                ],
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
