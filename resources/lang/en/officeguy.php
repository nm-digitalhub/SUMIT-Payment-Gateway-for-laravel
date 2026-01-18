<?php

/**
 * English Translations for SUMIT Payment Gateway
 *
 * Usage in Filament:
 * protected static ?string $navigationLabel = __('officeguy::officeguy.nav.transactions');
 *
 * Usage in Blade:
 * {{ __('officeguy::officeguy.messages.payment_success') }}
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Labels
    |--------------------------------------------------------------------------
    */
    'nav' => [
        'group' => 'SUMIT Gateway',
        'transactions' => 'Transactions',
        'tokens' => 'Payment Methods',
        'documents' => 'Documents',
        'subscriptions' => 'Subscriptions',
        'vendor_credentials' => 'Vendor Credentials',
        'webhook_events' => 'Webhook Events',
        'sumit_webhooks' => 'SUMIT Webhooks',
        'settings' => 'Settings',
        'dashboard' => 'Dashboard',
        'my_payment_methods' => 'My Payment Methods',
        'my_transactions' => 'My Transactions',
        'my_documents' => 'My Documents',
        'payments_group' => 'Payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Page - Sections
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'page_title' => 'SUMIT Settings',

        // API Credentials
        'api_credentials' => 'API Credentials',
        'company_id' => 'Company ID',
        'private_key' => 'Private Key',
        'public_key' => 'Public Key',

        // Environment
        'environment_settings' => 'Environment Settings',
        'environment' => 'Environment',
        'environment_production' => 'Production (www)',
        'environment_development' => 'Development (dev)',
        'environment_testing' => 'Testing (test)',
        'pci_mode' => 'PCI Mode',
        'pci_simple' => 'Simple (PaymentsJS)',
        'pci_redirect' => 'Redirect',
        'pci_advanced' => 'Advanced (PCI-compliant)',
        'testing_mode' => 'Testing Mode',
        'pci_mode_label' => 'PCI Compliance Mode',

        // Payment Configuration
        'payment_configuration' => 'Payment Configuration',
        'max_payments' => 'Maximum Installments',
        'max_payments_help' => 'Maximum number of installments (1-36)',
        'min_amount_for_payments' => 'Minimum Amount for Installments',
        'min_amount_for_payments_help' => 'Minimum amount to enable installments (₪)',
        'min_amount_per_payment' => 'Minimum Amount per Installment',
        'min_amount_per_payment_help' => 'Minimum amount per single installment (₪)',
        'authorize_only' => 'Authorize Only (No Charge)',
        'authorize_only_help' => 'Authorize payments without immediate charge',
        'authorize_added_percent' => 'Authorization Added Percent',
        'authorize_added_percent_help' => 'Extra percent for authorization (e.g., 20%)',
        'authorize_minimum_addition' => 'Authorization Minimum Addition',
        'authorize_minimum_addition_help' => 'Minimum amount to add for authorization (₪)',

        // Merchant Numbers
        'merchant_numbers' => 'Merchant Numbers',
        'merchant_number' => 'Primary Merchant Number',
        'merchant_number_help' => 'Merchant number for regular transactions',
        'subscriptions_merchant_number' => 'Subscriptions Merchant Number',
        'subscriptions_merchant_number_help' => 'Dedicated merchant number for recurring subscriptions',

        // Document Settings
        'document_settings' => 'Document Settings',
        'draft_document' => 'Draft Document',
        'draft_document_help' => 'Document type for drafts',
        'email_document' => 'Email Document',
        'email_document_help' => 'Send documents automatically to customers',
        'create_order_document' => 'Create Order Document',
        'create_order_document_help' => 'Automatically create invoice/receipt document',

        // Language & Localization
        'language_localization' => 'Language & Localization',
        'automatic_languages' => 'Automatic Languages',
        'automatic_languages_help' => 'Auto-detect customer language',

        // Customer Management
        'customer_management' => 'Customer Management',
        'merge_customers' => 'Merge Customers',
        'merge_customers_help' => 'Automatically merge duplicate customers',
        'customer_sync_enabled' => 'Customer Sync Enabled',
        'customer_model' => 'Customer Model',
        'customer_field_email' => 'Email Field',
        'customer_field_name' => 'Name Field',
        'customer_field_phone' => 'Phone Field',
        'customer_field_first_name' => 'First Name Field',
        'customer_field_last_name' => 'Last Name Field',
        'customer_field_company' => 'Company Field',
        'customer_field_address' => 'Address Field',
        'customer_field_city' => 'City Field',
        'customer_field_sumit_id' => 'SUMIT ID Field',

        // Token Configuration
        'token_configuration' => 'Token Configuration',
        'support_tokens' => 'Support Tokens',
        'support_tokens_help' => 'Enable saved payment methods (J2/J5)',
        'token_param' => 'Token Parameter',
        'token_param_help' => 'Token type: 2 (J2 - single-use), 5 (J5 - multi-use)',
        'citizen_id' => 'Require Citizen ID',
        'citizen_id_help' => 'Request citizen ID from customer',
        'cvv' => 'Require CVV',
        'cvv_help' => 'Request CVV code for payments',
        'four_digits_year' => '4-Digit Year',
        'four_digits_year_help' => 'Use 4-digit year format (2025)',

        // UI Settings
        'ui_settings' => 'UI Settings',
        'single_column_layout' => 'Single Column Layout',
        'single_column_layout_help' => 'Display fields in single column',

        // Bit Payment
        'bit_payment' => 'Bit Payment',
        'bit_enabled' => 'Enable Bit',
        'bit_enabled_help' => 'Enable payments via Bit app',

        // Logging
        'logging' => 'Logging',
        'logging_enabled' => 'Logging Enabled',
        'logging_enabled_help' => 'Log all API calls and transactions',
        'log_channel' => 'Log Channel',
        'log_channel_help' => 'Laravel channel for logging (stack, single, daily)',
        'enable_notifications' => 'Enable Notifications',
        'enable_notifications_help' => 'Send notifications to users about payments, subscriptions, and documents',

        // SSL
        'ssl_settings' => 'SSL Settings',
        'ssl_verify' => 'SSL Verification',
        'ssl_verify_help' => 'Verify SSL certificates in API calls (recommended for production)',

        // Stock Sync
        'stock_sync' => 'Stock Synchronization',
        'stock_sync_freq' => 'Sync Frequency',
        'stock_sync_freq_help' => 'Automatic stock sync frequency',
        'stock_sync_none' => 'None',
        'stock_sync_12h' => 'Every 12 Hours',
        'stock_sync_24h' => 'Daily',
        'checkout_stock_sync' => 'Checkout Stock Sync',
        'checkout_stock_sync_help' => 'Sync stock during checkout process',

        // Receipt Settings
        'receipt_settings' => 'Receipt Settings',
        'paypal_receipts' => 'PayPal Receipts',
        'paypal_receipts_help' => 'Create receipts for PayPal payments',
        'bluesnap_receipts' => 'BlueSnap Receipts',
        'bluesnap_receipts_help' => 'Create receipts for BlueSnap payments',
        'other_receipts' => 'Other Receipts',
        'other_receipts_help' => 'Create receipts for other payment methods',

        // Public Checkout
        'public_checkout' => 'Public Checkout Page',
        'enable_public_checkout' => 'Enable Public Checkout',
        'enable_public_checkout_help' => 'Enable public checkout page with unique URL',
        'public_checkout_path' => 'Public Checkout Path',
        'public_checkout_path_help' => 'URL path for public checkout page',
        'payable_model' => 'Payable Model',
        'payable_model_help' => 'Eloquent model implementing Payable',

        // Field Mapping
        'field_mapping' => 'Field Mapping',
        'field_map_amount' => 'Amount Field',
        'field_map_currency' => 'Currency Field',
        'field_map_customer_name' => 'Customer Name Field',
        'field_map_customer_email' => 'Customer Email Field',
        'field_map_customer_phone' => 'Customer Phone Field',
        'field_map_description' => 'Description Field',

        // Webhooks (v1.2.0+)
        'webhook_configuration' => 'Webhook System Configuration (v1.2.0+)',
        'webhook_configuration_desc' => 'Configure how webhooks are delivered. These settings control the queue-based webhook system introduced in v1.2.0.',
        'webhook_async' => 'Async Delivery (Queue-based)',
        'webhook_async_help' => 'Enable async webhook delivery via Laravel queues (recommended for production)',
        'webhook_queue' => 'Queue Name',
        'webhook_queue_help' => 'Name of the queue to use for webhook jobs',
        'webhook_max_tries' => 'Maximum Retry Attempts',
        'webhook_max_tries_help' => 'Number of times to retry failed webhooks (exponential backoff: 10s, 100s, 1000s)',
        'webhook_timeout' => 'Request Timeout (seconds)',
        'webhook_timeout_help' => 'HTTP request timeout in seconds',
        'webhook_verify_ssl' => 'Verify SSL Certificates',
        'webhook_verify_ssl_help' => 'Verify SSL certificates when sending webhooks (disable for testing only)',

        // Custom Event Webhooks
        'custom_webhooks' => 'Custom Event Webhooks',
        'webhook_payment_completed' => 'Payment Completed',
        'webhook_payment_completed_help' => 'Webhook URL when payment is completed',
        'webhook_payment_failed' => 'Payment Failed',
        'webhook_payment_failed_help' => 'Webhook URL when payment fails',
        'webhook_document_created' => 'Document Created',
        'webhook_document_created_help' => 'Webhook URL when document is created',
        'webhook_subscription_created' => 'Subscription Created',
        'webhook_subscription_created_help' => 'Webhook URL when subscription is created',
        'webhook_subscription_charged' => 'Subscription Charged',
        'webhook_subscription_charged_help' => 'Webhook URL when subscription is charged',
        'webhook_bit_payment_completed' => 'Bit Payment Completed',
        'webhook_bit_payment_completed_help' => 'Webhook URL when Bit payment is completed',
        'webhook_stock_synced' => 'Stock Synced',
        'webhook_stock_synced_help' => 'Webhook URL when stock is synced',
        'webhook_secret' => 'Webhook Secret',
        'webhook_secret_help' => 'Secret key for webhook signature validation',

        // Customer Management (v1.2.4+)
        'customer_management' => 'Customer Management',
        'customer_management_desc' => 'Configure how customers are synced and managed with SUMIT',
        'customer_merging_enabled' => 'Enable Customer Merging',
        'customer_merging_enabled_help' => 'When enabled, SUMIT will automatically merge customers by email/ID to prevent duplicates',
        'customer_local_sync_enabled' => 'Enable Local Customer Sync',
        'customer_local_sync_enabled_help' => 'Sync SUMIT customers with your local customer model',
        'customer_model_class' => 'Customer Model Class',
        'customer_model_class_help' => 'Full class name of your customer model (e.g., App\\Models\\Client)',

        // Route Configuration
        'route_configuration' => 'Route Configuration',
        'routes_prefix' => 'Routes Prefix',
        'routes_prefix_help' => 'Prefix for all package routes (default: officeguy)',
        'routes_card_callback' => 'Card Callback Route',
        'routes_card_callback_help' => 'Route for card payment callback',
        'routes_bit_webhook' => 'Bit Webhook Route',
        'routes_bit_webhook_help' => 'Route for Bit IPN webhooks',
        'routes_sumit_webhook' => 'SUMIT Webhook Route',
        'routes_sumit_webhook_help' => 'Route for incoming SUMIT webhooks',
        'routes_enable_checkout_endpoint' => 'Enable Checkout Endpoint',
        'routes_enable_checkout_endpoint_help' => 'Enable public checkout endpoint',
        'routes_checkout_charge' => 'Checkout Charge Route',
        'routes_checkout_charge_help' => 'Route for direct charge',
        'routes_document_download' => 'Document Download Route',
        'routes_document_download_help' => 'Route for document downloads',
        'routes_success' => 'Success Route',
        'routes_success_help' => 'URL to redirect after successful payment',
        'routes_failed' => 'Failed Route',
        'routes_failed_help' => 'URL to redirect after failed payment',

        // Subscriptions
        'subscriptions' => 'Subscriptions',
        'subscriptions_enabled' => 'Subscriptions Enabled',
        'subscriptions_enabled_help' => 'Enable recurring subscription support',
        'subscriptions_default_interval' => 'Default Interval',
        'subscriptions_default_interval_help' => 'Default billing interval (monthly, weekly)',
        'subscriptions_default_cycles' => 'Default Cycles',
        'subscriptions_default_cycles_help' => 'Number of billing cycles (0 = infinite)',
        'subscriptions_allow_pause' => 'Allow Pause',
        'subscriptions_allow_pause_help' => 'Allow customers to pause subscriptions',
        'subscriptions_retry_failed' => 'Retry Failed Subscriptions',
        'subscriptions_retry_failed_help' => 'Automatically retry failed subscription charges',
        'subscriptions_max_retries' => 'Maximum Retries',
        'subscriptions_max_retries_help' => 'Number of retry attempts for failed subscriptions',

        // Donations
        'donations' => 'Donations',
        'donations_enabled' => 'Donations Enabled',
        'donations_enabled_help' => 'Enable donation support',
        'donations_allow_mixed' => 'Allow Mixed',
        'donations_allow_mixed_help' => 'Allow donations mixed with purchases',
        'donations_default_document_type' => 'Default Document Type',
        'donations_default_document_type_help' => 'Document type for donations (receipt, invoice)',

        // Multi-Vendor
        'multivendor' => 'Multi-Vendor',
        'multivendor_enabled' => 'Multi-Vendor Enabled',
        'multivendor_enabled_help' => 'Enable multi-vendor support',
        'multivendor_validate_credentials' => 'Validate Credentials',
        'multivendor_validate_credentials_help' => 'Validate vendor credentials on save',
        'multivendor_allow_authorize' => 'Allow Authorize',
        'multivendor_allow_authorize_help' => 'Allow authorize-only mode for vendors',

        // Upsell / CartFlows
        'upsell' => 'Upsell / CartFlows',
        'upsell_enabled' => 'Upsell Enabled',
        'upsell_enabled_help' => 'Enable upsell features (CartFlows)',
        'upsell_require_token' => 'Require Token',
        'upsell_require_token_help' => 'Require payment token for upsells',
        'upsell_max_per_order' => 'Maximum per Order',
        'upsell_max_per_order_help' => 'Maximum number of upsells per order',

        // Actions
        'save' => 'Save Settings',
        'test_connection' => 'Test Connection',
        'reset_to_defaults' => 'Reset to Defaults',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages & Notifications
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'payment_success' => 'Payment completed successfully',
        'payment_failed' => 'Payment failed',
        'payment_pending' => 'Payment is pending approval',
        'token_saved' => 'Payment method saved successfully',
        'token_deleted' => 'Payment method deleted',
        'document_created' => 'Document created successfully',
        'document_sent' => 'Document sent to customer',
        'subscription_created' => 'Subscription created successfully',
        'subscription_cancelled' => 'Subscription cancelled',
        'subscription_paused' => 'Subscription paused',
        'subscription_resumed' => 'Subscription resumed',
        'settings_saved' => 'Settings saved successfully',
        'settings_reset' => 'Settings reset to defaults',
        'connection_success' => 'Connection to SUMIT API successful',
        'connection_failed' => 'Connection to SUMIT API failed',
        'webhook_sent' => 'Webhook sent successfully',
        'webhook_failed' => 'Webhook delivery failed',
        'stock_synced' => 'Stock synchronized successfully',
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Labels
    |--------------------------------------------------------------------------
    */
    'status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'paused' => 'Paused',
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Labels (Common)
    |--------------------------------------------------------------------------
    */
    'fields' => [
        'id' => 'ID',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'amount' => 'Amount',
        'currency' => 'Currency',
        'status' => 'Status',
        'transaction_id' => 'Transaction ID',
        'token' => 'Token',
        'description' => 'Description',
        'customer_name' => 'Customer Name',
        'customer_email' => 'Customer Email',
        'customer_phone' => 'Customer Phone',
        'card_last_4' => 'Last 4 Digits',
        'card_type' => 'Card Type',
        'expiry_date' => 'Expiry Date',
        'document_number' => 'Document Number',
        'document_type' => 'Document Type',
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'donation_receipt' => 'Donation Receipt',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions & Buttons
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'view' => 'View',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'back' => 'Back',
        'download' => 'Download',
        'send' => 'Send',
        'retry' => 'Retry',
        'refund' => 'Refund',
        'capture' => 'Capture',
        'pause' => 'Pause',
        'resume' => 'Resume',
        'test_connection' => 'Test Connection',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'required' => 'The :attribute field is required.',
        'numeric' => 'The :attribute field must be a number.',
        'email' => 'The :attribute field must be a valid email address.',
        'url' => 'The :attribute field must be a valid URL.',
        'min' => 'The :attribute field must be at least :min.',
        'max' => 'The :attribute field may not be greater than :max.',
        'between' => 'The :attribute field must be between :min and :max.',
    ],

    'about' => [
        // Page Titles
        'title_description' => 'Description',
        'title_features' => 'Key Features',
        'title_packagist' => 'Packagist Statistics',
        'title_saloon_migration' => 'Saloon PHP v2.0.0 Upgrade',
        'title_technology' => 'Technology Stack',
        'title_credits' => 'Credits',
        'title_links' => 'Important Links',

        // Description
        'description' => 'Official Laravel package for SUMIT payment gateway integration. Includes full Filament v4 support, payment management, tokens, documents, subscriptions, webhooks, and more. Built by NM-DigitalHub with focus on quality, security, and maintainability.',

        // Features
        'features' => [
            'credit_card_payments' => 'Credit card payments (3 PCI modes)',
            'bit_integration' => 'Bit payment integration',
            'token_management' => 'Token management (J2/J5)',
            'document_generation' => 'Document generation (invoices/receipts)',
            'recurring_billing' => 'Recurring billing',
            'multi_vendor' => 'Multi-vendor support',
            'webhook_handling' => 'Webhook handling (incoming + outgoing)',
            'filament_integration' => 'Full Filament v4 integration',
        ],

        // Packagist Statistics
        'stats' => [
            'total_downloads' => 'Total Downloads',
            'monthly_downloads' => 'Monthly Downloads',
            'daily_downloads' => 'Daily Downloads',
            'favers' => 'Favorites',
            'github_stars' => 'GitHub Stars',
            'github_watchers' => 'GitHub Watchers',
            'github_forks' => 'GitHub Forks',
            'updated_hourly' => 'Updated hourly',
        ],

        // Database Notifications
        'notifications' => [
            'payment_completed' => [
                'title' => 'Payment Completed Successfully',
                'message' => 'Payment of :amount ILS for order :order_id was completed successfully',
                'view_transaction' => 'View Transaction',
            ],
            'payment_failed' => [
                'title' => 'Payment Failed',
                'message' => 'Payment of :amount ILS for order :order_id failed. Reason: :error',
                'unknown_error' => 'Unknown error',
            ],
            'subscription_created' => [
                'title' => 'Subscription Created Successfully',
                'message' => 'Monthly subscription of :amount ILS (:interval) was created successfully',
                'view_subscription' => 'View Subscription',
            ],
            'document_created' => [
                'title' => 'Document Created Successfully',
                'message' => 'Document :document_type (:document_number) was created successfully',
                'view_document' => 'View Document',
                'download_document' => 'Download Document',
            ],
        ],

        // Saloon Highlights
        'saloon' => [
            'refactored_services' => '13 API services refactored to Saloon PHP',
            'type_safety' => 'Full type safety with readonly properties',
            'testability' => 'Easy testing with mocking support',
            'middleware_support' => 'Middleware support (Logging, Authentication)',
            'backward_compatible' => '100% backward compatibility at service layer',
        ],

        // Footer
        'footer_text' => 'Built with ❤️ by NM-DigitalHub • MIT License',
        'footer_support' => 'Support: info@nm-digitalhub.com',
    ],
];
