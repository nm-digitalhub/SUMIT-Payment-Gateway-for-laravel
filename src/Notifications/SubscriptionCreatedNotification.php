<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;

class SubscriptionCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly array $response
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
            'title' => __('officeguy::notifications.subscription_created.title'),
            'message' => __('officeguy::notifications.subscription_created.message', [
                'amount' => number_format($this->subscription->amount, 2),
                'interval' => $this->subscription->interval,
            ]),
            'icon' => 'heroicon-o-arrow-path',
            'icon_color' => 'info',
            'data' => [
                'subscription_id' => $this->subscription->id,
                'sumit_subscription_id' => $this->subscription->sumit_subscription_id,
                'amount' => $this->subscription->amount,
                'currency' => $this->subscription->currency,
                'interval' => $this->subscription->interval,
                'status' => $this->subscription->status,
            ],
            'actions' => [
                [
                    'label' => __('officeguy::notifications.subscription_created.view_subscription'),
                    'url' => route('filament.admin.resources.subscriptions.view', $this->subscription),
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
