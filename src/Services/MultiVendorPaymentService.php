<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Models\VendorCredential;
use OfficeGuy\LaravelSumitGateway\Events\MultiVendorPaymentCompleted;
use OfficeGuy\LaravelSumitGateway\Events\MultiVendorPaymentFailed;

/**
 * Multi-Vendor Payment Service
 *
 * Port of OfficeGuyMultiVendor.php, OfficeGuyDokanMarketplace.php,
 * OfficeGuyWCFMMarketplace.php, and OfficeGuyWCVendorsMarketplace.php from WooCommerce plugin.
 *
 * Handles splitting orders by vendor and processing separate charges with vendor-specific credentials.
 */
class MultiVendorPaymentService
{
    /**
     * Vendor resolver callback
     * Should return vendor model/ID for a given product/item
     *
     * @var callable|null
     */
    protected static $vendorResolver = null;

    /**
     * Set the vendor resolver callback
     *
     * @param callable $resolver fn(array $item): mixed - Returns vendor for the item
     */
    public static function setVendorResolver(callable $resolver): void
    {
        self::$vendorResolver = $resolver;
    }

    /**
     * Get the vendor for an item
     *
     * @param array $item Line item data
     * @return mixed Vendor model or ID, or null if no vendor
     */
    public static function getVendorForItem(array $item): mixed
    {
        if (self::$vendorResolver) {
            return call_user_func(self::$vendorResolver, $item);
        }

        // Check if item has vendor_id
        return $item['vendor_id'] ?? $item['vendor'] ?? null;
    }

    /**
     * Get vendor credentials for an item
     *
     * @param array $item Line item data
     * @return VendorCredential|null
     */
    public static function getCredentialsForItem(array $item): ?VendorCredential
    {
        $vendor = self::getVendorForItem($item);

        if (!$vendor) {
            return null;
        }

        if ($vendor instanceof VendorCredential) {
            return $vendor;
        }

        // If vendor is a model with credentials relationship
        if (is_object($vendor) && method_exists($vendor, 'vendorCredential')) {
            return $vendor->vendorCredential;
        }

        // Look up by vendor
        return VendorCredential::forVendor($vendor);
    }

    /**
     * Group order items by vendor
     *
     * @param Payable $order
     * @return array Array of vendor_id => items
     */
    public static function groupItemsByVendor(Payable $order): array
    {
        $grouped = [
            'default' => [], // Items without vendor (use store credentials)
        ];

        foreach ($order->getLineItems() as $item) {
            $vendor = self::getVendorForItem($item);

            if ($vendor) {
                $vendorKey = is_object($vendor) ? get_class($vendor) . '_' . $vendor->getKey() : (string) $vendor;

                if (!isset($grouped[$vendorKey])) {
                    $grouped[$vendorKey] = [
                        'vendor' => $vendor,
                        'items' => [],
                    ];
                }
                $grouped[$vendorKey]['items'][] = $item;
            } else {
                $grouped['default'][] = $item;
            }
        }

        // Remove empty default group
        if (empty($grouped['default'])) {
            unset($grouped['default']);
        }

        return $grouped;
    }

    /**
     * Check if cart/order has items from multiple vendors
     * Port of: HasMultipleVendorsInCart() from OfficeGuyMultiVendor.php
     *
     * @param Payable $order
     * @return bool
     */
    public static function hasMultipleVendors(Payable $order): bool
    {
        $groups = self::groupItemsByVendor($order);
        return count($groups) > 1;
    }

