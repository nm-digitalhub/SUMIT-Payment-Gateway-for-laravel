<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support\Traits;

/**
 * HasCheckoutTheme Trait
 *
 * Provides dynamic checkout theme capabilities for Payable models.
 * Allows customization of colors, branding, trust badges, and progress steps.
 *
 * Usage:
 * ```php
 * class MayaNetEsimProduct extends Model implements Payable
 * {
 *     use HasCheckoutTheme;
 * }
 * ```
 *
 * @version 1.16.0
 */
trait HasCheckoutTheme
{
    /**
     * Get primary brand color with fallback to default.
     *
     * @return string Hex color code (e.g., '#3B82F6')
     */
    public function getPrimaryColor(): string
    {
        // Try multiple possible color fields
        return $this->color
            ?? $this->brand_color
            ?? $this->theme_color
            ?? '#3B82F6'; // Default blue
    }

    /**
     * Get secondary color (automatically calculated as lighter shade of primary).
     *
     * @return string Hex color code
     */
    public function getSecondaryColor(): string
    {
        $primary = $this->getPrimaryColor();

        return $this->lightenColor($primary, 40);
    }

    /**
     * Get hover color (automatically calculated as darker shade of primary).
     *
     * @return string Hex color code
     */
    public function getHoverColor(): string
    {
        $primary = $this->getPrimaryColor();

        return $this->darkenColor($primary, 10);
    }

    /**
     * Get brand tagline with fallback.
     *
     * Note: Brand name is always "NM-DigitalHub" - this is for tagline/subtitle only.
     *
     * @return string Tagline/slogan
     */
    public function getBrandTagline(): string
    {
        return $this->brand_tagline
            ?? $this->tagline
            ?? $this->slogan
            ?? __('Secure Payment Gateway');
    }

    /**
     * Get complete checkout theme configuration.
     *
     * Returns merged configuration from:
     * 1. Custom checkout_theme JSON field
     * 2. Individual fields (brand_name, brand_logo, etc.)
     * 3. Default theme configuration
     *
     * @return array<string, mixed> Complete theme configuration
     */
    public function getCheckoutTheme(): array
    {
        // Start with default theme
        $theme = $this->getDefaultTheme();

        // Merge with custom checkout_theme if it exists
        $customTheme = $this->getCustomThemeData();
        if (! empty($customTheme)) {
            return array_replace_recursive($theme, $customTheme);
        }

        return $theme;
    }

    /**
     * Get default theme configuration.
     *
     * Note: Brand name is always "NM-DigitalHub" - not customizable per product.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultTheme(): array
    {
        return [
            'colors' => [
                'primary' => $this->getPrimaryColor(),
                'secondary' => $this->getSecondaryColor(),
                'hover' => $this->getHoverColor(),
            ],
            'branding' => [
                'tagline' => $this->getBrandTagline(),
            ],
            'trust_badges' => $this->getTrustBadges(),
            'progress_steps' => $this->getProgressSteps(),
        ];
    }

    /**
     * Get custom theme data from checkout_theme field.
     *
     * @return array<string, mixed>
     */
    protected function getCustomThemeData(): array
    {
        $theme = $this->checkout_theme ?? $this->theme_config ?? null;

        if (is_string($theme)) {
            $decoded = json_decode($theme, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($theme) ? $theme : [];
    }

    /**
     * Get trust badges configuration.
     *
     * Can be overridden in model to provide custom badges.
     *
     * @return array<int, array<string, string>>
     */
    protected function getTrustBadges(): array
    {
        return [
            ['icon' => 'lock', 'text' => __('SSL Encrypted')],
            ['icon' => 'check-circle', 'text' => __('PCI DSS Compliant')],
            ['icon' => 'cards', 'text' => 'VISA / MC / AMEX'],
        ];
    }

    /**
     * Get progress steps configuration.
     *
     * Can be overridden in model to provide custom steps.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getProgressSteps(): array
    {
        return [
            ['number' => 1, 'label' => __('Customer')],
            ['number' => 2, 'label' => __('Payment')],
            ['number' => 3, 'label' => __('Terms')],
            ['number' => 4, 'label' => __('Submit')],
        ];
    }

    /**
     * Lighten a hex color by a percentage.
     *
     * @param  string  $hex  Hex color code (#RRGGBB or RRGGBB)
     * @param  int  $percent  Percentage to lighten (0-100)
     * @return string Lightened hex color
     */
    protected function lightenColor(string $hex, int $percent): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Ensure valid hex length
        if (strlen($hex) !== 6) {
            return '#' . $hex; // Return original if invalid
        }

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Lighten by moving towards white (255)
        $r = min(255, $r + (int) ((255 - $r) * $percent / 100));
        $g = min(255, $g + (int) ((255 - $g) * $percent / 100));
        $b = min(255, $b + (int) ((255 - $b) * $percent / 100));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * Darken a hex color by a percentage.
     *
     * @param  string  $hex  Hex color code (#RRGGBB or RRGGBB)
     * @param  int  $percent  Percentage to darken (0-100)
     * @return string Darkened hex color
     */
    protected function darkenColor(string $hex, int $percent): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Ensure valid hex length
        if (strlen($hex) !== 6) {
            return '#' . $hex; // Return original if invalid
        }

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Darken by moving towards black (0)
        $r = max(0, $r - (int) ($r * $percent / 100));
        $g = max(0, $g - (int) ($g * $percent / 100));
        $b = max(0, $b - (int) ($b * $percent / 100));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /**
     * Check if a color is valid hex.
     *
     * @param  string  $color  Color to validate
     * @return bool True if valid hex color
     */
    protected function isValidHexColor(string $color): bool
    {
        return (bool) preg_match('/^#?[a-fA-F0-9]{6}$/', $color);
    }

    /**
     * Get color contrast (for determining text color).
     *
     * @param  string  $hex  Background color
     * @return string 'light' or 'dark'
     */
    protected function getColorContrast(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return 'dark';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? 'dark' : 'light';
    }
}
