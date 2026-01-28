<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\DataTransferObjects\ResolvedPaymentIntent;
use OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector;
use OfficeGuy\LaravelSumitGateway\Http\DTOs\CredentialsData;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Payment\ChargePaymentRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Payment\GetPaymentDetailsRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Payment\GetPaymentMethodsRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Payment\ListPaymentsRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Payment\RemovePaymentMethodRequest;
use OfficeGuy\LaravelSumitGateway\Http\Requests\Payment\SetPaymentMethodRequest;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Payment Service
 *
 * Core payment processing service for the SUMIT Gateway package.
 * 1:1 port of `OfficeGuyPayment.php` from the WooCommerce plugin.
 *
 * ## Architecture
 *
 * This service is the **central payment orchestration layer** in the package:
 *
 * ```
 * Checkout Flow
 *     ↓
 * PaymentService::preparePayment()  → Create OfficeGuyTransaction record
 *     ↓
 * TokenService::processToken()      → Exchange single-use token for permanent token
 *     ↓
 * PaymentService::chargePayment()   → Execute charge via SUMIT API
 *     ↓
 * PaymentCompleted Event           → Trigger fulfillment
 *     ↓
 * FulfillmentListener               → Dispatch to fulfillment handlers
 * ```
 *
 * ## Key Responsibilities
 *
 * 1. **Payment Processing**:
 *    - `chargePayment()` - Execute credit card charges
 *    - `preparePayment()` - Create transaction records
 *    - `handleCallback()` - Process payment callbacks
 *
 * 2. **Document Generation**:
 *    - Automatic invoice/receipt creation
 *    - Donation receipt generation
 *    - Document linking to transactions
 *
 * 3. **Payment Method Management**:
 *    - `setPaymentMethodForCustomer()` - Save payment methods to SUMIT customer
 *    - Token-based payment method storage
 *    - Payment method retrieval and removal
 *
 * 4. **Installment Support**:
 *    - `getMaximumPayments()` - Calculate allowed installments
 *    - Configuration-based installment limits
 *    - Minimum amount per payment validation
 *
 * 5. **Bit Payment Integration**:
 *    - `createBitPayment()` - Create Bit payment transactions
 *    - Bit-specific callback handling
 *    - Bit payment status tracking
 *
 * ## Integration with Application State Machine
 *
 * The **Application Layer** owns the Order State Machine. This service:
 * - **Receives**: Payment request from checkout controller
 * - **Creates**: OfficeGuyTransaction record (technical tracking)
 * - **Executes**: Payment charge via SUMIT API
 * - **Dispatches**: PaymentCompleted event (triggers app state transition)
 * - **Does NOT** manage order state (app's responsibility)
 *
 * ## Saloon HTTP Integration (v2.0.0+)
 *
 * Uses Saloon PHP v3.14.2 for type-safe API communication:
 * - `SumitConnector` - Base API client
 * - `CredentialsData` - Type-safe credentials DTO
 * - Request classes - Inline anonymous Saloon Request classes
 *
 * ## Configuration
 *
 * All behavior is configurable via `config/officeguy.php`:
 * ```php
 * 'company_id' => env('OFFICEGUY_COMPANY_ID'),
 * 'private_key' => env('OFFICEGUY_PRIVATE_KEY'),
 * 'max_payments' => 12,  // Maximum installments
 * 'min_amount_per_payment' => 10,  // Min per installment
 * 'min_amount_for_payments' => 50,  // Min order value for installments
 * 'automatic_languages' => true,  // Auto-detect document language
 * ```
 *
 * ## PCI Compliance Modes
 *
 * - **PCI Mode = 'no'**: PaymentsJS SDK (recommended) - Card data never touches server
 * - **PCI Mode = 'redirect'**: External SUMIT payment page - Simplest integration
 * - **PCI Mode = 'yes'**: Direct API - Requires PCI DSS Level 1 certification
 *
 * ## Document Generation
 *
 * Automatically creates documents after successful payment:
 * - **Invoice**: For standard payments (configurable)
 * - **Receipt**: For non-invoice payments
 * - **Donation Receipt**: For donation payments (Section 46 compliant)
 *
 * ## Error Handling
 *
 * - All API calls wrapped in try-catch
 * - Detailed error logging via `OfficeGuyApi::writeToLog()`
 * - Transaction status updated based on API response
 * - Exceptions re-thrown for application layer handling
 *
 * @see \OfficeGuy\LaravelSumitGateway\Http\Connectors\SumitConnector
 * @see \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction
 * @see \OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted
 * @see \OfficeGuy\LaravelSumitGateway\Listeners\FulfillmentListener
 * @see docs/STATE_MACHINE_ARCHITECTURE.md
 */
class PaymentService
{
    /**
     * Get credentials array for API requests
     *
     * Port of: GetCredentials($Gateway)
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
     * @param  float  $orderValue  Order total amount
     * @return int Maximum number of installments
     */
    public static function getMaximumPayments(float $orderValue): int
    {
        $maximumPayments = (int) config('officeguy.max_payments', 1);

        $minAmountPerPayment = (float) config('officeguy.min_amount_per_payment', 0);
        if ($minAmountPerPayment > 0) {
            $maximumPayments = min($maximumPayments, (int) floor($orderValue / $minAmountPerPayment));
        }

        $minAmountForPayments = (float) config('officeguy.min_amount_for_payments', 0);
        if ($minAmountForPayments > 0 && round($orderValue) < round($minAmountForPayments)) {
            return 1;
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
     * @param  Payable  $order  Order instance
     * @return string VAT rate as string percentage
     */
    public static function getOrderVatRate(Payable $order): string
    {
        if (! $order->isTaxEnabled()) {
            return '';
        }

        $vatRate = $order->getVatRate();
        if ($vatRate === null) {
            return '0';
        }

        return (string) $vatRate;
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
        if (! config('officeguy.automatic_languages', true)) {
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
     * @param  string  $token  CreditCard_Token from SUMIT
     * @param  array  $method  Additional fields (optional) from PaymentMethod schema
     * @return array{success: bool, error?: string}
     */
    public static function setPaymentMethodForCustomer(string | int $sumitCustomerId, string $token, array $method = []): array
    {
        try {
            // Prepare additional fields for permanent tokens
            $additionalFields = $method;

            // If token looks like a permanent token (UUID format), try to get expiry dates
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
                // Try to find token in database to get expiry dates
                $tokenModel = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken::where('token', $token)->first();

                if ($tokenModel) {
                    $additionalFields = array_merge([
                        'CreditCard_ExpirationMonth' => (int) $tokenModel->expiry_month,
                        'CreditCard_ExpirationYear' => (int) $tokenModel->expiry_year,
                    ], $additionalFields);
                }
            }

            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new SetPaymentMethodRequest(
                customerId: (int) $sumitCustomerId,
                token: $token,
                credentials: $credentials,
                additionalFields: $additionalFields
            );

            \Log::info('setPaymentMethodForCustomer request', ['customer_id' => $sumitCustomerId, 'token' => substr($token, 0, 8) . '...']);

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            \Log::info('setPaymentMethodForCustomer response', ['status' => $data['Status'] ?? null]);

            if ($data === null || ($data['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $data['UserErrorMessage'] ?? 'Failed to set payment method',
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
     * @return array{success: bool, payment?: array|null, error?: string}
     */
    public static function getPaymentDetails(int | string $paymentId): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new GetPaymentDetailsRequest(
                paymentId: (int) $paymentId,
                credentials: $credentials
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data === null || ($data['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $data['UserErrorMessage'] ?? 'Failed to fetch payment details',
                ];
            }

            return [
                'success' => true,
                'payment' => $data['Data']['Payment'] ?? null,
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
     * @param  array  $filters  [Date_From?, Date_To?, Valid?, StartIndex?]
     * @return array{success: bool, payments?: array<int, array>, has_next?: bool, error?: string}
     */
    public static function listPayments(array $filters = []): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new ListPaymentsRequest(
                credentials: $credentials,
                dateFrom: $filters['Date_From'] ?? null,
                dateTo: $filters['Date_To'] ?? null,
                valid: $filters['Valid'] ?? null,
                startIndex: $filters['StartIndex'] ?? 0
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data === null || ($data['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $data['UserErrorMessage'] ?? 'Failed to list payments',
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
     * @return array{success: bool, payment_methods?: array<int, array>, active_method?: array|null, inactive_methods?: array<int, array>, error?: string}
     */
    public static function getPaymentMethodsForCustomer(string | int $sumitCustomerId, bool $includeInactive = false): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new GetPaymentMethodsRequest(
                customerId: (int) $sumitCustomerId,
                credentials: $credentials,
                includeInactive: $includeInactive
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data === null || ($data['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $data['UserErrorMessage'] ?? 'Failed to fetch payment methods',
                ];
            }

            $responseData = $data['Data'] ?? [];

            $active = $responseData['PaymentMethod'] ?? null;
            $inactive = ! empty($responseData['InactivePaymentMethods']) && is_array($responseData['InactivePaymentMethods'])
                ? $responseData['InactivePaymentMethods']
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
     * @param  string|int  $sumitCustomerId  SUMIT customer ID
     * @return array{success: bool, error?: string}
     */
    public static function removePaymentMethodForCustomer(string | int $sumitCustomerId): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new RemovePaymentMethodRequest(
                customerId: (int) $sumitCustomerId,
                credentials: $credentials
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data === null || ($data['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $data['UserErrorMessage'] ?? 'Failed to remove payment method',
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
     * @param  string  $token  Payment token to test
     * @param  string|int  $sumitCustomerId  SUMIT customer ID
     * @return array{success: bool, transaction_id?: string, error?: string}
     */
    public static function testPayment(string $token, string | int $sumitCustomerId): array
    {
        try {
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new ChargePaymentRequest(
                customerId: (int) $sumitCustomerId,
                amount: 1.0, // ₪1 test charge
                credentials: $credentials,
                token: $token,
                description: 'Test payment - Token validation',
                cancelable: true // Allow cancellation
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();

            if ($data === null || ($data['Status'] ?? 1) !== 0) {
                return [
                    'success' => false,
                    'error' => $data['UserErrorMessage'] ?? 'Test payment failed',
                ];
            }

            return [
                'success' => true,
                'transaction_id' => $data['Data']['ID'] ?? null,
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
     * @param  Payable  $order  Order instance
     * @param  string|null  $citizenId  Optional citizen ID from request
     * @return array Customer data for API
     */
    public static function getOrderCustomer(Payable $order, ?string $citizenId = null): array
    {
        $customerName = $order->getCustomerName();
        $company = $order->getCustomerCompany();

        if (! in_array($company, [null, '', '0'], true)) {
            $customerName = $company . ' - ' . $customerName;
        }

        if (in_array(trim($customerName), ['', '0'], true)) {
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
        // IMPORTANT: Always fetch fresh Client from DB to avoid stale relation cache
        $sumitCustomerId = null;
        if ($order instanceof \Illuminate\Database\Eloquent\Model && method_exists($order, 'client')) {
            $client = $order->client()->first();
            if ($client && ! empty($client->sumit_customer_id)) {
                $sumitCustomerId = $client->sumit_customer_id;
            }
        }

        // CRITICAL: If customer exists in SUMIT, return ONLY CustomerID
        // When Customer.ID is provided, NO other customer fields must be sent.
        // SUMIT will use the existing customer record and ignore search logic.
        if ($sumitCustomerId) {
            return [
                'ID' => (int) $sumitCustomerId,
            ];
        }

        // Otherwise, send full Customer object for new customer creation
        // SearchMode values: 0=Automatic, 1=None, 6=EmailAddress
        // Using 6 (EmailAddress) prevents duplicate customers by matching email
        $customer = [
            'Name' => $customerName,
            'EmailAddress' => $order->getCustomerEmail(),
            'Phone' => $order->getCustomerPhone(),
            'SearchMode' => $mergeCustomers ? 6 : 1,
        ];

        // Add ExternalIdentifier for additional matching (if available)
        // This helps SUMIT match existing customers even without sumit_customer_id
        if ($order->getCustomerId()) {
            $customer['ExternalIdentifier'] = (string) $order->getCustomerId();
        }

        if ($address) {
            $customer['Address'] = $address['address'] ?? '';
            if (! empty($address['address2'])) {
                $customer['Address'] = trim($customer['Address'] . ', ' . $address['address2']);
            }

            $customer['City'] = $address['city'] ?? '';
            if (! empty($address['state'])) {
                $customer['City'] = empty($customer['City'])
                    ? $address['state']
                    : $customer['City'] . ', ' . $address['state'];
            }

            if (! empty($address['country']) && $address['country'] !== 'IL') {
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
     * @param  string  $currency  Currency code
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
     * @param  Payable  $order  Order instance
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
        if ($shippingAmount > 0 && ! in_array($shippingMethod, [null, '', '0'], true)) {
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
     * @param  Payable  $order  Order instance
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
     * @param  array  $extra  Additional request overrides
     */
    public static function buildChargeRequest(
        Payable $order,
        int $paymentsCount = 1,
        bool $recurring = false,
        bool $redirectMode = false,
        ?OfficeGuyToken $token = null,
        array $extra = [],
        ?array $paymentMethodPayload = null,
        ?string $singleUseToken = null,
        ?string $customerCitizenId = null
    ): array {
        $orderTotal = round($order->getPayableAmount(), 2);

        $authorizeOnly = config('officeguy.authorize_only', false) || config('officeguy.testing', false);

        $request = [
            'Credentials' => self::getCredentials(),
            'Items' => self::getPaymentOrderItems($order),
            'VATIncluded' => 'true',
            'VATRate' => self::getOrderVatRate($order),
            'Customer' => self::getOrderCustomer($order, $customerCitizenId),
            'AuthoriseOnly' => $authorizeOnly ? 'true' : 'false',
            'DraftDocument' => config('officeguy.draft_document', false) ? 'true' : 'false',
            'SendDocumentByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
            'UpdateCustomerByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
            'UpdateCustomerOnSuccess' => config('officeguy.email_document', true) ? 'true' : 'false',
            'DocumentDescription' => __('Order number') . ': ' . $order->getPayableId() .
                (in_array($order->getCustomerNote(), [null, '', '0'], true) ? '' : "\r\n" . $order->getCustomerNote()),
            'Payments_Count' => $paymentsCount,
            'MaximumPayments' => self::getMaximumPayments($orderTotal),
            'DocumentLanguage' => self::getOrderLanguage(),
            'MerchantNumber' => $recurring
                ? config('officeguy.subscriptions_merchant_number')
                : config('officeguy.merchant_number'),
        ];

        if ($authorizeOnly) {
            $request['AutoCapture'] = 'false';
            $authorizeAmount = $orderTotal;
            $percent = config('officeguy.authorize_added_percent');
            if ($percent !== null) {
                $authorizeAmount = round($authorizeAmount * (1 + ((float) $percent) / 100), 2);
            }
            $minAddition = config('officeguy.authorize_minimum_addition');
            if ($minAddition !== null && ($authorizeAmount - $orderTotal) < (float) $minAddition) {
                $authorizeAmount = round($orderTotal + (float) $minAddition, 2);
            }
            $request['AuthorizeAmount'] = $authorizeAmount;
        }

        if ($singleUseToken !== null) {
            // Use single-use token from PaymentsJS SDK
            $request['SingleUseToken'] = $singleUseToken;
        } elseif ($token instanceof \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken) {
            // Use saved payment token
            // CRITICAL: Must use token's citizen_id (not customer input) for bank validation
            $request['PaymentMethod'] = [
                'CreditCard_Token' => $token->token,
                'CreditCard_CitizenID' => $token->citizen_id,  // ← From token, not customer input!
                'CreditCard_ExpirationMonth' => $token->expiry_month,
                'CreditCard_ExpirationYear' => $token->expiry_year,
                'Type' => 1,  // Credit card
            ];
        } elseif (! $redirectMode && ($paymentMethodPayload !== null && $paymentMethodPayload !== [])) {
            // Use direct card details (PCI mode = 'yes')
            $request['PaymentMethod'] = $paymentMethodPayload;
        }

        // Merge extra parameters
        $request = array_merge($request, $extra);

        // SAFETY GUARD: Prevent accidental override of Customer.ID
        // If Customer.ID exists, strip all other Customer fields to prevent SUMIT from creating duplicate customers
        // This protects against $extra containing Customer data that would override the ID-only approach
        if (isset($request['Customer']['ID'])) {
            $request['Customer'] = [
                'ID' => $request['Customer']['ID'],
            ];
        }

        return $request;
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
        array $extra = [],
        ?array $paymentMethodPayload = null,
        ?string $singleUseToken = null,
        ?string $customerCitizenId = null
    ): array {
        $environment = config('officeguy.environment', 'www');

        $request = self::buildChargeRequest(
            $order,
            $paymentsCount,
            $recurring,
            $redirectMode,
            $token,
            $extra,
            $paymentMethodPayload,
            $singleUseToken,
            $customerCitizenId
        );

        $endpoint = '/billing/payments/charge/';
        if ($recurring) {
            $endpoint = '/billing/recurring/charge/';
        } elseif ($redirectMode) {
            $endpoint = '/billing/payments/beginredirect/';
        }

        // VALIDATION LOG: Verify Customer payload before sending to SUMIT
        // Expected for existing customer: {"ID": 123456789}
        // Incorrect (causes duplicate): {"Name": "...", "EmailAddress": "...", ...}
        \Log::debug('SUMIT FINAL CUSTOMER PAYLOAD', [
            'order_id' => $order->getPayableId(),
            'customer' => $request['Customer'] ?? null,
            'endpoint' => $endpoint,
        ]);

        // Execute request using Saloon inline anonymous Request class
        try {
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            $connector = new SumitConnector;
            $saloonRequest = new class(
                $credentials,
                $request,
                $endpoint,
                ! $recurring // sendClientIp
            ) extends \Saloon\Http\Request implements \Saloon\Contracts\Body\HasBody
            {
                use \Saloon\Traits\Body\HasJsonBody;

                protected \Saloon\Enums\Method $method = \Saloon\Enums\Method::POST;

                public function __construct(
                    protected readonly CredentialsData $credentials,
                    protected readonly array $requestData,
                    protected readonly string $endpoint,
                    protected readonly bool $sendClientIp
                ) {}

                public function resolveEndpoint(): string
                {
                    return $this->endpoint;
                }

                protected function defaultBody(): array
                {
                    return array_merge(
                        ['Credentials' => $this->credentials->toArray()],
                        $this->requestData
                    );
                }

                protected function defaultHeaders(): array
                {
                    $headers = [];
                    if ($this->sendClientIp && request()->ip()) {
                        $headers['X-OG-ClientIP'] = request()->ip();
                    }

                    return $headers;
                }

                protected function defaultConfig(): array
                {
                    return ['timeout' => 180];
                }
            };

            $saloonResponse = $connector->send($saloonRequest);
            $response = $saloonResponse->json();

        } catch (\Throwable $e) {
            OfficeGuyApi::writeToLog('Payment charge exception: ' . $e->getMessage(), 'error');

            return [
                'success' => false,
                'message' => __('Payment failed') . ' - ' . $e->getMessage(),
            ];
        }

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

        if (! $response) {
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
            $currency = $currencyMap[$currencyEnum] ?? config('officeguy.invoice_currency_code', 'ILS');

            // Persist transaction
            OfficeGuyTransaction::create([
                'order_id' => $order->getPayableId(),
                'order_type' => $order::class,
                'payment_id' => $payment['ID'] ?? null,
                'sumit_entity_id' => $payment['ID'] ?? null, // CRITICAL: Used by TransactionSyncListener to match CRM webhooks
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
     * Execute payment from a resolved checkout intent
     *
     * Single architectural entry point for payment execution.
     * Controllers / Jobs MUST call this method only.
     */
    public static function processResolvedIntent(ResolvedPaymentIntent $intent): array
    {
        // DEBUG: Log what we received
        OfficeGuyApi::writeToLog('🔍 processResolvedIntent called', 'info');

        // Resolve saved payment token with security validation
        $tokenModel = null;
        if (! in_array($intent->token, [null, '', '0'], true)) {
            // Get customer ID for security validation
            $customerId = $intent->payable->getCustomerId();

            // Query by token ID (not UUID) with owner validation
            // Note: $intent->token contains the database ID (integer), not the UUID string
            $tokenModel = OfficeGuyToken::query()
                ->where('id', $intent->token)  // ← Search by ID, not token UUID!
                ->where('owner_type', 'client')  // Tokens use 'client' as owner_type
                ->where('owner_id', $customerId)
                ->first();

            // Log warning if token not found
            if (! $tokenModel) {
                OfficeGuyApi::writeToLog('⚠️ Token not found for intent', 'warning');
            } else {
                OfficeGuyApi::writeToLog('✅ Token found and resolved', 'info');
            }
        } else {
            OfficeGuyApi::writeToLog('ℹ️ No saved token in intent', 'info');
        }

        $extra = [];
        if ($intent->redirectMode && $intent->redirectUrls) {
            $extra['RedirectURL'] = $intent->redirectUrls['success'] ?? null;
            $extra['CancelRedirectURL'] = $intent->redirectUrls['cancel'] ?? null;
        }

        return self::processCharge(
            order: $intent->payable,
            paymentsCount: $intent->paymentsCount,
            recurring: $intent->recurring,
            redirectMode: $intent->redirectMode,
            token: $tokenModel,  // Pass OfficeGuyToken model or null
            extra: $extra,
            paymentMethodPayload: $intent->paymentMethodPayload,
            singleUseToken: $intent->singleUseToken,
            customerCitizenId: $intent->customerCitizenId
        );
    }

    /**
     * Process refund to customer's payment method
     *
     * This returns money back to the original credit card.
     * This is NOT an accounting credit note - use DocumentService::createCreditNote() for that.
     *
     * @param  \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer  $customer  Customer instance
     * @param  string  $transactionId  Original transaction auth number
     * @param  float  $amount  Amount to refund
     * @param  string  $reason  Refund reason (default: החזר כספי ללקוח)
     * @return array{success: bool, transaction_id?: string, auth_number?: string, amount?: float, error?: string}
     */
    public static function processRefund(
        \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer $customer,
        string $transactionId,
        float $amount,
        string $reason = 'החזר כספי ללקוח'
    ): array {
        $sumitCustomerId = $customer->getSumitCustomerId();

        if (! $sumitCustomerId) {
            return [
                'success' => false,
                'error' => 'Customer not synced to SUMIT',
            ];
        }

        try {
            // SUMIT uses negative amount for refunds with SupportCredit flag
            // Create credentials DTO
            $credentials = new CredentialsData(
                companyId: (int) config('officeguy.company_id'),
                apiKey: (string) config('officeguy.private_key')
            );

            // Prepare refund items (negative amount)
            $items = [
                [
                    'Item' => ['Name' => $reason],
                    'Quantity' => 1,
                    'UnitPrice' => -abs($amount), // Negative for refund
                ],
            ];

            // Instantiate connector and request
            $connector = new SumitConnector;
            $request = new ChargePaymentRequest(
                customerId: (int) $sumitCustomerId,
                amount: $amount, // Amount is ignored when items are provided
                credentials: $credentials,
                token: null, // No token needed for refunds
                description: null,
                cancelable: false,
                supportCredit: true, // Enable credit/refund support
                items: $items,
                originalTransactionId: $transactionId, // Reference to original transaction
                vatIncluded: false
            );

            // Send request
            $response = $connector->send($request);
            $data = $response->json();
            $environment = config('officeguy.environment', 'www');

            if (($data['Status'] ?? 1) === 0 && isset($data['Data'])) {
                // Extract refund transaction ID from Payment object
                // SUMIT returns refund details in Data.Payment (same structure as charge)
                $refundTransactionId = $response['Data']['Payment']['ID'] ?? null;
                $refundAuthNumber = $response['Data']['Payment']['AuthNumber'] ?? null;
                $paymentData = $response['Data']['Payment'] ?? [];
                $paymentMethod = $paymentData['PaymentMethod'] ?? [];

                // Find original transaction by payment_id (which is the auth_number we sent)
                $originalTransaction = OfficeGuyTransaction::where('payment_id', $transactionId)
                    ->orWhere('auth_number', $transactionId)
                    ->first();

                if (! $originalTransaction) {
                    // Log warning but don't fail the refund
                    OfficeGuyApi::writeToLog(
                        'Warning: Original transaction not found for refund. Transaction ID: ' . $transactionId,
                        'warning'
                    );
                }

                // Create new transaction record for the refund
                $refundRecord = OfficeGuyTransaction::create([
                    'order_id' => 'REFUND-' . $transactionId,  // Unique identifier
                    'payment_id' => $refundTransactionId,
                    'sumit_entity_id' => $refundTransactionId, // CRITICAL: Used by TransactionSyncListener to match CRM webhooks
                    'auth_number' => $refundAuthNumber,
                    'customer_id' => $sumitCustomerId,
                    'amount' => $amount,  // Positive amount (represents refunded value)
                    'currency' => $originalTransaction?->currency ?? config('officeguy.invoice_currency_code', 'ILS'),
                    'transaction_type' => 'refund',
                    'parent_transaction_id' => $originalTransaction?->id,
                    'payment_token' => $paymentMethod['CreditCard_Token'] ?? $originalTransaction?->payment_token,
                    'last_digits' => $paymentMethod['CreditCard_LastDigits'] ?? $originalTransaction?->last_digits,
                    'expiration_month' => $paymentMethod['CreditCard_ExpirationMonth'] ?? $originalTransaction?->expiration_month,
                    'expiration_year' => $paymentMethod['CreditCard_ExpirationYear'] ?? $originalTransaction?->expiration_year,
                    'card_type' => $paymentMethod['Type'] ?? $originalTransaction?->card_type,
                    'status' => 'completed',
                    'status_description' => $reason,
                    'payment_method' => 'card',
                    'payments_count' => 1,
                    'raw_request' => $payload,
                    'raw_response' => $response,
                    'environment' => $environment,
                    'is_test' => config('officeguy.testing', false),
                ]);

                // Update original transaction with refund link
                if ($originalTransaction) {
                    $originalTransaction->update([
                        'status' => 'refunded',
                        'refund_transaction_id' => $refundRecord->id,
                        'status_description' => $reason,
                    ]);
                }

                OfficeGuyApi::writeToLog(
                    'SUMIT refund processed successfully. Original Transaction: ' . $transactionId .
                    ', Refund Transaction: ' . ($refundTransactionId ?? 'N/A') .
                    ', Auth Number: ' . ($refundAuthNumber ?? 'N/A') .
                    ', Refund Record ID: ' . $refundRecord->id,
                    'info'
                );

                return [
                    'success' => true,
                    'refund_record' => $refundRecord,
                    'original_transaction' => $originalTransaction,
                    'transaction_id' => $refundTransactionId,
                    'auth_number' => $refundAuthNumber,
                    'amount' => $amount,
                    'response' => $response,
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