    /**
     * Check if cart/order has any vendor items
     * Port of: HasVendorInCart() from OfficeGuyMultiVendor.php
     *
     * @param Payable $order
     * @return bool
     */
    public static function hasVendorItems(Payable $order): bool
    {
        foreach ($order->getLineItems() as $item) {
            if (self::getVendorForItem($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count unique vendors in cart/order
     * Port of: VendorsInCartCount() from marketplace classes
     *
     * @param Payable $order
     * @return int
     */
    public static function countVendors(Payable $order): int
    {
        $groups = self::groupItemsByVendor($order);
        return count($groups) - (isset($groups['default']) ? 1 : 0);
    }

    /**
     * Get all vendor credentials for items in the order
     *
     * @param Payable $order
     * @return array Array of product_id => VendorCredential
     */
    public static function getProductVendorCredentials(Payable $order): array
    {
        $credentials = [];

        foreach ($order->getLineItems() as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? null;
            if (!$productId) {
                continue;
            }

            $credential = self::getCredentialsForItem($item);
            if ($credential) {
                $credentials[$productId] = $credential;
            }
        }

        return $credentials;
    }

    /**
     * Process a multi-vendor charge, splitting by vendor
     *
     * @param Payable $order
     * @param int $paymentsCount
     * @param bool $redirectMode
     * @param array $extra Additional request overrides
     * @return array Results for each vendor charge
     */
    public static function processMultiVendorCharge(
        Payable $order,
        int $paymentsCount = 1,
        bool $redirectMode = false,
        array $extra = []
    ): array {
        $groups = self::groupItemsByVendor($order);
        $results = [];
        $allSuccess = true;

        foreach ($groups as $vendorKey => $groupData) {
            // For default items, use standard PaymentService
            if ($vendorKey === 'default') {
                $items = $groupData;
                $result = self::chargeVendorItems($order, $items, null, $paymentsCount, $redirectMode, $extra);
            } else {
                $items = $groupData['items'];
                $vendor = $groupData['vendor'];
                $credentials = self::getCredentialsForItem($items[0]);

                $result = self::chargeVendorItems($order, $items, $credentials, $paymentsCount, $redirectMode, $extra);
            }

            $results[$vendorKey] = $result;

            if (!$result['success']) {
                $allSuccess = false;
            }
        }

        // Fire events
        if ($allSuccess) {
            event(new MultiVendorPaymentCompleted($order->getPayableId(), $results));
        } else {
            event(new MultiVendorPaymentFailed($order->getPayableId(), $results));
        }

        return [
            'success' => $allSuccess,
            'vendor_results' => $results,
        ];
    }

    /**
     * Charge items for a specific vendor
     *
     * @param Payable $order Original order for customer data
     * @param array $items Items to charge
     * @param VendorCredential|null $credentials Vendor credentials (null = use store credentials)
     * @param int $paymentsCount
     * @param bool $redirectMode
     * @param array $extra
     * @return array
     */
    protected static function chargeVendorItems(
        Payable $order,
        array $items,
        ?VendorCredential $credentials,
        int $paymentsCount,
        bool $redirectMode,
        array $extra
    ): array {
        // Calculate total for these items
        $total = 0;
        $apiItems = [];

        foreach ($items as $item) {
            $unitPrice = round($item['unit_price'], 2);
            $quantity = $item['quantity'];
            $total += $unitPrice * $quantity;

            $apiItems[] = [
                'Item' => [
                    'ExternalIdentifier' => $item['variation_id'] ?? $item['product_id'],
                    'Name' => $item['name'],
                    'SKU' => $item['sku'] ?? '',
                    'SearchMode' => 'Automatic',
                ],
                'Quantity' => $quantity,
                'UnitPrice' => $unitPrice,
                'Currency' => $order->getPayableCurrency(),
            ];
        }

        // Use vendor credentials or default
        $requestCredentials = $credentials 
            ? $credentials->getCredentials() 
            : PaymentService::getCredentials();

        $request = [
            'Credentials' => $requestCredentials,
            'Items' => $apiItems,
            'VATIncluded' => 'true',
            'VATRate' => PaymentService::getOrderVatRate($order),
            'Customer' => PaymentService::getOrderCustomer($order),
            'AuthoriseOnly' => config('officeguy.authorize_only', false) ? 'true' : 'false',
            'DraftDocument' => config('officeguy.draft_document', false) ? 'true' : 'false',
            'SendDocumentByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
            'DocumentDescription' => __('Order number') . ': ' . $order->getPayableId(),
            'Payments_Count' => $paymentsCount,
            'MaximumPayments' => PaymentService::getMaximumPayments($total),
            'DocumentLanguage' => PaymentService::getOrderLanguage(),
        ];

        if ($credentials && $credentials->merchant_number) {
            $request['MerchantNumber'] = $credentials->merchant_number;
        }

        // Add payment method
        if (!$redirectMode) {
            $pciMode = config('officeguy.pci', 'no');
            if ($pciMode === 'yes') {
                $request['PaymentMethod'] = TokenService::getPaymentMethodPCI();
            } else {
                $request['PaymentMethod'] = [
                    'SingleUseToken' => \OfficeGuy\LaravelSumitGateway\Support\RequestHelpers::post('og-token'),
                    'Type' => 1,
                ];
            }
        }

        $request = array_merge($request, $extra);

        $environment = config('officeguy.environment', 'www');
        $endpoint = $redirectMode ? '/billing/payments/beginredirect/' : '/billing/payments/charge/';

        $response = OfficeGuyApi::post($request, $endpoint, $environment, !$redirectMode);

        // Handle redirect mode
        if ($redirectMode) {
            if ($response && isset($response['Data']['RedirectURL'])) {
                return [
                    'success' => true,
                    'redirect_url' => $response['Data']['RedirectURL'],
                    'response' => $response,
                ];
            }
            return [
                'success' => false,
                'message' => __('Something went wrong.'),
                'response' => $response,
            ];
        }

        // Handle charge response
        if (!$response) {
            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . __('No response'),
            ];
        }

        $status = $response['Status'] ?? null;
        $payment = $response['Data']['Payment'] ?? null;

        if ($status === 0 && $payment && ($payment['ValidPayment'] ?? false) === true) {
            // Create transaction record
            OfficeGuyTransaction::create([
                'order_id' => $order->getPayableId(),
                'payment_id' => $payment['ID'] ?? null,
                'document_id' => $response['Data']['DocumentID'] ?? null,
                'customer_id' => $response['Data']['CustomerID'] ?? null,
                'auth_number' => $payment['AuthNumber'] ?? null,
                'amount' => $payment['Amount'] ?? $total,
                'first_payment_amount' => $payment['FirstPaymentAmount'] ?? null,
                'non_first_payment_amount' => $payment['NonFirstPaymentAmount'] ?? null,
                'status' => 'completed',
                'status_description' => $payment['StatusDescription'] ?? null,
                'payment_method' => 'card',
                'last_digits' => $payment['PaymentMethod']['CreditCard_LastDigits'] ?? null,
                'expiration_month' => $payment['PaymentMethod']['CreditCard_ExpirationMonth'] ?? null,
                'expiration_year' => $payment['PaymentMethod']['CreditCard_ExpirationYear'] ?? null,
                'raw_request' => $request,
                'raw_response' => $response,
                'environment' => $environment,
                'is_test' => config('officeguy.testing', false),
                'vendor_id' => $credentials ? ($credentials->vendor_id ?? null) : null,
            ]);

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

        return [
            'success' => false,
            'message' => __('Payment failed') . ' - ' . $message,
            'response' => $response,
        ];
    }
}
