<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\PaymentFailed;
use OfficeGuy\LaravelSumitGateway\Notifications\PaymentFailedNotification;

class NotifyPaymentFailedListener
{
    /**
     * Handle the event.
     */
    public function handle(PaymentFailed $event): void
    {
        // Skip if notifications are disabled
        if (! config('officeguy.enable_notifications', true)) {
            return;
        }

        // Determine who should receive the notification
        $notifiable = $this->getNotifiable($event);

        if (! $notifiable instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            return;
        }

        // Send the notification
        // PaymentFailed event may not carry `payment` or `payable` properties
        // (e.g. when fired from a recurring-billing path), so guard with property_exists.
        $notifiable->notify(new PaymentFailedNotification(
            orderId: $event->orderId,
            payment: property_exists($event, 'payment') ? $event->payment : [],
            response: $event->response ?? [],
            payable: property_exists($event, 'payable') ? $event->payable : null,
        ));
    }

    /**
     * Determine who should be notified.
     */
    protected function getNotifiable(PaymentFailed $event): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        // Try to get user from payable (if it has a user relationship)
        // Guard with property_exists — PaymentFailed does not always carry $payable.
        if (property_exists($event, 'payable') && $event->payable && method_exists($event->payable, 'user')) {
            return $event->payable->user;
        }

        // Try to get user from authenticated session
        if (auth()->check()) {
            return auth()->user();
        }

        return null;
    }
}
