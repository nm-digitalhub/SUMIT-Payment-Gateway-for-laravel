<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Carbon\Carbon;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;

/**
 * Payment Service
 *
 * 1:1 port of OfficeGuyPayment.php from WooCommerce plugin
 * Handles payment processing, document creation, and order management
 */
class PaymentService
{
    /**
     * Get credentials array for API requests
     *
     * Port of: GetCredentials($Gateway)
     *
     * @return array
     */
    public static function getCredentials(): array
    {
        return [
            'CompanyID' => config('officeguy.company_id'),
            'APIKey' => config('officeguy.private_key'),
        ];
    }

    /**
     * Get maximum number of payments/installments allowed for an order value
     *
     * Port of: GetMaximumPayments($Gateway, $OrderValue)
     *
     * @param float $orderValue Order total amount
     * @return int Maximum number of installments
     */
    public static function getMaximumPayments(float $orderValue): int
    {
        $maximumPayments = (int)config('officeguy.max_payments', 1);

        $minAmountPerPayment = (float)config('officeguy.min_amount_per_payment', 0);
        if ($minAmountPerPayment > 0) {
            $maximumPayments = min($maximumPayments, (int)floor($orderValue / $minAmountPerPayment));
        }

        $minAmountForPayments = (float)config('officeguy.min_amount_for_payments', 0);
        if ($minAmountForPayments > 0 && round($orderValue) < round($minAmountForPayments)) {
            $maximumPayments = 1;
        }

        // Allow filtering via events/hooks
        // $maximumPayments = apply_filters('sumit_maximum_installments', $maximumPayments, $orderValue);

        return $maximumPayments;
    }

    /**
     * Get VAT rate from order
     *
     * Port of: GetOrderVatRate($Order)
     *
     * @param Payable $order Order instance
     * @return string VAT rate as string percentage
     */
    public static function getOrderVatRate(Payable $order): string
    {
        if (!$order->isTaxEnabled()) {
            return '';
        }

        $vatRate = $order->getVatRate();
        if ($vatRate === null) {
            return '0';
        }

        return (string)$vatRate;
    }

    /**
     * Get document language based on configuration
     *
     * Port of: GetOrderLanguage($Gateway)
     *
     * @return string Language code
     */
    public static function getOrderLanguage(): string
    {
        if (!config('officeguy.automatic_languages', true)) {
            return '';
        }

        $locale = app()->getLocale();

        return match ($locale) {
            'en', 'en_US' => 'English',
            'ar', 'ar_AR' => 'Arabic',
            'es', 'es_ES' => 'Spanish',
            'he', 'he_IL' => 'Hebrew',
            default => '',
        };
    }

