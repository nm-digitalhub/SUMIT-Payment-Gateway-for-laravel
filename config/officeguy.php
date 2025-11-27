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
    'pci_mode' => env('OFFICEGUY_PCI_MODE', 'no'), // alias for backward compatibility

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
    | Customer Management (v1.2.4+)
    |--------------------------------------------------------------------------
    |
    | Configure customer synchronization and merging with SUMIT.
    | These settings can be managed via Admin Panel → Office Guy Settings → Customer Management
    |
    */
    'customer_merging_enabled' => env('OFFICEGUY_CUSTOMER_MERGING_ENABLED', false),
    'customer_local_sync_enabled' => env('OFFICEGUY_CUSTOMER_LOCAL_SYNC_ENABLED', false),
    'customer_model_class' => env('OFFICEGUY_CUSTOMER_MODEL_CLASS', 'App\\Models\\Client'),

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
    | SSL verification
    |--------------------------------------------------------------------------
    */
    'ssl_verify' => env('OFFICEGUY_SSL_VERIFY', true),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure callback and webhook URLs.
    | All paths can be customized via Admin Panel (Gateway Settings > Route Configuration)
    |
    */
    'routes' => [
        'prefix' => env('OFFICEGUY_ROUTE_PREFIX', 'officeguy'),
        'middleware' => ['web'],
        'card_callback' => env('OFFICEGUY_CARD_CALLBACK_PATH', 'callback/card'),
        'bit_webhook' => env('OFFICEGUY_BIT_WEBHOOK_PATH', 'webhook/bit'),
        'sumit_webhook' => env('OFFICEGUY_SUMIT_WEBHOOK_PATH', 'webhook/sumit'),
        'document_download' => env('OFFICEGUY_DOCUMENT_DOWNLOAD_PATH', 'documents/{document}'),
        'success' => env('OFFICEGUY_SUCCESS_ROUTE', 'checkout.success'),
        'failed' => env('OFFICEGUY_FAILED_ROUTE', 'checkout.failed'),
        'enable_checkout_endpoint' => env('OFFICEGUY_ENABLE_CHECKOUT_ROUTE', false),
        'checkout_charge' => env('OFFICEGUY_CHECKOUT_CHARGE_PATH', 'checkout/charge'),
        'enable_public_checkout' => env('OFFICEGUY_ENABLE_PUBLIC_CHECKOUT', false),
        'public_checkout' => env('OFFICEGUY_PUBLIC_CHECKOUT_PATH', 'checkout/{id}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Order binding
    |--------------------------------------------------------------------------
    | Provide either a resolver callable or a model class implementing Payable
    */
    'order' => [
        'resolver' => null, // fn(string|int $orderId): ?Payable
        'model' => env('OFFICEGUY_ORDER_MODEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Management
    |--------------------------------------------------------------------------
    */
    'stock_sync_freq' => env('OFFICEGUY_STOCK_SYNC_FREQ', 'none'), // 'none', '12', '24'
    'checkout_stock_sync' => env('OFFICEGUY_CHECKOUT_STOCK_SYNC', false),
    'stock' => [
        'update_callback' => null, // callable(array $stockItem)
    ],

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
    | Subscription Settings
    |--------------------------------------------------------------------------
    */
    'subscriptions' => [
        'enabled' => env('OFFICEGUY_SUBSCRIPTIONS_ENABLED', true),
        'default_interval_months' => env('OFFICEGUY_SUBSCRIPTIONS_DEFAULT_INTERVAL', 1),
        'default_cycles' => env('OFFICEGUY_SUBSCRIPTIONS_DEFAULT_CYCLES'), // null = unlimited
        'allow_pause' => env('OFFICEGUY_SUBSCRIPTIONS_ALLOW_PAUSE', true),
        'retry_failed_charges' => env('OFFICEGUY_SUBSCRIPTIONS_RETRY_FAILED', true),
        'max_retry_attempts' => env('OFFICEGUY_SUBSCRIPTIONS_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Donation Settings
    |--------------------------------------------------------------------------
    */
    'donations' => [
        'enabled' => env('OFFICEGUY_DONATIONS_ENABLED', true),
        'allow_mixed_cart' => env('OFFICEGUY_DONATIONS_ALLOW_MIXED', false), // Allow donations + regular products
        'default_document_type' => env('OFFICEGUY_DONATIONS_DOCUMENT_TYPE', '320'), // DonationReceipt
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Vendor Settings
    |--------------------------------------------------------------------------
    */
    'multivendor' => [
        'enabled' => env('OFFICEGUY_MULTIVENDOR_ENABLED', false),
        'validate_credentials' => env('OFFICEGUY_MULTIVENDOR_VALIDATE_CREDENTIALS', true),
        'allow_authorize_only' => env('OFFICEGUY_MULTIVENDOR_ALLOW_AUTHORIZE', false),
        'vendor_resolver' => null, // callable(array $item): mixed - returns vendor for item
    ],

    /*
    |--------------------------------------------------------------------------
    | Upsell / CartFlows Settings
    |--------------------------------------------------------------------------
    */
    'upsell' => [
        'enabled' => env('OFFICEGUY_UPSELL_ENABLED', true),
        'require_token' => env('OFFICEGUY_UPSELL_REQUIRE_TOKEN', true),
        'max_upsells_per_order' => env('OFFICEGUY_UPSELL_MAX_PER_ORDER', 5),
    ],

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
