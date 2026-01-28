<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\SubscriptionCreated;
use OfficeGuy\LaravelSumitGateway\Notifications\SubscriptionCreatedNotification;

class NotifySubscriptionCreatedListener
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionCreated $event): void
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
        $notifiable->notify(new SubscriptionCreatedNotification(
            subscription: $event->subscription,
            response: $event->response
        ));
    }

    /**
     * Determine who should be notified.
     */
    protected function getNotifiable(SubscriptionCreated $event): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        // Try to get user from subscription
        if ($event->subscription->user) {
            return $event->subscription->user;
        }

        // Try to get user from authenticated session
        if (auth()->check()) {
            return auth()->user();
        }

        return null;
    }
}
