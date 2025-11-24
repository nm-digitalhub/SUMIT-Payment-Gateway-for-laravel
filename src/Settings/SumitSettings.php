<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Settings;

use Spatie\LaravelSettings\Settings;

class SumitSettings extends Settings
{
    public int $company_id;
    public string $private_key;
    public string $public_key;
    public string $environment; // www|dev|test

    public string $pci; // no|redirect|yes
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

    public string $paypal_receipts; // no|yes|async
    public bool $bluesnap_receipts;
    public ?string $other_receipts;

    /** @var array<int,string> */
    public array $supported_currencies;

    public bool $ssl_verify;

    public static function group(): string
    {
        return 'officeguy';
    }
}
