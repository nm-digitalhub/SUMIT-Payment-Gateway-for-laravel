<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\DocumentCreated;
use OfficeGuy\LaravelSumitGateway\Notifications\DocumentCreatedNotification;

class NotifyDocumentCreatedListener
{
    /**
     * Handle the event.
     */
    public function handle(DocumentCreated $event): void
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
        $notifiable->notify(new DocumentCreatedNotification(
            document: $event->document,
            response: $event->response
        ));
    }

    /**
     * Determine who should be notified.
     */
    protected function getNotifiable(DocumentCreated $event): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        // Try to get user from document's order (if it exists)
        if ($event->document->order && method_exists($event->document->order, 'user')) {
            return $event->document->order->user;
        }

        // Try to get user from transaction
        if ($event->document->transaction?->user) {
            return $event->document->transaction->user;
        }

        // Try to get user from authenticated session
        if (auth()->check()) {
            return auth()->user();
        }

        return null;
    }
}
