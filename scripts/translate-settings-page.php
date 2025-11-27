<?php

/**
 * Script to update OfficeGuySettings.php with translation function calls
 *
 * Usage: php scripts/translate-settings-page.php
 */

$filePath = __DIR__ . '/../src/Filament/Pages/OfficeGuySettings.php';
$content = file_get_contents($filePath);

// Mapping of English strings to translation keys
$translations = [
    // Sections
    "'API Credentials'" => "__('officeguy::officeguy.settings.api_credentials')",
    "'Environment Settings'" => "__('officeguy::officeguy.settings.environment_settings')",
    "'Payment Configuration'" => "__('officeguy::officeguy.settings.payment_configuration')",
    "'Merchant Numbers'" => "__('officeguy::officeguy.settings.merchant_numbers')",
    "'Document Settings'" => "__('officeguy::officeguy.settings.document_settings')",
    "'Language & Localization'" => "__('officeguy::officeguy.settings.language_localization')",
    "'Customer Management'" => "__('officeguy::officeguy.settings.customer_management')",
    "'Token Configuration'" => "__('officeguy::officeguy.settings.token_configuration')",
    "'UI Settings'" => "__('officeguy::officeguy.settings.ui_settings')",
    "'Bit Payment'" => "__('officeguy::officeguy.settings.bit_payment')",
    "'Logging'" => "__('officeguy::officeguy.settings.logging')",
    "'SSL Settings'" => "__('officeguy::officeguy.settings.ssl_settings')",
    "'Stock Synchronization'" => "__('officeguy::officeguy.settings.stock_sync')",
    "'Receipt Settings'" => "__('officeguy::officeguy.settings.receipt_settings')",
    "'Public Checkout Page'" => "__('officeguy::officeguy.settings.public_checkout')",
    "'Field Mapping'" => "__('officeguy::officeguy.settings.field_mapping')",
    "'Webhook System Configuration (v1.2.0+)'" => "__('officeguy::officeguy.settings.webhook_configuration')",
    "'Custom Event Webhooks'" => "__('officeguy::officeguy.settings.custom_webhooks')",
    "'Route Configuration'" => "__('officeguy::officeguy.settings.route_configuration')",
    "'Subscriptions'" => "__('officeguy::officeguy.settings.subscriptions')",
    "'Donations'" => "__('officeguy::officeguy.settings.donations')",
    "'Multi-Vendor'" => "__('officeguy::officeguy.settings.multivendor')",
    "'Upsell / CartFlows'" => "__('officeguy::officeguy.settings.upsell')",

    // Labels
    "'Company ID'" => "__('officeguy::officeguy.settings.company_id')",
    "'Private Key'" => "__('officeguy::officeguy.settings.private_key')",
    "'Public Key'" => "__('officeguy::officeguy.settings.public_key')",
    "'Environment'" => "__('officeguy::officeguy.settings.environment')",
    "'Production (www)'" => "__('officeguy::officeguy.settings.environment_production')",
    "'Development (dev)'" => "__('officeguy::officeguy.settings.environment_development')",
    "'Testing (test)'" => "__('officeguy::officeguy.settings.environment_testing')",
    "'PCI Mode'" => "__('officeguy::officeguy.settings.pci_mode')",
    "'Simple (PaymentsJS)'" => "__('officeguy::officeguy.settings.pci_simple')",
    "'Redirect'" => "__('officeguy::officeguy.settings.pci_redirect')",
    "'Advanced (PCI-compliant)'" => "__('officeguy::officeguy.settings.pci_advanced')",
    "'Testing Mode'" => "__('officeguy::officeguy.settings.testing_mode')",
    "'Maximum Installments'" => "__('officeguy::officeguy.settings.max_payments')",
    "'Minimum Amount for Installments'" => "__('officeguy::officeguy.settings.min_amount_for_payments')",
    "'Minimum Amount per Installment'" => "__('officeguy::officeguy.settings.min_amount_per_payment')",
    "'Authorize Only (No Charge)'" => "__('officeguy::officeguy.settings.authorize_only')",
    "'Authorization Added Percent'" => "__('officeguy::officeguy.settings.authorize_added_percent')",
    "'Authorization Minimum Addition'" => "__('officeguy::officeguy.settings.authorize_minimum_addition')",
    "'Primary Merchant Number'" => "__('officeguy::officeguy.settings.merchant_number')",
    "'Subscriptions Merchant Number'" => "__('officeguy::officeguy.settings.subscriptions_merchant_number')",
    "'Draft Document'" => "__('officeguy::officeguy.settings.draft_document')",
    "'Email Document'" => "__('officeguy::officeguy.settings.email_document')",
    "'Create Order Document'" => "__('officeguy::officeguy.settings.create_order_document')",
    "'Automatic Languages'" => "__('officeguy::officeguy.settings.automatic_languages')",
    "'Merge Customers'" => "__('officeguy::officeguy.settings.merge_customers')",
    "'Customer Sync Enabled'" => "__('officeguy::officeguy.settings.customer_sync_enabled')",
    "'Support Tokens'" => "__('officeguy::officeguy.settings.support_tokens')",
    "'Token Parameter'" => "__('officeguy::officeguy.settings.token_param')",
    "'Require Citizen ID'" => "__('officeguy::officeguy.settings.citizen_id')",
    "'Require CVV'" => "__('officeguy::officeguy.settings.cvv')",
    "'4-Digit Year'" => "__('officeguy::officeguy.settings.four_digits_year')",
    "'Single Column Layout'" => "__('officeguy::officeguy.settings.single_column_layout')",
    "'Enable Bit'" => "__('officeguy::officeguy.settings.bit_enabled')",
    "'Logging Enabled'" => "__('officeguy::officeguy.settings.logging_enabled')",
    "'Log Channel'" => "__('officeguy::officeguy.settings.log_channel')",
    "'SSL Verification'" => "__('officeguy::officeguy.settings.ssl_verify')",
    "'Async Delivery (Queue-based)'" => "__('officeguy::officeguy.settings.webhook_async')",
    "'Queue Name'" => "__('officeguy::officeguy.settings.webhook_queue')",
    "'Maximum Retry Attempts'" => "__('officeguy::officeguy.settings.webhook_max_tries')",
    "'Request Timeout (seconds)'" => "__('officeguy::officeguy.settings.webhook_timeout')",
    "'Verify SSL Certificates'" => "__('officeguy::officeguy.settings.webhook_verify_ssl')",

    // Helper texts (descriptions)
    "'Maximum number of installments (1-36)'" => "__('officeguy::officeguy.settings.max_payments_help')",
    "'Minimum amount to enable installments (₪)'" => "__('officeguy::officeguy.settings.min_amount_for_payments_help')",
    "'Minimum amount per single installment (₪)'" => "__('officeguy::officeguy.settings.min_amount_per_payment_help')",
    "'Authorize payments without immediate charge'" => "__('officeguy::officeguy.settings.authorize_only_help')",
    "'Extra percent for authorization (e.g., 20%)'" => "__('officeguy::officeguy.settings.authorize_added_percent_help')",
    "'Minimum amount to add for authorization (₪)'" => "__('officeguy::officeguy.settings.authorize_minimum_addition_help')",
    "'Merchant number for regular transactions'" => "__('officeguy::officeguy.settings.merchant_number_help')",
    "'Dedicated merchant number for recurring subscriptions'" => "__('officeguy::officeguy.settings.subscriptions_merchant_number_help')",
    "'Document type for drafts'" => "__('officeguy::officeguy.settings.draft_document_help')",
    "'Send documents automatically to customers'" => "__('officeguy::officeguy.settings.email_document_help')",
    "'Automatically create invoice/receipt document'" => "__('officeguy::officeguy.settings.create_order_document_help')",
    "'Auto-detect customer language'" => "__('officeguy::officeguy.settings.automatic_languages_help')",
    "'Automatically merge duplicate customers'" => "__('officeguy::officeguy.settings.merge_customers_help')",
    "'Enable saved payment methods (J2/J5)'" => "__('officeguy::officeguy.settings.support_tokens_help')",
    "'Token type: 2 (J2 - single-use), 5 (J5 - multi-use)'" => "__('officeguy::officeguy.settings.token_param_help')",
    "'Request citizen ID from customer'" => "__('officeguy::officeguy.settings.citizen_id_help')",
    "'Request CVV code for payments'" => "__('officeguy::officeguy.settings.cvv_help')",
    "'Use 4-digit year format (2025)'" => "__('officeguy::officeguy.settings.four_digits_year_help')",
    "'Display fields in single column'" => "__('officeguy::officeguy.settings.single_column_layout_help')",
    "'Enable payments via Bit app'" => "__('officeguy::officeguy.settings.bit_enabled_help')",
    "'Log all API calls and transactions'" => "__('officeguy::officeguy.settings.logging_enabled_help')",
    "'Laravel channel for logging (stack, single, daily)'" => "__('officeguy::officeguy.settings.log_channel_help')",
    "'Verify SSL certificates in API calls (recommended for production)'" => "__('officeguy::officeguy.settings.ssl_verify_help')",
    "'Enable async webhook delivery via Laravel queues (recommended for production)'" => "__('officeguy::officeguy.settings.webhook_async_help')",
    "'Name of the queue to use for webhook jobs'" => "__('officeguy::officeguy.settings.webhook_queue_help')",
    "'Number of times to retry failed webhooks (exponential backoff: 10s, 100s, 1000s)'" => "__('officeguy::officeguy.settings.webhook_max_tries_help')",
    "'HTTP request timeout in seconds'" => "__('officeguy::officeguy.settings.webhook_timeout_help')",
    "'Verify SSL certificates when sending webhooks (disable for testing only)'" => "__('officeguy::officeguy.settings.webhook_verify_ssl_help')",
    "'Configure how webhooks are delivered. These settings control the queue-based webhook system introduced in v1.2.0.'" => "__('officeguy::officeguy.settings.webhook_configuration_desc')",
];

// Apply replacements
foreach ($translations as $old => $new) {
    $content = str_replace($old, $new, $content);
}

// Write back
file_put_contents($filePath, $content);

echo "✅ Translation keys applied to OfficeGuySettings.php\n";
echo "Total replacements: " . count($translations) . "\n";
