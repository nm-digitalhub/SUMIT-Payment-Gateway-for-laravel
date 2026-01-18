<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string|int $orderId,
        public readonly array $payment,
        public readonly array $response,
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
        $errorMessage = $this->response['error_message']
            ?? $this->response['UserErrorMessage']
            ?? __('officeguy::notifications.payment_failed.unknown_error');

        return [
            'title' => __('officeguy::notifications.payment_failed.title'),
            'message' => __('officeguy::notifications.payment_failed.message', [
                'amount' => number_format($this->payment['amount'] ?? 0, 2),
                'order_id' => $this->orderId,
                'error' => $errorMessage,
            ]),
            'icon' => 'heroicon-o-x-circle',
            'icon_color' => 'danger',
            'data' => [
                'order_id' => $this->orderId,
                'amount' => $this->payment['amount'] ?? 0,
                'currency' => $this->payment['currency'] ?? 'ILS',
                'error_message' => $errorMessage,
                'response' => $this->response,
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
