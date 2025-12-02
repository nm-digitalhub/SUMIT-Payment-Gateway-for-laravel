<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;

/**
 * Settings Service - Hybrid config + database approach.
 *
 * Priority:
 * 1. Database value (user-edited via Admin Panel)
 * 2. Config file value (from .env or config/officeguy.php)
 *
 * Fallback: If table doesn't exist, uses config only.
 */
class SettingsService
{
    /**
     * Cached result of table existence check.
     *
     * @var bool|null
     */
    protected static ?bool $tableExistsCache = null;

    /**
     * Check if settings table exists.
     *
     * Cached to prevent N+1 queries when loading 74 settings.
     *
     * @return bool
     */
    protected function tableExists(): bool
    {
        if (self::$tableExistsCache !== null) {
            return self::$tableExistsCache;
        }

        try {
            self::$tableExistsCache = Schema::hasTable('officeguy_settings');
            return self::$tableExistsCache;
        } catch (\Exception $e) {
            self::$tableExistsCache = false;
            return false;
        }
    }

    /**
     * Get a setting value (DB override or config fallback).
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Try database first (if table exists)
        if ($this->tableExists()) {
            try {
                if (OfficeGuySetting::has($key)) {
                    return OfficeGuySetting::get($key);
                }
            } catch (\Exception $e) {
                // Table exists but query failed - continue to config
            }
        }

        // Fallback to config
        return config("officeguy.{$key}", $default);
    }

    /**
     * Set a setting value (saves to database).
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException('Settings table does not exist. Run migrations first.');
        }

        OfficeGuySetting::set($key, $value);
    }

    /**
     * Set multiple settings at once.
     *
     * @param array<string,mixed> $settings
     * @return void
     */
    public function setMany(array $settings): void
    {
        // Flatten nested arrays (e.g., collection.email) before saving
        $settings = Arr::dot($settings);

        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Check if a setting exists in database.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        try {
            return OfficeGuySetting::has($key);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove a setting from database (reverts to config default).
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void
    {
        if ($this->tableExists()) {
            OfficeGuySetting::remove($key);
        }
    }

    /**
     * Get all settings (merged: config defaults + DB overrides).
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        // Start with config defaults
        $settings = config('officeguy', []);

        // Override with database values (if table exists)
        if ($this->tableExists()) {
            try {
                $dbSettings = OfficeGuySetting::getAllSettings();
                $settings = array_merge($settings, $dbSettings);
            } catch (\Exception $e) {
                // Failed to query - return config only
            }
        }

        return $settings;
    }

    /**
     * Get all editable setting keys (for Filament form).
     *
     * @return array<string>
     */
    public function getEditableKeys(): array
    {
        return [
            'company_id',
            'private_key',
            'public_key',
            'environment',
            'pci',
            'pci_mode',
            'testing',
            'max_payments',
            'min_amount_for_payments',
            'min_amount_per_payment',
            'authorize_only',
            'authorize_added_percent',
            'authorize_minimum_addition',
            'merchant_number',
            'subscriptions_merchant_number',
            'draft_document',
            'email_document',
            'create_order_document',
            'automatic_languages',
            'merge_customers',
            'support_tokens',
            'token_param',
            'citizen_id',
            'cvv',
            'four_digits_year',
            'single_column_layout',
            'bit_enabled',
            'logging',
            'log_channel',
            'ssl_verify',
            'stock_sync_freq',
            'checkout_stock_sync',
            'paypal_receipts',
            'bluesnap_receipts',
            'other_receipts',
            // Public Checkout Page settings
            'enable_public_checkout',
            'public_checkout_path',
            'payable_model',
            // Field mapping settings
            'field_map_amount',
            'field_map_currency',
            'field_map_customer_name',
            'field_map_customer_email',
            'field_map_customer_phone',
            'field_map_description',
            // Collection (Debt) settings
            'collection.email',
            'collection.sms',
            'collection.schedule_time',
            'collection.reminder_days',
            'collection.max_attempts',
            // Custom Event Webhooks
            'webhook_payment_completed',
            'webhook_payment_failed',
            'webhook_document_created',
            'webhook_subscription_created',
            'webhook_subscription_charged',
            'webhook_bit_payment_completed',
            'webhook_stock_synced',
            'webhook_secret',
            // Customer Management (v1.2.4+)
            'customer_merging_enabled',
            'customer_local_sync_enabled',
            'customer_model_class',
            'customer_field_email',
            'customer_field_name',
            'customer_field_phone',
            'customer_field_first_name',
            'customer_field_last_name',
            'customer_field_company',
            'customer_field_address',
            'customer_field_city',
            'customer_field_sumit_id',
            // Route Configuration
            'routes_prefix',
            'routes_card_callback',
            'routes_bit_webhook',
            'routes_sumit_webhook',
            'routes_enable_checkout_endpoint',
            'routes_checkout_charge',
            'routes_document_download',
            'routes_success',
            'routes_failed',
            // Subscriptions
            'subscriptions_enabled',
            'subscriptions_default_interval',
            'subscriptions_default_cycles',
            'subscriptions_allow_pause',
            'subscriptions_retry_failed',
            'subscriptions_max_retries',
            // Donations
            'donations_enabled',
            'donations_allow_mixed',
            'donations_default_document_type',
            // Multi-Vendor
            'multivendor_enabled',
            'multivendor_validate_credentials',
            'multivendor_allow_authorize',
            // Upsell / CartFlows
            'upsell_enabled',
            'upsell_require_token',
            'upsell_max_per_order',
        ];
    }

    /**
     * Get all editable settings with current values.
     *
     * Optimized to prevent N+1 queries by fetching all DB settings at once.
     *
     * @return array<string,mixed>
     */
    public function getEditableSettings(): array
    {
        // Start with config defaults for all editable keys
        $settings = [];
        $editableKeys = $this->getEditableKeys();

        foreach ($editableKeys as $key) {
            Arr::set($settings, $key, config("officeguy.{$key}"));
        }

        // Override with database values in one query (if table exists)
        if ($this->tableExists()) {
            try {
                // Fetch all DB settings at once instead of one-by-one
                $dbSettings = OfficeGuySetting::getAllSettings();

                // Only override editable keys
                foreach ($editableKeys as $key) {
                    if (isset($dbSettings[$key])) {
                        Arr::set($settings, $key, $dbSettings[$key]);
                    }
                }
            } catch (\Exception $e) {
                // Failed to query - return config only
            }
        }

        return $settings;
    }

    /**
     * Reset a setting to config default (removes from DB).
     *
     * @param string $key
     * @return void
     */
    public function resetToDefault(string $key): void
    {
        $this->remove($key);
    }

    /**
     * Reset all settings to config defaults (clears DB).
     *
     * @return void
     */
    public function resetAllToDefaults(): void
    {
        if ($this->tableExists()) {
            OfficeGuySetting::query()->delete();
        }
    }
}
