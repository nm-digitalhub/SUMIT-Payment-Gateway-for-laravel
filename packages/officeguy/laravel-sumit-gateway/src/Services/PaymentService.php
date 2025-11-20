<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

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

        $customer = [
            'Name' => $customerName,
            'EmailAddress' => $order->getCustomerEmail(),
            'Phone' => $order->getCustomerPhone(),
            'ExternalIdentifier' => $order->getCustomerId() ?: '',
            'SearchMode' => config('officeguy.merge_customers', false) ? 'Automatic' : 'None',
        ];

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
}
