<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Events\SubscriptionCharged;
use OfficeGuy\LaravelSumitGateway\Events\SubscriptionChargesFailed;
use OfficeGuy\LaravelSumitGateway\Events\SubscriptionCreated;
use OfficeGuy\LaravelSumitGateway\Events\SubscriptionCancelled;

/**
 * Subscription Service
 *
 * Port of OfficeGuySubscriptions.php from WooCommerce plugin.
 * Handles subscription creation, management, and recurring charges.
 */
class SubscriptionService
{
    /**
     * Ensure subscriptions are enabled.
     *
     * @throws \RuntimeException if subscriptions are disabled
     */
    protected static function ensureEnabled(): void
    {
        if (!config('officeguy.subscriptions.enabled', true)) {
            throw new \RuntimeException(__('Subscriptions are disabled'));
        }
    }

    /**
     * Create a new subscription
     *
     * @param mixed $subscriber User/Customer model
     * @param string $name Subscription name
     * @param float $amount Amount per charge
     * @param string $currency Currency code
     * @param int $intervalMonths Interval between charges in months
     * @param int|null $totalCycles Total number of cycles (null = unlimited)
     * @param int|null $tokenId Payment method token ID
     * @param array $metadata Additional metadata
     * @return Subscription
     */
    public static function create(
        mixed $subscriber,
        string $name,
        float $amount,
        string $currency = 'ILS',
        int $intervalMonths = 1,
        ?int $totalCycles = null,
        ?int $tokenId = null,
        array $metadata = []
    ): Subscription {
        self::ensureEnabled();

        $subscription = Subscription::create([
            'subscriber_type' => get_class($subscriber),
            'subscriber_id' => $subscriber->getKey(),
            'name' => $name,
            'amount' => $amount,
            'currency' => $currency,
            'interval_months' => $intervalMonths,
            'total_cycles' => $totalCycles,
            'payment_method_token' => $tokenId,
            'status' => Subscription::STATUS_PENDING,
            'next_charge_at' => now(),
            'metadata' => $metadata,
        ]);

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }

    /**
     * Create subscription from a product with subscription metadata
     *
     * @param mixed $subscriber User/Customer model
     * @param array $product Product data with subscription settings
     * @param int|null $tokenId Payment token
     * @return Subscription|null
     */
    public static function createFromProduct(
        mixed $subscriber,
        array $product,
        ?int $tokenId = null
    ): ?Subscription {
        // Check if product is a subscription product
        $isSubscription = $product['is_subscription'] ?? $product['OfficeGuySubscription'] ?? false;

        if (!$isSubscription) {
            return null;
        }

        $name = $product['name'] ?? __('Subscription');
        $amount = (float) ($product['price'] ?? $product['unit_price'] ?? 0);
        $currency = $product['currency'] ?? config('officeguy.default_currency', 'ILS');
        $intervalMonths = (int) ($product['interval_months'] ?? $product['_duration_in_months'] ?? 1);
        $totalCycles = isset($product['total_cycles']) || isset($product['_recurrences'])
            ? (int) ($product['total_cycles'] ?? $product['_recurrences'])
            : null;

        return self::create(
            $subscriber,
            $name,
            $amount,
            $currency,
            $intervalMonths,
            $totalCycles,
            $tokenId,
            ['product_id' => $product['id'] ?? $product['product_id'] ?? null]
        );
    }

