<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Settings;

use Spatie\LaravelSettings\Settings;

class SumitSettings extends Settings
{
    /**
     * Default values to prevent MissingSettings before migrations run.
     */
    public static array $defaults = [
        'company_id' => 0,
        'private_key' => '',
        'public_key' => '',
        'environment' => 'www',

        'routes' => [
            'prefix' => 'officeguy',
            'middleware' => ['web'],
            'card_callback' => 'callback/card',
            'bit_webhook' => 'webhook/bit',
            'success' => 'checkout.success',
            'failed' => 'checkout.failed',
            'enable_checkout_endpoint' => false,
            'checkout_charge' => 'checkout/charge',
        ],

        'order' => [
            'resolver' => null,
            'model' => null,
        ],

        'pci' => 'no',
        'pci_mode' => 'no',
        'testing' => false,

        'max_payments' => 1,
        'min_amount_for_payments' => 0.0,
        'min_amount_per_payment' => 0.0,

        'authorize_only' => false,
        'authorize_added_percent' => 0.0,
        'authorize_minimum_addition' => 0.0,

        'merchant_number' => null,
        'subscriptions_merchant_number' => null,

        'draft_document' => false,
        'email_document' => true,
        'create_order_document' => false,
        'automatic_languages' => true,
        'merge_customers' => false,

        'support_tokens' => true,
        'token_param' => '5',

        'citizen_id' => 'required',
        'cvv' => 'required',
        'four_digits_year' => true,
        'single_column_layout' => true,

        'bit_enabled' => false,

        'logging' => false,
        'log_channel' => 'stack',
        'ssl_verify' => true,

        'stock_sync_freq' => 'none',
        'checkout_stock_sync' => false,
        'stock' => [
            'update_callback' => null,
        ],

        'paypal_receipts' => 'no',
        'bluesnap_receipts' => false,
        'other_receipts' => null,

        'supported_currencies' => [
            'ILS', 'USD', 'EUR', 'CAD', 'GBP', 'CHF', 'AUD', 'JPY', 'SEK', 'NOK',
            'DKK', 'ZAR', 'JOD', 'LBP', 'EGP', 'BGN', 'CZK', 'HUF', 'PLN', 'RON',
            'ISK', 'HRK', 'RUB', 'TRY', 'BRL', 'CNY', 'HKD', 'IDR', 'INR', 'KRW',
            'MXN', 'MYR', 'NZD', 'PHP', 'SGD', 'THB',
        ],
    ];
    public int $company_id;
    public string $private_key;
    public string $public_key;
    public string $environment; // www|dev|test

    public string $pci; // no|redirect|yes
    public string $pci_mode; // alias to pci
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
    public string $token_param; // J2/J5 -> '2'|'5'

    public string $citizen_id; // required|yes|no
    public string $cvv; // required|yes|no
    public bool $four_digits_year;
    public bool $single_column_layout;

    public bool $bit_enabled;

    public bool $logging;
    public string $log_channel;

    public string $stock_sync_freq; // none|12|24
    public bool $checkout_stock_sync;
    /** @var array{update_callback:mixed} */
    public array $stock;

    public string $paypal_receipts; // no|yes|async
    public bool $bluesnap_receipts;
    public ?string $other_receipts;

    /** @var array<int,string> */
    public array $supported_currencies;

    public bool $ssl_verify;

    /** @var array{prefix:string,middleware:array,card_callback:string,bit_webhook:string,success:string,failed:string,enable_checkout_endpoint:bool,checkout_charge:string} */
    public array $routes;

    /** @var array{resolver:mixed,model:mixed} */
    public array $order;

    public static function group(): string
    {
        return 'officeguy';
    }
}
