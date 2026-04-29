<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * Resolves the checkout view. Phase 4.6: host-owned selection via config callable; single default.
 *
 * Uses config('officeguy.checkout.view_resolver'): callable(Request, Payable) -> ?string.
 * If callable returns a view name and it exists, that view is used; else config('officeguy.checkout.default_view').
 */
class CheckoutViewResolver
{
    public function resolve(Request $request, Payable $payable): string
    {
        $callable = config('officeguy.checkout.view_resolver');
        if (is_callable($callable)) {
            $viewName = $callable($request, $payable);
            if (is_string($viewName) && $viewName !== '' && View::exists($viewName)) {
                return $viewName;
            }
        }

        $default = config('officeguy.checkout.default_view', 'officeguy::pages.checkout');

        return View::exists($default) ? $default : 'officeguy::pages.checkout';
    }

    public function setBaseViewPath(string $path): self
    {
        // No-op for BC; view selection is now via config callable only.
        return $this;
    }

    public function getBaseViewPath(): string
    {
        return config('officeguy.checkout.default_view', 'officeguy::pages.checkout');
    }

    /**
     * Check if a template exists under the default view path (for host use).
     */
    public function templateExists(string $template): bool
    {
        $base = 'officeguy::pages';

        return View::exists($base . '.' . $template);
    }

    /**
     * Returns only the default view name (no type-based list). Host can override via view_resolver.
     *
     * @return array<string>
     */
    public function getAvailableTemplates(): array
    {
        $default = config('officeguy.checkout.default_view', 'officeguy::pages.checkout');

        return View::exists($default) ? [$default] : [];
    }
}
