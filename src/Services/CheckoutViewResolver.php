<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use BackedEnum;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use Illuminate\Support\Facades\View;

/**
 * CheckoutViewResolver
 *
 * Resolves the appropriate checkout view template based on PayableType.
 * Implements a 4-tier priority system:
 * 1. Product-specific template (esim.blade.php)
 * 2. Type-specific template (digital.blade.php)
 * 3. Custom overrides
 * 4. Generic fallback (checkout.blade.php)
 *
 * @package OfficeGuy\LaravelSumitGateway
 * @since 1.10.0
 */
class CheckoutViewResolver
{
    protected string $baseViewPath = 'officeguy::pages';

    /**
     * Resolve the appropriate checkout view for a payable
     *
     * Priority:
     * 1. Product-specific (e.g., esim.blade.php for eSIM products)
     * 2. Type-specific (e.g., digital.blade.php for DIGITAL_PRODUCT)
     * 3. Generic fallback (checkout.blade.php)
     *
     * @param Payable $payable
     * @return string Full view path (e.g., "officeguy::pages.checkout.digital")
     */
    public function resolve(Payable $payable): string
    {
        // Priority 1: Product-specific template
        // Check if model has service_type attribute (Eloquent models use isset/hasAttribute, not property_exists)
        if (method_exists($payable, '__get') && isset($payable->service_type)) {
            $serviceType = $payable->service_type;

            // Handle string-backed enums (extract value)
            if ($serviceType instanceof BackedEnum) {
                $serviceType = $serviceType->value;
            }

            if (is_string($serviceType)) {
                $productView = $this->baseViewPath . '.' . $serviceType;
                if (View::exists($productView)) {
                    return $productView;
                }
            }
        }

        // Priority 2: Type-specific template
        $typeTemplate = $payable->getPayableType()->checkoutTemplate();
        // Use concatenation instead of interpolation
        $typeView = $this->baseViewPath . '.' . $typeTemplate;

        if (View::exists($typeView)) {
            return $typeView;
        }

        // Priority 3: Fallback to generic checkout
        return $this->baseViewPath . '.checkout';
    }

    /**
     * Set custom base view path
     *
     * Allows applications to override the default view path namespace.
     *
     * @param string $path
     * @return $this
     */
    public function setBaseViewPath(string $path): self
    {
        $this->baseViewPath = $path;

        return $this;
    }

    /**
     * Get current base view path
     *
     * @return string
     */
    public function getBaseViewPath(): string
    {
        return $this->baseViewPath;
    }

    /**
     * Check if a specific template exists
     *
     * @param string $template Template name without extension (e.g., 'digital', 'infrastructure')
     * @return bool
     */
    public function templateExists(string $template): bool
    {
        return View::exists($this->baseViewPath . '.' . $template);
    }

    /**
     * Get all available checkout templates
     *
     * Returns array of template names that exist in the views directory.
     *
     * @return array<string>
     */
    public function getAvailableTemplates(): array
    {
        $templates = [
            'checkout',        // Generic fallback
            'digital',         // DIGITAL_PRODUCT
            'infrastructure',  // INFRASTRUCTURE
            'subscription',    // SUBSCRIPTION
            'service',         // SERVICE
        ];

        return array_filter($templates, fn ($template) => $this->templateExists($template));
    }
}
