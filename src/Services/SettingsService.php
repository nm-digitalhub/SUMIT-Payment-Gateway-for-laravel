<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting;
use Illuminate\Support\Facades\Schema;

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
     * Check if settings table exists.
     *
     * @return bool
     */
    protected function tableExists(): bool
    {
        try {
            return Schema::hasTable('officeguy_settings');
        } catch (\Exception $e) {
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
                $dbSettings = OfficeGuySetting::all();
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
        ];
    }

    /**
     * Get all editable settings with current values.
     *
     * @return array<string,mixed>
     */
    public function getEditableSettings(): array
    {
        $settings = [];

        foreach ($this->getEditableKeys() as $key) {
            $settings[$key] = $this->get($key);
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
