<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SUMIT Environment
    |--------------------------------------------------------------------------
    |
    | The environment for SUMIT API calls. Options: 'www' (production), 'dev', 'test'
    |
    */
    'environment' => env('OFFICEGUY_ENVIRONMENT', 'www'),

    /*
    |--------------------------------------------------------------------------
    | Company Credentials
    |--------------------------------------------------------------------------
    |
    | Your SUMIT company credentials obtained from https://app.sumit.co.il/developers/keys/
    |
    */
    'company_id' => env('OFFICEGUY_COMPANY_ID'),
    'private_key' => env('OFFICEGUY_PRIVATE_KEY'),
    'public_key' => env('OFFICEGUY_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | PCI Mode
    |--------------------------------------------------------------------------
    |
    | Payment capture interface mode:
    | - 'no': Simple mode using PaymentsJS (recommended, supports all features)
    | - 'redirect': External page redirect (no support for recurring, tokens, or authorize-only)
    | - 'yes': Advanced PCI compliant mode (direct API calls, requires PCI certification)
    |
    */
    'pci' => env('OFFICEGUY_PCI_MODE', 'no'),

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */
    'testing' => env('OFFICEGUY_TESTING', false),
    'authorize_only' => env('OFFICEGUY_AUTHORIZE_ONLY', false),
    'authorize_added_percent' => env('OFFICEGUY_AUTHORIZE_ADDED_PERCENT'),
    'authorize_minimum_addition' => env('OFFICEGUY_AUTHORIZE_MINIMUM_ADDITION'),

    /*
    |--------------------------------------------------------------------------
    | Installments Configuration
    |--------------------------------------------------------------------------
    */
    'max_payments' => env('OFFICEGUY_MAX_PAYMENTS', 1),
    'min_amount_for_payments' => env('OFFICEGUY_MIN_AMOUNT_FOR_PAYMENTS', 0),
    'min_amount_per_payment' => env('OFFICEGUY_MIN_AMOUNT_PER_PAYMENT', 0),

    /*
    |--------------------------------------------------------------------------
    | Merchant Numbers
    |--------------------------------------------------------------------------
    |
    | Optional merchant numbers for multiple merchant setups
    |
    */
    'merchant_number' => env('OFFICEGUY_MERCHANT_NUMBER'),
    'subscriptions_merchant_number' => env('OFFICEGUY_SUBSCRIPTIONS_MERCHANT_NUMBER'),

    /*
    |--------------------------------------------------------------------------
    | Document Settings
    |--------------------------------------------------------------------------
    */
    'draft_document' => env('OFFICEGUY_DRAFT_DOCUMENT', false),
    'email_document' => env('OFFICEGUY_EMAIL_DOCUMENT', true),
    'create_order_document' => env('OFFICEGUY_CREATE_ORDER_DOCUMENT', false),
    'automatic_languages' => env('OFFICEGUY_AUTOMATIC_LANGUAGES', true),

    /*
    |--------------------------------------------------------------------------
    | Customer Settings
    |--------------------------------------------------------------------------
    */
    'merge_customers' => env('OFFICEGUY_MERGE_CUSTOMERS', false),

    /*
    |--------------------------------------------------------------------------
    | Tokenization Settings
    |--------------------------------------------------------------------------
    */
    'support_tokens' => env('OFFICEGUY_SUPPORT_TOKENS', false),
    'token_param' => env('OFFICEGUY_TOKEN_PARAM', '5'), // J5 or J2 (2)

    /*
    |--------------------------------------------------------------------------
    | Input Field Settings
    |--------------------------------------------------------------------------
    |
    | Options: 'required', 'yes' (optional), 'no' (hidden)
    |
    */
    'citizen_id' => env('OFFICEGUY_CITIZEN_ID', 'required'),
    'cvv' => env('OFFICEGUY_CVV', 'required'),
    'four_digits_year' => env('OFFICEGUY_FOUR_DIGITS_YEAR', true),
    'single_column_layout' => env('OFFICEGUY_SINGLE_COLUMN_LAYOUT', true),

    /*
    |--------------------------------------------------------------------------
    | Bit Payment Settings
    |--------------------------------------------------------------------------
    */
    'bit_enabled' => env('OFFICEGUY_BIT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => env('OFFICEGUY_LOGGING', false),
    'log_channel' => env('OFFICEGUY_LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure callback and webhook URLs
    |
    */
    'routes' => [
        'prefix' => env('OFFICEGUY_ROUTE_PREFIX', 'officeguy'),
        'middleware' => ['web'],
        'card_callback' => env('OFFICEGUY_CARD_CALLBACK_PATH', 'callback/card'),
        'bit_webhook' => env('OFFICEGUY_BIT_WEBHOOK_PATH', 'webhook/bit'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Management
    |--------------------------------------------------------------------------
    */
    'stock_sync_freq' => env('OFFICEGUY_STOCK_SYNC_FREQ', 'none'), // 'none', '12', '24'
    'checkout_stock_sync' => env('OFFICEGUY_CHECKOUT_STOCK_SYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    */
    'paypal_receipts' => env('OFFICEGUY_PAYPAL_RECEIPTS', 'no'), // 'no', 'yes', 'async'
    'bluesnap_receipts' => env('OFFICEGUY_BLUESNAP_RECEIPTS', false),
    'other_receipts' => env('OFFICEGUY_OTHER_RECEIPTS'),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    */
    'supported_currencies' => [
        'ILS', 'USD', 'EUR', 'CAD', 'GBP', 'CHF', 'AUD', 'JPY', 'SEK', 'NOK',
        'DKK', 'ZAR', 'JOD', 'LBP', 'EGP', 'BGN', 'CZK', 'HUF', 'PLN', 'RON',
        'ISK', 'HRK', 'RUB', 'TRY', 'BRL', 'CNY', 'HKD', 'IDR', 'INR', 'KRW',
        'MXN', 'MYR', 'NZD', 'PHP', 'SGD', 'THB'
    ],
];
