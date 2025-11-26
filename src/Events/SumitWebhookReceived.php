<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

/**
 * Event fired when a webhook is received from SUMIT.
 *
 * This event allows your application to handle incoming SUMIT webhooks
 * by creating listeners that respond to different types of card events.
 *
 * Example listener:
 *
 * ```php
 * use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
 *
 * class HandleSumitWebhook
 * {
 *     public function handle(SumitWebhookReceived $event)
 *     {
 *         $webhook = $event->webhook;
 *         
 *         switch ($webhook->event_type) {
 *             case 'card_created':
 *                 // Handle new card
 *                 break;
 *             case 'card_updated':
 *                 // Handle card update
 *                 break;
 *         }
 *     }
 * }
 * ```
 */
class SumitWebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The webhook that was received.
     */
    public SumitWebhook $webhook;

    /**
     * Create a new event instance.
     */
    public function __construct(SumitWebhook $webhook)
    {
        $this->webhook = $webhook;
    }

    /**
     * Get the event type.
     */
    public function getEventType(): string
    {
        return $this->webhook->event_type;
    }

    /**
     * Get the card type.
     */
    public function getCardType(): ?string
    {
        return $this->webhook->card_type;
    }

    /**
     * Get the webhook payload.
     */
    public function getPayload(): array
    {
        return $this->webhook->payload ?? [];
    }

    /**
     * Get a specific field from the payload.
     */
    public function getPayloadField(string $field, $default = null)
    {
        return $this->webhook->getPayloadField($field, $default);
    }

    /**
     * Check if this is a card created event.
     */
    public function isCardCreated(): bool
    {
        return $this->webhook->event_type === SumitWebhook::TYPE_CARD_CREATED;
    }

    /**
     * Check if this is a card updated event.
     */
    public function isCardUpdated(): bool
    {
        return $this->webhook->event_type === SumitWebhook::TYPE_CARD_UPDATED;
    }

    /**
     * Check if this is a card deleted event.
     */
    public function isCardDeleted(): bool
    {
        return $this->webhook->event_type === SumitWebhook::TYPE_CARD_DELETED;
    }

    /**
     * Check if this is a card archived event.
     */
    public function isCardArchived(): bool
    {
        return $this->webhook->event_type === SumitWebhook::TYPE_CARD_ARCHIVED;
    }
}
