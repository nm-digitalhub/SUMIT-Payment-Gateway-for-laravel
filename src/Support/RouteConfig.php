<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting;

/**
 * RouteConfig Helper
 *
 * Gets route configuration from database settings with fallback to config file.
 * This allows customization of all endpoints via Admin Panel.
 */
class RouteConfig
{
    /**
     * Get route prefix
     */
    public static function getPrefix(): string
    {
        return static::getSetting('routes_prefix', 'officeguy.routes.prefix', 'officeguy');
    }

    /**
     * Get middleware array
     */
    public static function getMiddleware(): array
    {
        return config('officeguy.routes.middleware', ['web']);
    }

    /**
     * Get card callback path
     */
    public static function getCardCallbackPath(): string
    {
        return static::getSetting('routes_card_callback', 'officeguy.routes.card_callback', 'callback/card');
    }

    /**
     * Get Bit webhook path
     */
    public static function getBitWebhookPath(): string
    {
        return static::getSetting('routes_bit_webhook', 'officeguy.routes.bit_webhook', 'webhook/bit');
    }

    /**
     * Get SUMIT webhook path
     */
    public static function getSumitWebhookPath(): string
    {
        return static::getSetting('routes_sumit_webhook', 'officeguy.routes.sumit_webhook', 'webhook/sumit');
    }

    /**
     * Get document download path
     */
    public static function getDocumentDownloadPath(): string
    {
        return static::getSetting('routes_document_download', 'officeguy.routes.document_download', 'documents/{document}');
    }

    /**
     * Get checkout charge path
     */
    public static function getCheckoutChargePath(): string
    {
        return static::getSetting('routes_checkout_charge', 'officeguy.routes.checkout_charge', 'checkout/charge');
    }

    /**
     * Get public checkout path
     */
    public static function getPublicCheckoutPath(): string
    {
        return static::getSetting('public_checkout_path', 'officeguy.routes.public_checkout', 'checkout/{id}');
    }

    /**
     * Check if checkout endpoint is enabled
     */
    public static function isCheckoutEndpointEnabled(): bool
    {
        return (bool) static::getSetting(
            'routes_enable_checkout_endpoint',
            'officeguy.routes.enable_checkout_endpoint',
            false
        );
    }

    /**
     * Check if public checkout is enabled
     */
    public static function isPublicCheckoutEnabled(): bool
    {
        return (bool) static::getSetting(
            'enable_public_checkout',
            'officeguy.routes.enable_public_checkout',
            false
        );
    }

    /**
     * Get success redirect route name
     */
    public static function getSuccessRoute(): string
    {
        return static::getSetting('routes_success', 'officeguy.routes.success', 'checkout.success');
    }

    /**
     * Get failed redirect route name
     */
    public static function getFailedRoute(): string
    {
        return static::getSetting('routes_failed', 'officeguy.routes.failed', 'checkout.failed');
    }

    /**
     * Get all route paths for display/debugging
     */
    public static function getAllPaths(): array
    {
        $prefix = static::getPrefix();

        return [
            'prefix' => $prefix,
            'card_callback' => $prefix . '/' . static::getCardCallbackPath(),
            'bit_webhook' => $prefix . '/' . static::getBitWebhookPath(),
            'sumit_webhook' => $prefix . '/' . static::getSumitWebhookPath(),
            'sumit_webhook_card_created' => $prefix . '/' . static::getSumitWebhookPath() . '/card-created',
            'sumit_webhook_card_updated' => $prefix . '/' . static::getSumitWebhookPath() . '/card-updated',
            'sumit_webhook_card_deleted' => $prefix . '/' . static::getSumitWebhookPath() . '/card-deleted',
            'sumit_webhook_card_archived' => $prefix . '/' . static::getSumitWebhookPath() . '/card-archived',
            'document_download' => $prefix . '/' . static::getDocumentDownloadPath(),
            'checkout_charge' => $prefix . '/' . static::getCheckoutChargePath(),
            'public_checkout' => $prefix . '/' . static::getPublicCheckoutPath(),
        ];
    }

    /**
     * Get setting from database with fallback to config
     *
     * IMPORTANT: Uses the Model's get() method instead of DB::table() to ensure
     * the JSON cast is applied correctly (fixing double-encoded route values).
     */
    protected static function getSetting(string $settingKey, string $configKey, $default)
    {
        // Try to get from database first using the Model (with JSON cast)
        try {
            if (Schema::hasTable('officeguy_settings')) {
                $value = OfficeGuySetting::get($settingKey);

                if ($value !== null) {
                    return $value;
                }
            }
        } catch (\Throwable $e) {
            // Database not available, use config
        }

        // Fall back to config
        return config($configKey, $default);
    }
}