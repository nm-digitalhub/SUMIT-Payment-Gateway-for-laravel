<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\Models\PendingCheckout;

/**
 * TemporaryStorageService
 *
 * DB-first temporary storage for checkout data before payment confirmation.
 *
 * CRITICAL RULES:
 * - Primary: Database storage (survives restarts, works with webhooks)
 * - Fallback: Session (only for edge cases - redirect, mobile)
 * - Auto-expiration via scheduled job
 *
 * Flow:
 * 1. User submits checkout → store(Intent, serviceData)
 * 2. Payment redirect/webhook → retrieve(payableId)
 * 3. Auto-cleanup expired records (scheduled job)
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.2.0
 */
class TemporaryStorageService
{
    /**
     * Default expiration time in hours
     */
    protected int $defaultExpirationHours = 2;

    /**
     * Store checkout intent + service data temporarily
     *
     * @param CheckoutIntent $intent Checkout context
     * @param array<string, mixed> $serviceData Service-specific data (WHOIS, cPanel, etc.)
     * @param Request|null $request HTTP request (for session/IP tracking)
     * @return PendingCheckout
     */
    public function store(
        CheckoutIntent $intent,
        array $serviceData = [],
        ?Request $request = null
    ): PendingCheckout {
        $expiresAt = $this->getExpirationTime();

        // Create or update pending checkout
        $pending = PendingCheckout::updateOrCreate(
            [
                'payable_type' => get_class($intent->payable),
                'payable_id' => $intent->payable->getPayableId(),
            ],
            [
                'customer_data' => $intent->customer->toArray(),
                'payment_preferences' => $intent->payment->toArray(),
                'service_data' => $serviceData,
                'session_id' => $request ? $request->session()->getId() : null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? $request->userAgent() : null,
                'expires_at' => $expiresAt,
            ]
        );

        // Optional: Also store in session as fallback
        if ($request) {
            $this->storeInSession($request, $intent, $serviceData);
        }

        return $pending;
    }

    /**
     * Retrieve checkout intent + service data for a payable
     *
     * @param Payable $payable The payable entity
     * @param Request|null $request HTTP request (for session fallback)
     * @return array{intent: CheckoutIntent, serviceData: array}|null
     */
    public function retrieve(Payable $payable, ?Request $request = null): ?array
    {
        // Try database first (primary)
        $pending = PendingCheckout::forPayable(get_class($payable), $payable->getPayableId())
            ->active()
            ->latest()
            ->first();

        if ($pending) {
            return [
                'intent' => $pending->toIntent($payable),
                'serviceData' => $pending->getServiceData(),
            ];
        }

        // Fallback: Try session (edge cases)
        if ($request) {
            return $this->retrieveFromSession($request, $payable);
        }

        return null;
    }

    /**
     * Cleanup expired pending checkouts
     *
     * This should be called by a scheduled job (e.g., hourly)
     *
     * @return int Number of deleted records
     */
    public function cleanup(): int
    {
        return PendingCheckout::expired()->delete();
    }

    /**
     * Delete pending checkout for a payable (after payment success)
     *
     * @param Payable $payable The payable entity
     * @return bool
     */
    public function delete(Payable $payable): bool
    {
        return PendingCheckout::forPayable(get_class($payable), $payable->getPayableId())
            ->delete() > 0;
    }

    /**
     * Get expiration time
     *
     * @return Carbon
     */
    protected function getExpirationTime(): Carbon
    {
        $hours = config('officeguy.pending_checkout_expiration_hours', $this->defaultExpirationHours);
        return now()->addHours($hours);
    }

    /**
     * Store in session as fallback (for redirect scenarios)
     *
     * @param Request $request
     * @param CheckoutIntent $intent
     * @param array<string, mixed> $serviceData
     * @return void
     */
    protected function storeInSession(Request $request, CheckoutIntent $intent, array $serviceData): void
    {
        $key = 'pending_checkout_' . $intent->payable->getPayableId();

        $request->session()->put($key, [
            'customer' => $intent->customer->toArray(),
            'payment' => $intent->payment->toArray(),
            'service_data' => $serviceData,
            'expires_at' => $this->getExpirationTime()->toDateTimeString(),
        ]);
    }

    /**
     * Retrieve from session (fallback)
     *
     * @param Request $request
     * @param Payable $payable
     * @return array{intent: CheckoutIntent, serviceData: array}|null
     */
    protected function retrieveFromSession(Request $request, Payable $payable): ?array
    {
        $key = 'pending_checkout_' . $payable->getPayableId();
        $data = $request->session()->get($key);

        if (!$data) {
            return null;
        }

        // Check expiration
        if (isset($data['expires_at']) && Carbon::parse($data['expires_at'])->isPast()) {
            $request->session()->forget($key);
            return null;
        }

        return [
            'intent' => CheckoutIntent::fromArray($data, $payable),
            'serviceData' => $data['service_data'] ?? [],
        ];
    }
}
