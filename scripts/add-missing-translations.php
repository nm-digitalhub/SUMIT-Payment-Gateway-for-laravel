<?php

/**
 * Script to add missing translations to OfficeGuySettings.php
 *
 * This script adds ->label() and ->helperText() to fields that don't have them
 */
$filePath = __DIR__ . '/../src/Filament/Pages/OfficeGuySettings.php';
$content = file_get_contents($filePath);

// Pattern 1: Section titles that need translation
$sectionReplacements = [
    "Section::make('Payment Settings')" => "Section::make(__('officeguy::officeguy.settings.payment_configuration'))",
    "Section::make('Tokenization')" => "Section::make(__('officeguy::officeguy.settings.token_configuration'))",
    "Section::make('Customer Merging')" => "Section::make(__('officeguy::officeguy.settings.customer_management'))",
    "Section::make('Logging & SSL')" => "Section::make(__('officeguy::officeguy.settings.logging'))",
    "Section::make('Stock Sync')" => "Section::make(__('officeguy::officeguy.settings.stock_sync'))",
    "Section::make('Receipts')" => "Section::make(__('officeguy::officeguy.settings.receipt_settings'))",
];

foreach ($sectionReplacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

// Pattern 2: Add labels and helper texts to fields without them
$fieldReplacements = [
    // Payment Settings
    "TextInput::make('max_payments')\n                        ->numeric()" => "TextInput::make('max_payments')\n                        ->label(__('officeguy::officeguy.settings.max_payments'))\n                        ->helperText(__('officeguy::officeguy.settings.max_payments_help'))\n                        ->numeric()",

    "Toggle::make('authorize_only')" => "Toggle::make('authorize_only')\n                        ->label(__('officeguy::officeguy.settings.authorize_only'))\n                        ->helperText(__('officeguy::officeguy.settings.authorize_only_help'))",

    "TextInput::make('authorize_added_percent')\n                        ->numeric()" => "TextInput::make('authorize_added_percent')\n                        ->label(__('officeguy::officeguy.settings.authorize_added_percent'))\n                        ->helperText(__('officeguy::officeguy.settings.authorize_added_percent_help'))\n                        ->numeric()",

    "TextInput::make('authorize_minimum_addition')\n                        ->numeric()" => "TextInput::make('authorize_minimum_addition')\n                        ->label(__('officeguy::officeguy.settings.authorize_minimum_addition'))\n                        ->helperText(__('officeguy::officeguy.settings.authorize_minimum_addition_help'))\n                        ->numeric()",

    // Document Settings
    "Toggle::make('draft_document')" => "Toggle::make('draft_document')\n                        ->label(__('officeguy::officeguy.settings.draft_document'))\n                        ->helperText(__('officeguy::officeguy.settings.draft_document_help'))",

    "Toggle::make('email_document')" => "Toggle::make('email_document')\n                        ->label(__('officeguy::officeguy.settings.email_document'))\n                        ->helperText(__('officeguy::officeguy.settings.email_document_help'))",

    "Toggle::make('create_order_document')" => "Toggle::make('create_order_document')\n                        ->label(__('officeguy::officeguy.settings.create_order_document'))\n                        ->helperText(__('officeguy::officeguy.settings.create_order_document_help'))",

    // Tokenization
    "Toggle::make('support_tokens')" => "Toggle::make('support_tokens')\n                        ->label(__('officeguy::officeguy.settings.support_tokens'))\n                        ->helperText(__('officeguy::officeguy.settings.support_tokens_help'))",

    "Select::make('token_param')\n                        ->options([\n                            '2' => 'J2 Method',\n                            '5' => 'J5 Method (Recommended)',\n                        ])" => "Select::make('token_param')\n                        ->label(__('officeguy::officeguy.settings.token_param'))\n                        ->helperText(__('officeguy::officeguy.settings.token_param_help'))\n                        ->options([\n                            '2' => 'J2 (חד פעמי)',\n                            '5' => 'J5 (רב פעמי - מומלץ)',\n                        ])",

    // Bit Payment
    "Toggle::make('bit_enabled')" => "Toggle::make('bit_enabled')\n                        ->label(__('officeguy::officeguy.settings.bit_enabled'))\n                        ->helperText(__('officeguy::officeguy.settings.bit_enabled_help'))",

    // Logging
    "Toggle::make('logging')" => "Toggle::make('logging')\n                        ->label(__('officeguy::officeguy.settings.logging_enabled'))\n                        ->helperText(__('officeguy::officeguy.settings.logging_enabled_help'))",

    "TextInput::make('log_channel')" => "TextInput::make('log_channel')\n                        ->label(__('officeguy::officeguy.settings.log_channel'))\n                        ->helperText(__('officeguy::officeguy.settings.log_channel_help'))\n                        ->placeholder('stack')",

    // Merchant Numbers
    "TextInput::make('merchant_number')" => "TextInput::make('merchant_number')\n                        ->label(__('officeguy::officeguy.settings.merchant_number'))\n                        ->helperText(__('officeguy::officeguy.settings.merchant_number_help'))",

    "TextInput::make('subscriptions_merchant_number')" => "TextInput::make('subscriptions_merchant_number')\n                        ->label(__('officeguy::officeguy.settings.subscriptions_merchant_number'))\n                        ->helperText(__('officeguy::officeguy.settings.subscriptions_merchant_number_help'))",

    // Subscriptions labels
    "'Enable Subscriptions'" => "__('officeguy::officeguy.settings.subscriptions_enabled')",
    "'Default Interval'" => "__('officeguy::officeguy.settings.subscriptions_default_interval')",
    "'Monthly'" => "'חודשי'",
    "'Weekly'" => "'שבועי'",
    "'Yearly'" => "'שנתי'",
    "'Default Cycles'" => "__('officeguy::officeguy.settings.subscriptions_default_cycles')",
    "'Allow Pause'" => "__('officeguy::officeguy.settings.subscriptions_allow_pause')",
    "'Retry Failed'" => "__('officeguy::officeguy.settings.subscriptions_retry_failed')",
    "'Max Retry Attempts'" => "__('officeguy::officeguy.settings.subscriptions_max_retries')",
    "'Configure recurring payment settings'" => "__('officeguy::officeguy.settings.subscriptions')",

    // Donations labels
    "'Enable Donations'" => "__('officeguy::officeguy.settings.donations_enabled')",
    "'Allow Mixed'" => "__('officeguy::officeguy.settings.donations_allow_mixed')",
    "'Document Type'" => "__('officeguy::officeguy.settings.donations_default_document_type')",
    "'Donation Receipt'" => "'קבלה לתרומה'",
    "'Invoice'" => "'חשבונית'",
    "'Configure donation handling'" => "__('officeguy::officeguy.settings.donations')",

    // Multi-Vendor labels
    "'Enable Multi-Vendor'" => "__('officeguy::officeguy.settings.multivendor_enabled')",
    "'Validate Credentials'" => "__('officeguy::officeguy.settings.multivendor_validate_credentials')",
    "'Allow Authorize Only'" => "__('officeguy::officeguy.settings.multivendor_allow_authorize')",
    "'Configure multi-vendor marketplace support'" => "__('officeguy::officeguy.settings.multivendor')",
    "'Allow authorize-only for vendor payments'" => "__('officeguy::officeguy.settings.multivendor_allow_authorize_help')",

    // Upsell labels
    "'Enable Upsell'" => "__('officeguy::officeguy.settings.upsell_enabled')",
    "'Require Token'" => "__('officeguy::officeguy.settings.upsell_require_token')",
    "'Max per Order'" => "__('officeguy::officeguy.settings.upsell_max_per_order')",
    "'Configure upsell payment settings'" => "__('officeguy::officeguy.settings.upsell')",
    "'Require payment token for upsell payments'" => "__('officeguy::officeguy.settings.upsell_require_token_help')",
    "'Maximum upsell items per order'" => "__('officeguy::officeguy.settings.upsell_max_per_order_help')",

    // Public Checkout labels
    "'Enable Public Checkout'" => "__('officeguy::officeguy.settings.enable_public_checkout')",
    "'Public Checkout Path'" => "__('officeguy::officeguy.settings.public_checkout_path')",
    "'Payable Model'" => "__('officeguy::officeguy.settings.payable_model')",
    "'Configure the public checkout page for payment links'" => "__('officeguy::officeguy.settings.public_checkout')",
    "'Allow public access to checkout page via /officeguy/checkout/{id}'" => "__('officeguy::officeguy.settings.enable_public_checkout_help')",
    "'URL path for public checkout (without leading slash)'" => "__('officeguy::officeguy.settings.public_checkout_path_help')",
    "'Eloquent model implementing Payable contract (e.g., App\\\\Models\\\\Order)'" => "__('officeguy::officeguy.settings.payable_model_help')",

    // Field Mapping labels
    "'Amount Field'" => "__('officeguy::officeguy.settings.field_map_amount')",
    "'Currency Field'" => "__('officeguy::officeguy.settings.field_map_currency')",
    "'Customer Name Field'" => "__('officeguy::officeguy.settings.field_map_customer_name')",
    "'Customer Email Field'" => "__('officeguy::officeguy.settings.field_map_customer_email')",
    "'Customer Phone Field'" => "__('officeguy::officeguy.settings.field_map_customer_phone')",
    "'Description Field'" => "__('officeguy::officeguy.settings.field_map_description')",

    // Customer Merging labels
    "'Customer Sync Enabled'" => "__('officeguy::officeguy.settings.customer_sync_enabled')",
    "'Customer Model'" => "__('officeguy::officeguy.settings.customer_model')",
    "'Email Field'" => "__('officeguy::officeguy.settings.customer_field_email')",
    "'Name Field'" => "__('officeguy::officeguy.settings.customer_field_name')",
    "'Phone Field'" => "__('officeguy::officeguy.settings.customer_field_phone')",
    "'First Name Field'" => "__('officeguy::officeguy.settings.customer_field_first_name')",
    "'Last Name Field'" => "__('officeguy::officeguy.settings.customer_field_last_name')",
    "'Company Field'" => "__('officeguy::officeguy.settings.customer_field_company')",
    "'Address Field'" => "__('officeguy::officeguy.settings.customer_field_address')",
    "'City Field'" => "__('officeguy::officeguy.settings.customer_field_city')",
    "'SUMIT ID Field'" => "__('officeguy::officeguy.settings.customer_field_sumit_id')",
    "'Configure automatic customer merging with SUMIT and sync with your local customer model.'" => "__('officeguy::officeguy.settings.customer_management')",

    // Route Configuration labels
    "'Route Prefix'" => "__('officeguy::officeguy.settings.routes_prefix')",
    "'Card Callback Route'" => "__('officeguy::officeguy.settings.routes_card_callback')",
    "'Bit Webhook Route'" => "__('officeguy::officeguy.settings.routes_bit_webhook')",
    "'SUMIT Webhook Route'" => "__('officeguy::officeguy.settings.routes_sumit_webhook')",
    "'Enable Checkout Endpoint'" => "__('officeguy::officeguy.settings.routes_enable_checkout_endpoint')",
    "'Checkout Charge Route'" => "__('officeguy::officeguy.settings.routes_checkout_charge')",
    "'Document Download Route'" => "__('officeguy::officeguy.settings.routes_document_download')",
    "'Success Redirect URL'" => "__('officeguy::officeguy.settings.routes_success')",
    "'Failed Redirect URL'" => "__('officeguy::officeguy.settings.routes_failed')",
    "'Customize all package endpoints. Changes require cache clear to take effect. Run: php artisan route:clear'" => "__('officeguy::officeguy.settings.route_configuration')",

    // Webhook labels
    "'Webhook Secret'" => "__('officeguy::officeguy.settings.webhook_secret')",
    "'Payment Completed URL'" => "__('officeguy::officeguy.settings.webhook_payment_completed')",
    "'Payment Failed URL'" => "__('officeguy::officeguy.settings.webhook_payment_failed')",
    "'Document Created URL'" => "__('officeguy::officeguy.settings.webhook_document_created')",
    "'Subscription Created URL'" => "__('officeguy::officeguy.settings.webhook_subscription_created')",
    "'Subscription Charged URL'" => "__('officeguy::officeguy.settings.webhook_subscription_charged')",
    "'Bit Payment Completed URL'" => "__('officeguy::officeguy.settings.webhook_bit_payment_completed')",
    "'Stock Synced URL'" => "__('officeguy::officeguy.settings.webhook_stock_synced')",
    "'Configure webhook URLs to receive notifications when events occur. Leave empty to disable.'" => "__('officeguy::officeguy.settings.custom_webhooks')",
];

foreach ($fieldReplacements as $old => $new) {
    $content = str_replace($old, $new, $content);
}

// Write back
file_put_contents($filePath, $content);

echo "✅ Missing translations added to OfficeGuySettings.php\n";
echo 'Total replacements: ' . (count($sectionReplacements) + count($fieldReplacements)) . "\n";
