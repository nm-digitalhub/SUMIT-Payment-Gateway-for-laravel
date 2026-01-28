<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

use OfficeGuy\LaravelSumitGateway\Models\OrderSuccessToken;

/**
 * Success Page Accessed Event
 *
 * Dispatched when a user successfully accesses the success page.
 * Used for analytics and tracking ONLY - NOT for provisioning.
 *
 * CRITICAL: This event is for UI/analytics purposes only!
 * - Do NOT trigger provisioning in response to this event
 * - Provisioning is done via PaymentConfirmed event (from webhook)
 * - This event fires AFTER validation passes (token consumed)
 *
 * Use cases:
 * - Analytics tracking (Google Analytics, Mixpanel, etc.)
 * - User experience metrics
 * - Conversion tracking
 * - Session recording
 *
 * Usage in controller:
 * ```php
 * event(new SuccessPageAccessed($payable, $token));
 * ```
 */
class SuccessPageAccessed
{
    /**
     * @param  object  $payable  The payable entity (Order, Invoice, etc.)
     * @param  OrderSuccessToken  $token  The consumed success token
     */
    public function __construct(
        public object $payable,
        public OrderSuccessToken $token
    ) {}

    /**
     * Get analytics data for tracking
     */
    public function getAnalyticsData(): array
    {
        return [
            'payable_id' => $this->payable->getKey(),
            'payable_type' => $this->payable::class,
            'token_consumed_at' => $this->token->consumed_at?->toIso8601String(),
            'token_created_at' => $this->token->created_at->toIso8601String(),
            'time_to_access_seconds' => $this->token->consumed_at?->diffInSeconds($this->token->created_at),
        ];
    }
}
