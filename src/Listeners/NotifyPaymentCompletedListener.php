<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use Illuminate\Support\Facades\Notification;
use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Notifications\PaymentCompletedNotification;

class NotifyPaymentCompletedListener
{
    /**
     * Handle the event.
     */
    public function handle(PaymentCompleted $event): void
    {
        // Skip if notifications are disabled
        if (! config('officeguy.enable_notifications', true)) {
            return;
        }

        // Determine who should receive the notification
        $notifiable = $this->getNotifiable($event);

        if (! $notifiable) {
            return;
        }

        // Send the notification
        $notifiable->notify(new PaymentCompletedNotification(
            orderId: $event->orderId,
            payment: $event->payment,
            response: $event->response,
            transaction: $event->transaction,
            payable: $event->payable
        ));
    }

    /**
     * Determine who should be notified.
     *
     * @param PaymentCompleted $event
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function getNotifiable(PaymentCompleted $event): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        // Try to get user from transaction
        if ($event->transaction?->user) {
            return $event->transaction->user;
        }

        // Try to get user from payable (if it has a user relationship)
        if ($event->payable && method_exists($event->payable, 'user')) {
            return $event->payable->user;
        }

        // Try to get user from authenticated session
        if (auth()->check()) {
            return auth()->user();
        }

        return null;
    }
}
