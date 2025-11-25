<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * Donation Service
 *
 * Port of OfficeGuyDonation.php from WooCommerce plugin.
 * Handles donation product detection and document creation with DonationReceipt type.
 */
class DonationService
{
    /**
     * Check if an order/cart contains donation items
     * Port of: CartContainsDonation() from OfficeGuyDonation.php
     *
     * @param Payable|array $orderOrItems Order instance or array of line items
     * @return bool
     */
    public static function containsDonation(Payable|array $orderOrItems): bool
    {
        $items = $orderOrItems instanceof Payable 
            ? $orderOrItems->getLineItems() 
            : $orderOrItems;

        foreach ($items as $item) {
            if (self::isDonationItem($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an order/cart contains non-donation items
     * Port of: CartContainsNonDonation() from OfficeGuyDonation.php
     *
     * @param Payable|array $orderOrItems
     * @return bool
     */
    public static function containsNonDonation(Payable|array $orderOrItems): bool
    {
        $items = $orderOrItems instanceof Payable 
            ? $orderOrItems->getLineItems() 
            : $orderOrItems;

        foreach ($items as $item) {
            if (!self::isDonationItem($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if item is a donation
     *
     * @param array $item
     * @return bool
     */
    public static function isDonationItem(array $item): bool
    {
        return ($item['is_donation'] ?? false) === true
            || ($item['is_donation'] ?? false) === 'yes'
            || ($item['OfficeGuyDonation'] ?? false) === 'yes'
            || ($item['OfficeGuyDonation'] ?? false) === true;
    }

    /**
     * Check if cart has mixed items (donations and non-donations)
     *
     * @param Payable|array $orderOrItems
     * @return bool
     */
    public static function hasMixedItems(Payable|array $orderOrItems): bool
    {
        return self::containsDonation($orderOrItems) && self::containsNonDonation($orderOrItems);
    }

    /**
     * Validate that cart doesn't have mixed donations and regular products
     * Port of: UpdateAvailableGateways logic from OfficeGuyDonation.php
     *
     * @param Payable|array $orderOrItems
     * @return array Validation result with 'valid' bool and 'message' string
     */
    public static function validateCart(Payable|array $orderOrItems): array
    {
        if (self::hasMixedItems($orderOrItems)) {
            return [
                'valid' => false,
                'message' => __('Donations cannot be combined with regular products in the same order. Please complete separate orders.'),
            ];
        }

        return [
            'valid' => true,
            'message' => '',
        ];
    }

    /**
     * Get the document type for the order
     * Returns 'DonationReceipt' (type 320) for donations, or the configured default type
     *
     * @param Payable|array $orderOrItems
     * @return string Document type identifier
     */
    public static function getDocumentType(Payable|array $orderOrItems): string
    {
        if (self::containsDonation($orderOrItems) && !self::containsNonDonation($orderOrItems)) {
            return 'DonationReceipt'; // SUMIT document type for donation receipts
        }

        return '1'; // Default: Invoice/Receipt
    }

    /**
     * Get the numeric document type code for the order
     *
     * @param Payable|array $orderOrItems
     * @return int
     */
    public static function getDocumentTypeCode(Payable|array $orderOrItems): int
    {
        if (self::containsDonation($orderOrItems) && !self::containsNonDonation($orderOrItems)) {
            return 320; // SUMIT document type code for donation receipts
        }

        return 1; // Default: Invoice/Receipt
    }

    /**
     * Split items into donations and regular products
     *
     * @param Payable|array $orderOrItems
     * @return array ['donations' => [...], 'regular' => [...]]
     */
    public static function splitItems(Payable|array $orderOrItems): array
    {
        $items = $orderOrItems instanceof Payable 
            ? $orderOrItems->getLineItems() 
            : $orderOrItems;

        $donations = [];
        $regular = [];

        foreach ($items as $item) {
            if (self::isDonationItem($item)) {
                $donations[] = $item;
            } else {
                $regular[] = $item;
            }
        }

        return [
            'donations' => $donations,
            'regular' => $regular,
        ];
    }

    /**
     * Calculate total amount for donation items only
     *
     * @param Payable|array $orderOrItems
     * @return float
     */
    public static function getDonationTotal(Payable|array $orderOrItems): float
    {
        $items = $orderOrItems instanceof Payable 
            ? $orderOrItems->getLineItems() 
            : $orderOrItems;

        $total = 0;

        foreach ($items as $item) {
            if (self::isDonationItem($item)) {
                $total += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
            }
        }

        return round($total, 2);
    }

    /**
     * Calculate total amount for non-donation items
     *
     * @param Payable|array $orderOrItems
     * @return float
     */
    public static function getRegularTotal(Payable|array $orderOrItems): float
    {
        $items = $orderOrItems instanceof Payable 
            ? $orderOrItems->getLineItems() 
            : $orderOrItems;

        $total = 0;

        foreach ($items as $item) {
            if (!self::isDonationItem($item)) {
                $total += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 1);
            }
        }

        return round($total, 2);
    }
}