    /**
     * Process initial subscription charge
     *
     * @param Subscription $subscription
     * @param int $paymentsCount Number of installments
     * @return array
     */
    public static function processInitialCharge(
        Subscription $subscription,
        int $paymentsCount = 1
    ): array {
        $result = PaymentService::processCharge(
            $subscription,
            $paymentsCount,
            recurring: true,
            redirectMode: false,
            token: $subscription->paymentToken()
        );

        if ($result['success']) {
            // Store recurring ID and activate
            $recurringId = $result['response']['Data']['RecurringID'] 
                ?? $result['response']['Data']['Payment']['RecurringID'] 
                ?? null;

            $subscription->recurring_id = $recurringId;
            $subscription->activate();
            $subscription->recordCharge($recurringId);

            event(new SubscriptionCharged($subscription, $result['payment']));
        } else {
            $subscription->markAsFailed();
            event(new SubscriptionChargesFailed($subscription, $result['message'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * Process recurring charge for a subscription
     *
     * @param Subscription $subscription
     * @return array
     */
    public static function processRecurringCharge(Subscription $subscription): array
    {
        self::ensureEnabled();

        if (!$subscription->canBeCharged()) {
            return [
                'success' => false,
                'message' => __('Subscription cannot be charged'),
            ];
        }

        if (!$subscription->recurring_id) {
            return [
                'success' => false,
                'message' => __('No recurring ID found for subscription'),
            ];
        }

        // Use SUMIT recurring charge API
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'RecurringPaymentID' => $subscription->recurring_id,
            'Items' => PaymentService::getPaymentOrderItems($subscription),
            'VATIncluded' => 'true',
            'SendDocumentByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
            'DocumentDescription' => __('Subscription payment') . ': ' . $subscription->name,
            'DocumentLanguage' => PaymentService::getOrderLanguage(),
        ];

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post($request, '/billing/recurring/charge/', $environment, false);

        if (!$response) {
            event(new SubscriptionChargesFailed($subscription, 'No response from gateway'));
            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . __('No response'),
            ];
        }

        $status = $response['Status'] ?? null;
        $payment = $response['Data']['Payment'] ?? null;

        if ($status === 0 && $payment && ($payment['ValidPayment'] ?? false) === true) {
            $subscription->recordCharge();

            event(new SubscriptionCharged($subscription, $payment));

            return [
                'success' => true,
                'payment' => $payment,
                'response' => $response,
            ];
        }

        // Failure
        $message = $status !== 0
            ? ($response['UserErrorMessage'] ?? 'Gateway error')
            : ($payment['StatusDescription'] ?? 'Declined');

        event(new SubscriptionChargesFailed($subscription, $message));

        return [
            'success' => false,
            'message' => __('Payment failed') . ' - ' . $message,
            'response' => $response,
        ];
    }

    /**
     * Process all due subscriptions
     *
     * @return array Results of all charges
     */
    public static function processDueSubscriptions(): array
    {
        self::ensureEnabled();

        $results = [];
        $dueSubscriptions = Subscription::due()->get();

        foreach ($dueSubscriptions as $subscription) {
            $result = self::processRecurringCharge($subscription);
            $results[$subscription->id] = $result;
        }

        return $results;
    }

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @param string|null $reason
     * @return void
     */
    public static function cancel(Subscription $subscription, ?string $reason = null): void
    {
        self::ensureEnabled();

        $subscription->cancel($reason);
        event(new SubscriptionCancelled($subscription, $reason));
    }

    /**
     * Check if cart/order contains subscription products
     * Port of: CartContainsOfficeGuySubscription() from OfficeGuySubscriptions.php
     *
     * @param array $items Line items to check
     * @return bool
     */
    public static function containsSubscriptionProducts(array $items): bool
    {
        foreach ($items as $item) {
            if (self::isSubscriptionProduct($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if item is a subscription product
     *
     * @param array $item
     * @return bool
     */
    public static function isSubscriptionProduct(array $item): bool
    {
        return ($item['is_subscription'] ?? false) 
            || ($item['OfficeGuySubscription'] ?? false) === 'yes'
            || ($item['OfficeGuySubscription'] ?? false) === true;
    }

    /**
     * Get subscription interval description
     * Port of: GetMonthsString($Months) from OfficeGuySubscriptions.php
     *
     * @param int $months
     * @return string
     */
    public static function getIntervalDescription(int $months): string
    {
        if ($months === 1) {
            return __('Month');
        } elseif ($months === 2) {
            return __('2 months');
        } elseif ($months === 6) {
            return __('6 months');
        } elseif ($months % 12 === 0) {
            $years = $months / 12;
            if ($years === 1) {
                return __('Year');
            } elseif ($years === 2) {
                return __('2 Years');
            }
            return $years . ' ' . __('Years');
        }

        return $months . ' ' . __('months');
    }

    /**
     * Fetch subscriptions from SUMIT API for a customer
     *
     * @param int $sumitCustomerId SUMIT customer ID
     * @param bool $includeInactive Include inactive subscriptions
     * @return array List of recurring items from SUMIT
     */
    public static function fetchFromSumit(int $sumitCustomerId, bool $includeInactive = false): array
    {
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'Customer' => [
                'ID' => $sumitCustomerId,
            ],
            'IncludeInactive' => $includeInactive,
        ];

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post($request, '/billing/recurring/listforcustomer/', $environment, false);

        if (!$response || ($response['Status'] ?? null) !== 0) {
            return [];
        }

        return $response['Data']['RecurringItems'] ?? [];
    }

    /**
     * Sync subscriptions from SUMIT API to local database
     *
     * @param mixed $subscriber User/Customer model with sumit_customer_id
     * @param bool $includeInactive Include inactive subscriptions
     * @return int Number of subscriptions synced
     */
    public static function syncFromSumit(mixed $subscriber, bool $includeInactive = false): int
    {
        // Get SUMIT customer ID from subscriber
        $sumitCustomerId = $subscriber->sumit_customer_id ?? null;

        if (!$sumitCustomerId) {
            return 0;
        }

        $sumitItems = self::fetchFromSumit((int) $sumitCustomerId, $includeInactive);
        $syncedCount = 0;

        foreach ($sumitItems as $item) {
            $recurringId = (string) ($item['ID'] ?? '');

            if (!$recurringId) {
                continue;
            }

            // Map SUMIT status to our status
            $status = match ((int) ($item['Status'] ?? -1)) {
                0 => Subscription::STATUS_ACTIVE,
                1 => Subscription::STATUS_PAUSED,
                2 => Subscription::STATUS_CANCELLED,
                3 => Subscription::STATUS_EXPIRED,
                default => Subscription::STATUS_PENDING,
            };

            // Calculate interval from billing dates (default to 1 month)
            $intervalMonths = 1;

            // Extract item details
            $itemData = $item['Item'] ?? [];
            $name = $itemData['Name'] ?? __('Subscription');
            $unitPrice = (float) ($item['UnitPrice'] ?? 0);
            $quantity = (int) ($item['Quantity'] ?? 1);
            $amount = $unitPrice * $quantity;

            // Parse dates
            $nextChargeAt = isset($item['Date_NextBilling'])
                ? \Carbon\Carbon::parse($item['Date_NextBilling'])
                : null;
            $lastChargedAt = isset($item['Date_PreviousBilling'])
                ? \Carbon\Carbon::parse($item['Date_PreviousBilling'])
                : null;

            // Update or create subscription
            Subscription::updateOrCreate(
                [
                    'subscriber_type' => get_class($subscriber),
                    'subscriber_id' => $subscriber->getKey(),
                    'recurring_id' => $recurringId,
                ],
                [
                    'name' => $name,
                    'amount' => $amount,
                    'currency' => 'ILS', // SUMIT default
                    'interval_months' => $intervalMonths,
                    'status' => $status,
                    'next_charge_at' => $nextChargeAt,
                    'last_charged_at' => $lastChargedAt,
                    'metadata' => [
                        'sumit_item_id' => $itemData['ID'] ?? null,
                        'sumit_sku' => $itemData['SKU'] ?? null,
                        'sumit_description' => $itemData['Description'] ?? null,
                        'sumit_quantity' => $quantity,
                        'sumit_unit_price' => $unitPrice,
                        'date_start' => $item['Date_Start'] ?? null,
                        'date_last' => $item['Date_Last'] ?? null,
                    ],
                ]
            );

            $syncedCount++;
        }

        return $syncedCount;
    }
}
