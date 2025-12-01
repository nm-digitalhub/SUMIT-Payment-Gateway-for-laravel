<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

/**
 * Service for managing invoice settings.
 *
 * This service provides a fallback mechanism:
 * 1. First tries to get settings from App\Settings\InvoiceSettings (if exists)
 * 2. Falls back to config/officeguy.php
 * 3. Falls back to hardcoded defaults
 */
class InvoiceSettingsService
{
    /**
     * Get the default currency code.
     */
    public function getDefaultCurrency(): string
    {
        // Try to get from App settings first (if exists)
        if ($this->hasAppSettings()) {
            try {
                $settings = app(\App\Settings\InvoiceSettings::class);

                return $settings->currency_code ?? $this->getConfigCurrency();
            } catch (\Throwable $e) {
                // Fall back to config
            }
        }

        return $this->getConfigCurrency();
    }

    /**
     * Get available currencies.
     *
     * @return array<string, string>
     */
    public function getCurrencies(): array
    {
        return config('officeguy.invoice.currencies', [
            'ILS' => 'שקל חדש (₪)',
            'USD' => 'דולר אמריקאי ($)',
            'EUR' => 'יורו (€)',
            'GBP' => 'לירה שטרלינג (£)',
        ]);
    }

    /**
     * Get the default invoice prefix.
     */
    public function getDefaultPrefix(): string
    {
        if ($this->hasAppSettings()) {
            try {
                $settings = app(\App\Settings\InvoiceSettings::class);

                return $settings->invoice_prefix ?? $this->getConfigPrefix();
            } catch (\Throwable $e) {
                // Fall back
            }
        }

        return $this->getConfigPrefix();
    }

    /**
     * Get the default tax rate.
     */
    public function getTaxRate(): float
    {
        if ($this->hasAppSettings()) {
            try {
                $settings = app(\App\Settings\InvoiceSettings::class);

                return $settings->tax_rate ?? $this->getConfigTaxRate();
            } catch (\Throwable $e) {
                // Fall back
            }
        }

        return $this->getConfigTaxRate();
    }

    /**
     * Get default due days.
     */
    public function getDueDays(): int
    {
        if ($this->hasAppSettings()) {
            try {
                $settings = app(\App\Settings\InvoiceSettings::class);

                return $settings->due_days ?? $this->getConfigDueDays();
            } catch (\Throwable $e) {
                // Fall back
            }
        }

        return $this->getConfigDueDays();
    }

    /**
     * Get currency symbol.
     */
    public function getCurrencySymbol(string $currency): string
    {
        return match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '₪',
        };
    }

    /**
     * Check if App settings class exists.
     */
    private function hasAppSettings(): bool
    {
        return class_exists(\App\Settings\InvoiceSettings::class);
    }

    /**
     * Get currency from config.
     */
    private function getConfigCurrency(): string
    {
        return config('officeguy.invoice.currency_code', 'ILS');
    }

    /**
     * Get prefix from config.
     */
    private function getConfigPrefix(): string
    {
        return config('officeguy.invoice.default_prefix', 'INV-');
    }

    /**
     * Get tax rate from config.
     */
    private function getConfigTaxRate(): float
    {
        return (float) config('officeguy.invoice.tax_rate', 0.17);
    }

    /**
     * Get due days from config.
     */
    private function getConfigDueDays(): int
    {
        return (int) config('officeguy.invoice.due_days', 30);
    }
}
