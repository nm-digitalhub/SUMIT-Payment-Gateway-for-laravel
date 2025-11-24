<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Settings;

use Spatie\LaravelSettings\Settings;

class SumitSettings extends Settings
{
    public int $company_id;
    public string $private_key;
    public string $public_key;

    /** Allowed: www|dev|test */
    public string $environment;

    /** Allowed: no|redirect|yes */
    public string $pci;

    /** Alias for pci – optional for backward compatibility */
    public string $pci_mode;

    public bool $testing;

    public int $max_payments;
    public float $min_amount_for_payments;
    public float $min_amount_per_payment;

    public bool $authorize_only;
    public ?float $authorize_added_percent;
    public ?float $authorize_minimum_addition;

    public ?string $merchant_number;
    public ?string $subscriptions_merchant_number;

    public bool $draft_document;
    public bool $email_document;
    public bool $create_order_document;
    public bool $automatic_languages;
    public bool $merge_customers;

    public bool $support_tokens;

    /** Example: '2' or '5' */
    public string $token_param;

    /** Allowed: required|yes|no */
    public string $citizen_id;

    /** Allowed: required|yes|no */
    public string $cvv;

    public bool $four_digits_year;
    public bool $single_column_layout;

    public bool $bit_enabled;

    public bool $logging;
    public string $log_channel;

    /** Allowed: none|12|24 */
    public string $stock_sync_freq;

    public bool $checkout_stock_sync;

    /** Stock configuration (generic array) */
    public array $stock;

    /** Allowed: no|yes|async */
    public string $paypal_receipts;

    public bool $bluesnap_receipts;

    /** Optional value for "other" receipts provider */
    public ?string $other_receipts;

    /** Example: ['ILS','USD'] */
    public array $supported_currencies;

    public bool $ssl_verify;

    /**
     * Route configuration structure.
     * Example keys: prefix, middleware, card_callback, bit_webhook, success, failed, enable_checkout_endpoint, checkout_charge
     */
    public array $routes;

    /**
     * Order resolver or model configuration
     * Keys: resolver, model
     */
    public array $order;

    public static function group(): string
    {
        return 'officeguy';
    }
}