    /**
     * Set / upsert a payment method for a SUMIT customer (also sets it כברירת מחדל ב-SUMIT).
     *
     * Endpoint: POST /billing/paymentmethods/setforcustomer/
     *
     * @param string|int $sumitCustomerId
     * @param string $token CreditCard_Token from SUMIT
     * @param array $method Additional fields (optional) from PaymentMethod schema
     * @return array{success: bool, error?: string}
     */
    public static function setPaymentMethodForCustomer(string|int $sumitCustomerId, string $token, array $method = []): array
    {
        try {
            // Build payload - use PaymentMethod with CreditCard_Token (for permanent tokens)
            // OR use SingleUseToken (for temporary tokens from Payments.JS)
            $payload = [
                'Credentials' => self::getCredentials(),
                'Customer' => [
                    'ID' => (int) $sumitCustomerId,
                ],
            ];

            // If token looks like a permanent token (UUID format), send as PaymentMethod
            // Otherwise, send as SingleUseToken
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
                // Permanent token - use PaymentMethod

                // Try to find token in database to get expiry dates
                $tokenModel = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken::where('token', $token)->first();

                $payload['PaymentMethod'] = array_merge([
                    'Type' => 1,  // CreditCard type as integer (per API examples)
                    'CreditCard_Token' => $token,
                    'CreditCard_ExpirationMonth' => $tokenModel ? (int) $tokenModel->expiry_month : null,
                    'CreditCard_ExpirationYear' => $tokenModel ? (int) $tokenModel->expiry_year : null,
                ], $method);
            } else {
                // Single-use token - use SingleUseToken field
                $payload['SingleUseToken'] = $token;
            }

            \Log::info('setPaymentMethodForCustomer payload', ['payload' => $payload]);

            $response = OfficeGuyApi::post(
                $payload,
                '/billing/paymentmethods/setforcustomer/',
                config('officeguy.environment', 'www'),
                false
            );

            \Log::info('setPaymentMethodForCustomer response', ['response' => $response]);

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to set payment method',
                ];
            }

            return ['success' => true];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * קבלת פירוט עסקה לפי PaymentID.
     * Endpoint: POST /billing/payments/get/
     *
     * @param int|string $paymentId
     * @return array{success: bool, payment?: array|null, error?: string}
     */
    public static function getPaymentDetails(int|string $paymentId): array
    {
        try {
            $payload = [
                'Credentials' => self::getCredentials(),
                'PaymentID' => (int) $paymentId,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/billing/payments/get/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to fetch payment details',
                ];
            }

            return [
                'success' => true,
                'payment' => $response['Data']['Payment'] ?? null,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List payments history (paged) with optional date/valid filters.
     * Endpoint: POST /billing/payments/list/
     *
     * @param array $filters [Date_From?, Date_To?, Valid?, StartIndex?]
     * @return array{success: bool, payments?: array<int, array>, has_next?: bool, error?: string}
     */
    public static function listPayments(array $filters = []): array
    {
        try {
            // ברירת מחדל: שנה אחורה ועד היום
            $payload = [
                'Credentials' => self::getCredentials(),
                'Date_From' => $filters['Date_From'] ?? Carbon::now()->subYear()->startOfDay()->toIso8601String(),
                'Date_To' => $filters['Date_To'] ?? Carbon::now()->endOfDay()->toIso8601String(),
                'Valid' => $filters['Valid'] ?? null,
                'StartIndex' => $filters['StartIndex'] ?? 0,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/billing/payments/list/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to list payments',
                ];
            }

            return [
                'success' => true,
                'payments' => $response['Data']['Payments'] ?? [],
                'has_next' => $response['Data']['HasNextPage'] ?? false,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch payment methods for a SUMIT customer.
     *
     * Endpoint: POST /billing/paymentmethods/getforcustomer/
     *
     * @param string|int $sumitCustomerId
     * @param bool $includeInactive
     * @return array{success: bool, payment_methods?: array<int, array>, active_method?: array|null, inactive_methods?: array<int, array>, error?: string}
     */
    public static function getPaymentMethodsForCustomer(string|int $sumitCustomerId, bool $includeInactive = false): array
    {
        try {
            $payload = [
                'Credentials' => self::getCredentials(),
                'Customer' => [
                    'ID' => (int) $sumitCustomerId,
                ],
                'IncludeInactive' => $includeInactive,
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/billing/paymentmethods/getforcustomer/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to fetch payment methods',
                ];
            }

            $data = $response['Data'] ?? [];

            $active = $data['PaymentMethod'] ?? null;
            $inactive = !empty($data['InactivePaymentMethods']) && is_array($data['InactivePaymentMethods'])
                ? $data['InactivePaymentMethods']
                : [];

            $methods = [];
            if ($active) {
                $methods[] = $active;
            }
            $methods = array_merge($methods, $inactive);

            return [
                'success' => true,
                'active_method' => $active,
                'inactive_methods' => $inactive,
                'payment_methods' => $methods,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove active payment method from customer in SUMIT.
     * Endpoint: POST /billing/paymentmethods/remove/
     *
     * @param string|int $sumitCustomerId SUMIT customer ID
     * @return array{success: bool, error?: string}
     */
    public static function removePaymentMethodForCustomer(string|int $sumitCustomerId): array
    {
        try {
            $payload = [
                'Credentials' => self::getCredentials(),
                'Customer' => [
                    'ID' => (int) $sumitCustomerId,
                ],
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/billing/paymentmethods/remove/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Failed to remove payment method',
                ];
            }

            return ['success' => true];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test a payment method with a minimal charge (₪1).
     * Useful for validating that a token is still active and working.
     *
     * @param string $token Payment token to test
     * @param string|int $sumitCustomerId SUMIT customer ID
     * @return array{success: bool, transaction_id?: string, error?: string}
     */
    public static function testPayment(string $token, string|int $sumitCustomerId): array
    {
        try {
            $payload = [
                'Credentials' => self::getCredentials(),
                'Customer' => [
                    'ID' => (int) $sumitCustomerId,
                ],
                'PaymentMethod' => [
                    'CreditCard_Token' => $token,
                    'Type' => 'CreditCard (1)',
                ],
                'Amount' => 1, // ₪1 test charge
                'Description' => 'Test payment - Token validation',
                'Cancelable' => true, // Allow cancellation
            ];

            $response = OfficeGuyApi::post(
                $payload,
                '/billing/payments/charge/',
                config('officeguy.environment', 'www'),
                false
            );

            if ($response === null || ($response['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $response['UserErrorMessage'] ?? 'Test payment failed',
                ];
            }

            return [
                'success' => true,
                'transaction_id' => $response['Data']['ID'] ?? null,
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get customer data array from order
     *
     * Port of: GetOrderCustomer($Gateway, $Order)
     *
     * @param Payable $order Order instance
     * @param string|null $citizenId Optional citizen ID from request
     * @return array Customer data for API
     */
    public static function getOrderCustomer(Payable $order, ?string $citizenId = null): array
    {
        $customerName = $order->getCustomerName();
        $company = $order->getCustomerCompany();

        if (!empty($company)) {
            $customerName = $company . ' - ' . $customerName;
        }

        if (empty(trim($customerName))) {
            $customerName = __('Guest');
        }

        $address = $order->getCustomerAddress();
        $vatRate = self::getOrderVatRate($order);

        // Get merge_customers setting from SettingsService (respects Admin Panel)
        $settingsService = app(SettingsService::class);
        $mergeCustomers = (bool) $settingsService->get('merge_customers', false);

        // Check if customer already exists in SUMIT (via Client model)
        // If client has sumit_customer_id, return ONLY the CustomerID (not full Customer object)
        // This prevents SUMIT from creating duplicate customers
        $sumitCustomerId = null;
        if ($order instanceof \Illuminate\Database\Eloquent\Model && method_exists($order, 'client')) {
            $client = $order->client;
            if ($client && !empty($client->sumit_customer_id)) {
                $sumitCustomerId = $client->sumit_customer_id;
            }
        }

        // If customer exists in SUMIT, return ONLY CustomerID
        if ($sumitCustomerId) {
            return ['ID' => (int) $sumitCustomerId];
        }

        // Otherwise, send full Customer object for new customer creation
        // SUMIT supports searching by multiple parameters:
        // - EmailAddress (primary search key)
        // - Phone (secondary search key)
        // - ExternalIdentifier (tertiary search key)
        // - Name (for matching)
        // SearchMode 'Automatic' tells SUMIT to search by these parameters
        $customer = [
            'Name' => $customerName,
            'EmailAddress' => $order->getCustomerEmail(),
            'Phone' => $order->getCustomerPhone(),
            'SearchMode' => $mergeCustomers ? 'Automatic' : 'None',
        ];

        // Add ExternalIdentifier for additional matching (if available)
        // This helps SUMIT match existing customers even without sumit_customer_id
        if ($order->getCustomerId()) {
            $customer['ExternalIdentifier'] = (string) $order->getCustomerId();
        }

        if ($address) {
            $customer['Address'] = $address['address'] ?? '';
            if (!empty($address['address2'])) {
                $customer['Address'] = trim($customer['Address'] . ', ' . $address['address2']);
            }

            $customer['City'] = $address['city'] ?? '';
            if (!empty($address['state'])) {
                $customer['City'] = empty($customer['City'])
                    ? $address['state']
                    : $customer['City'] . ', ' . $address['state'];
            }

            if (!empty($address['country']) && $address['country'] !== 'IL') {
                $customer['City'] = empty($customer['City'])
                    ? $address['country']
                    : $customer['City'] . ', ' . $address['country'];
            }

            $customer['ZipCode'] = $address['zip_code'] ?? '';
        }

        if ($citizenId) {
            $customer['CompanyNumber'] = $citizenId;
        }

        if ($vatRate === '0') {
            $customer['NoVAT'] = true;
        } elseif ($vatRate !== '') {
            $customer['NoVAT'] = false;
        }

        // Allow filtering via events
        // $customer = apply_filters('sumit_customer_fields', $customer, $order);

        return $customer;
    }

    /**
     * Check if currency is supported
     *
     * Port of: IsCurrencySupported()
     *
     * @param string $currency Currency code
     * @return bool
     */
    public static function isCurrencySupported(string $currency): bool
    {
        return in_array($currency, config('officeguy.supported_currencies', []));
    }

    /**
     * Get payment order items array from order
     *
     * Port of: GetPaymentOrderItems($Order)
     *
     * @param Payable $order Order instance
     * @return array Items array for API request
     */
    public static function getPaymentOrderItems(Payable $order): array
    {
        $items = [];
        $total = 0;

        // Add line items
        foreach ($order->getLineItems() as $lineItem) {
            $unitPrice = round($lineItem['unit_price'], 2);
            $quantity = $lineItem['quantity'];

            $item = [
                'Item' => [
                    'ExternalIdentifier' => $lineItem['variation_id'] ?: $lineItem['product_id'],
                    'Name' => $lineItem['name'],
                    'SKU' => $lineItem['sku'] ?? '',
                    'SearchMode' => 'Automatic',
                ],
                'Quantity' => $quantity,
                'UnitPrice' => $unitPrice,
                'Currency' => $order->getPayableCurrency(),
                'Duration_Days' => '0',
                'Duration_Months' => '0',
                'Recurrence' => '0',
            ];

            // Allow filtering via events
            // $item = apply_filters('sumit_item_fields', $item, $lineItem, $order);

            $items[] = $item;
            $total += $unitPrice * $quantity;
        }

        // Add fees
        foreach ($order->getFees() as $fee) {
            $items[] = [
                'Item' => [
                    'Name' => $fee['name'],
                    'SearchMode' => 'Automatic',
                ],
                'UnitPrice' => round($fee['amount'], 2),
                'Currency' => $order->getPayableCurrency(),
            ];
            $total += $fee['amount'];
        }

        // Add shipping
        $shippingAmount = $order->getShippingAmount();
        $shippingMethod = $order->getShippingMethod();
        if ($shippingAmount > 0 && !empty($shippingMethod)) {
            $items[] = [
                'Item' => [
                    'Name' => $shippingMethod,
                    'SearchMode' => 'Automatic',
                ],
                'Quantity' => 1,
                'UnitPrice' => round($shippingAmount, 2),
                'Currency' => $order->getPayableCurrency(),
            ];
            $total += round($shippingAmount, 2);
        }

        // Add missing amount adjustment if needed
        $missingAmount = round($order->getPayableAmount() - $total, 2);
        if ($missingAmount != 0) {
            $missingAmountName = $missingAmount < 0
                ? __('General credit')
                : __('General');

            $items[] = [
                'Item' => [
                    'Name' => $missingAmountName,
                    'SearchMode' => 'Automatic',
                ],
                'Quantity' => 1,
                'UnitPrice' => $missingAmount,
                'Currency' => $order->getPayableCurrency(),
            ];
        }

        return $items;
    }

    /**
     * Get document order items array (for invoice/receipt creation)
     *
     * Port of: GetDocumentOrderItems($Order)
     *
     * @param Payable $order Order instance
     * @return array Items array for document API request
     */
    public static function getDocumentOrderItems(Payable $order): array
    {
        $items = [];
        $total = 0;

        // Add line items
        foreach ($order->getLineItems() as $lineItem) {
            $unitPrice = round($lineItem['unit_price'], 2);
            $quantity = $lineItem['quantity'];

            $itemDetails = [
                'ExternalIdentifier' => $lineItem['variation_id'] ?: $lineItem['product_id'],
                'Name' => $lineItem['name'],
                'SKU' => $lineItem['sku'] ?? '',
                'SearchMode' => 'Automatic',
            ];

            // Allow filtering
            // $itemDetails = apply_filters('sumit_item_fields', $itemDetails, $lineItem, $order);

            $items[] = [
                'Item' => $itemDetails,
                'Quantity' => $quantity,
                'DocumentCurrency_UnitPrice' => $unitPrice,
            ];

            $total += $quantity * $unitPrice;
        }

        // Add fees
        foreach ($order->getFees() as $fee) {
            $items[] = [
                'Item' => [
                    'Name' => $fee['name'],
                    'SearchMode' => 'Automatic',
                ],
                'DocumentCurrency_UnitPrice' => round($fee['amount'], 2),
            ];
            $total += $fee['amount'];
        }

        // Add shipping
        $shippingAmount = $order->getShippingAmount();
        $shippingMethod = $order->getShippingMethod();
        if ($shippingAmount > 0) {
            $items[] = [
                'Item' => [
                    'Name' => $shippingMethod ?? __('Shipping'),
                    'SearchMode' => 'Automatic',
                ],
                'DocumentCurrency_UnitPrice' => round($shippingAmount, 2),
            ];
            $total += round($shippingAmount, 2);
        }

        // Add missing amount adjustment
        $missingAmount = round($order->getPayableAmount() - $total, 2);
        if ($missingAmount != 0) {
            $missingAmountName = $missingAmount < 0
                ? __('General credit')
                : __('General');

            $items[] = [
                'Item' => [
                    'Name' => $missingAmountName,
                    'SearchMode' => 'Automatic',
                ],
                'Quantity' => 1,
                'DocumentCurrency_UnitPrice' => $missingAmount,
                'Currency' => $order->getPayableCurrency(),
            ];
        }

        return $items;
    }

    /**
     * Build charge request for card/redirect payments.
     * Mirrors GetOrderRequest logic from the Woo plugin.
     *
     * @param Payable $order
     * @param int $paymentsCount
     * @param bool $recurring
     * @param bool $redirectMode
     * @param OfficeGuyToken|null $token
     * @param array $extra Additional request overrides
     * @return array
     */
    public static function buildChargeRequest(
        Payable $order,
        int $paymentsCount = 1,
        bool $recurring = false,
        bool $redirectMode = false,
        ?OfficeGuyToken $token = null,
        array $extra = []
    ): array {
        $orderTotal = round($order->getPayableAmount(), 2);

        $authorizeOnly = config('officeguy.authorize_only', false) || config('officeguy.testing', false);

        $request = [
            'Credentials'          => self::getCredentials(),
            'Items'                => self::getPaymentOrderItems($order),
            'VATIncluded'          => 'true',
            'VATRate'              => self::getOrderVatRate($order),
            'Customer'             => self::getOrderCustomer($order, RequestHelpers::post('og-citizenid')),
            'AuthoriseOnly'        => $authorizeOnly ? 'true' : 'false',
            'DraftDocument'        => config('officeguy.draft_document', false) ? 'true' : 'false',
            'SendDocumentByEmail'  => config('officeguy.email_document', true) ? 'true' : 'false',
            'UpdateCustomerByEmail'=> config('officeguy.email_document', true) ? 'true' : 'false',
            'UpdateCustomerOnSuccess' => config('officeguy.email_document', true) ? 'true' : 'false',
            'DocumentDescription'  => __('Order number') . ': ' . $order->getPayableId() .
                (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote()),
            'Payments_Count'       => $paymentsCount,
            'MaximumPayments'      => self::getMaximumPayments($orderTotal),
            'DocumentLanguage'     => self::getOrderLanguage(),
            'MerchantNumber'       => $recurring
                ? config('officeguy.subscriptions_merchant_number')
                : config('officeguy.merchant_number'),
        ];

        if ($authorizeOnly) {
            $request['AutoCapture'] = 'false';
            $authorizeAmount = $orderTotal;
            $percent = config('officeguy.authorize_added_percent');
            if ($percent !== null) {
                $authorizeAmount = round($authorizeAmount * (1 + ((float)$percent) / 100), 2);
            }
            $minAddition = config('officeguy.authorize_minimum_addition');
            if ($minAddition !== null && ($authorizeAmount - $orderTotal) < (float)$minAddition) {
                $authorizeAmount = round($orderTotal + (float)$minAddition, 2);
            }
            $request['AuthorizeAmount'] = $authorizeAmount;
        }

        if ($redirectMode) {
            // Caller must set RedirectURL / CancelRedirectURL in $extra
        } else {
            // Build payment method based on PCI mode
            $pciMode = config('officeguy.pci', 'no');

            if ($token) {
                $request['PaymentMethod'] = TokenService::getPaymentMethodFromToken($token);
            } elseif ($pciMode === 'yes') {
                $request['PaymentMethod'] = TokenService::getPaymentMethodPCI();
            } else {
                $singleUseToken = RequestHelpers::post('og-token');

                // 🐛 DEBUG: Log token for troubleshooting
                \Log::info('💳 [PaymentService] Building PaymentMethod with SingleUseToken', [
                    'has_token' => !empty($singleUseToken),
                    'token_length' => $singleUseToken ? strlen($singleUseToken) : 0,
                    'token_value' => $singleUseToken ?: 'EMPTY/NULL',
                    'pci_mode' => $pciMode,
                    'all_request_keys' => array_keys(request()->all()),
                    'has_og_token_in_request' => request()->has('og-token'),
                ]);

                $request['PaymentMethod'] = [
                    'SingleUseToken' => $singleUseToken,
                    'Type'           => 1,
                ];
            }
        }

        return array_merge($request, $extra);
    }

    /**
     * Process a card/redirect charge.
     * Returns array with keys: success(bool), redirect_url?, message?, payment?, response?
     */
    public static function processCharge(
        Payable $order,
        int $paymentsCount = 1,
        bool $recurring = false,
        bool $redirectMode = false,
        ?OfficeGuyToken $token = null,
        array $extra = []
    ): array {
        $environment = config('officeguy.environment', 'www');

        $request = self::buildChargeRequest($order, $paymentsCount, $recurring, $redirectMode, $token, $extra);

        $endpoint = '/billing/payments/charge/';
        if ($recurring) {
            $endpoint = '/billing/recurring/charge/';
        } elseif ($redirectMode) {
            $endpoint = '/billing/payments/beginredirect/';
        }

        $response = OfficeGuyApi::post($request, $endpoint, $environment, !$recurring);

        // Redirect flow
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

        if (!$response) {
            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . __('No response'),
            ];
        }

        $status = $response['Status'] ?? null;
        $payment = $response['Data']['Payment'] ?? null;

        if ($status === 0 && $payment && ($payment['ValidPayment'] ?? false) === true) {
            // Convert SUMIT currency enum to string (0=ILS, 1=USD, 2=EUR, etc.)
            $currencyEnum = $payment['Currency'] ?? null;
            $currencyMap = [0 => 'ILS', 1 => 'USD', 2 => 'EUR', 3 => 'GBP'];
            $currency = $currencyMap[$currencyEnum] ?? config('app.currency', 'ILS');

            // Persist transaction
            OfficeGuyTransaction::create([
                'order_id' => $order->getPayableId(),
                'payment_id' => $payment['ID'] ?? null,
                'document_id' => $response['Data']['DocumentID'] ?? null,
                'customer_id' => $response['Data']['CustomerID'] ?? null,
                'auth_number' => $payment['AuthNumber'] ?? null,
                'amount' => $payment['Amount'] ?? $order->getPayableAmount(),
                'currency' => $currency,
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
            ]);

            event(new \OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted(
                $order->getPayableId(),
                $payment,
                $response
            ));

            return [
                'success' => true,
                'payment' => $payment,
                'response' => $response,
            ];
        }

        if ($status !== 0) {
            event(new \OfficeGuy\LaravelSumitGateway\Events\PaymentFailed(
                $order->getPayableId(),
                $response,
                $response['UserErrorMessage'] ?? 'Gateway error'
            ));
            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . ($response['UserErrorMessage'] ?? 'Gateway error'),
                'response' => $response,
            ];
        }

        // Decline
        event(new \OfficeGuy\LaravelSumitGateway\Events\PaymentFailed(
            $order->getPayableId(),
            $response,
            $payment['StatusDescription'] ?? 'Declined'
        ));
        return [
            'success' => false,
            'message' => __('Payment failed') . ' - ' . ($payment['StatusDescription'] ?? 'Declined'),
            'response' => $response,
        ];
    }

    /**
     * Process refund to customer's payment method
     *
     * This returns money back to the original credit card.
     * This is NOT an accounting credit note - use DocumentService::createCreditNote() for that.
     *
     * @param \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer $customer Customer instance
     * @param string $transactionId Original transaction auth number
     * @param float $amount Amount to refund
     * @param string $reason Refund reason (default: החזר כספי ללקוח)
     * @return array{success: bool, transaction_id?: string, auth_number?: string, amount?: float, error?: string}
     */
    public static function processRefund(
        \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer $customer,
        string $transactionId,
        float $amount,
        string $reason = 'החזר כספי ללקוח'
    ): array {
        $sumitCustomerId = $customer->getSumitCustomerId();

        if (!$sumitCustomerId) {
            return [
                'success' => false,
                'error' => 'Customer not synced to SUMIT',
            ];
        }

        try {
            // SUMIT uses negative amount for refunds
            $payload = [
                'Credentials' => self::getCredentials(),
                'Details' => [
                    'Customer' => [
                        'ID' => (int) $sumitCustomerId,
                    ],
                    'Description' => $reason,
                    'Currency' => 0, // ILS
                    'Language' => 0, // Hebrew
                ],
                'Items' => [
                    [
                        'Item' => ['Name' => $reason],
                        'Quantity' => 1,
                        'UnitPrice' => -abs($amount), // Negative for refund
                        'TotalPrice' => -abs($amount),
                    ],
                ],
                'Payment' => [
                    'CreditCardAuthNumber' => $transactionId, // Reference to original transaction
                ],
                'VATIncluded' => false,
            ];

            $environment = config('officeguy.environment', 'www');
            $response = OfficeGuyApi::post(
                $payload,
                '/payments/charge/',
                $environment,
                false
            );

            if (($response['Status'] ?? 1) === 0 && isset($response['Data'])) {
                OfficeGuyApi::writeToLog(
                    'SUMIT refund processed successfully. Transaction ID: ' . $transactionId,
                    'info'
                );

                return [
                    'success' => true,
                    'transaction_id' => $response['Data']['TransactionID'] ?? null,
                    'auth_number' => $response['Data']['AuthNumber'] ?? null,
                    'amount' => $amount,
                ];
            }

            OfficeGuyApi::writeToLog(
                'SUMIT refund failed for transaction ' . $transactionId . ': ' . ($response['ErrorMessage'] ?? 'Unknown error'),
                'error'
            );

            return [
                'success' => false,
                'error' => $response['ErrorMessage'] ?? 'Unknown error during refund',
            ];

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog(
                'SUMIT refund exception for transaction ' . $transactionId . ': ' . $e->getMessage(),
                'error'
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
