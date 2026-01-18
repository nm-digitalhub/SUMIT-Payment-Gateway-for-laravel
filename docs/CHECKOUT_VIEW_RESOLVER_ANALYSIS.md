# CheckoutViewResolver Service - Comprehensive Analysis

> **Purpose**: Dynamic checkout template resolution based on PayableType and product-specific attributes

**Version**: 1.10.0+
**File**: `src/Services/CheckoutViewResolver.php`
**Namespace**: `OfficeGuy\LaravelSumitGateway\Services`
**Created**: Phase 1 - Clean Architecture Implementation
**Last Updated**: 2025-12-18

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Methods Reference](#methods-reference)
4. [Priority System](#priority-system)
5. [Dependencies](#dependencies)
6. [Integration Points](#integration-points)
7. [View Templates](#view-templates)
8. [Best Practices](#best-practices)
9. [Usage Examples](#usage-examples)
10. [Testing Strategy](#testing-strategy)
11. [Common Scenarios](#common-scenarios)
12. [Summary](#summary)

---

## Overview

### Purpose

The `CheckoutViewResolver` service provides **intelligent, type-aware checkout template resolution** for the SUMIT Payment Gateway package. It implements a flexible priority system that allows:

1. **Product-specific templates** (e.g., `esim.blade.php` for eSIM products)
2. **Type-specific templates** (e.g., `digital.blade.php` for DIGITAL_PRODUCT)
3. **Generic fallback** (e.g., `checkout.blade.php` for all other types)

### Key Features

- **4-Tier Priority System**: Product → Type → Custom → Fallback
- **Automatic View Detection**: Uses Laravel's `View::exists()` for runtime checks
- **Extensible**: Supports custom base paths and product-specific overrides
- **Type-Safe**: Leverages PayableType enum for template mapping
- **Zero Configuration**: Works out-of-the-box with sensible defaults

### Design Goals

| Goal | Implementation |
|------|----------------|
| **Separation of Concerns** | View resolution logic isolated from controller |
| **DRY Principle** | Single source of truth for template mapping |
| **Extensibility** | Easy to add new templates without code changes |
| **Type Safety** | Enum-based template mapping prevents typos |
| **Performance** | Minimal runtime overhead (view existence checks cached by Laravel) |

---

## Architecture

### Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

class CheckoutViewResolver
{
    protected string $baseViewPath = 'officeguy::pages';

    // Public API
    public function resolve(Payable $payable): string
    public function setBaseViewPath(string $path): self
    public function getBaseViewPath(): string
    public function templateExists(string $template): bool
    public function getAvailableTemplates(): array
}
```

### Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     CheckoutViewResolver                         │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                     resolve(Payable $payable)
                              │
              ┌───────────────┴───────────────┐
              │                               │
              ▼                               ▼
    Priority 1: Product-Specific    Priority 2: Type-Specific
    Check: $payable->service_type   Check: PayableType->checkoutTemplate()
              │                               │
              ▼                               ▼
    View::exists('{base}.esim')     View::exists('{base}.digital')
              │                               │
              ├─ YES ─> Return               ├─ YES ─> Return
              │                               │
              ▼                               ▼
    Priority 3: Fallback                     │
    Return: '{base}.checkout'  ◄─────────────┘
```

---

## Methods Reference

### 1. `resolve(Payable $payable): string`

**Purpose**: Resolve the appropriate checkout view for a payable entity

**Parameters**:
- `$payable` (Payable): The payable entity requiring a checkout view

**Returns**: `string` - Full view path (e.g., `"officeguy::pages.digital"`)

**Algorithm**:

```php
public function resolve(Payable $payable): string
{
    // Priority 1: Product-specific template
    if (method_exists($payable, '__get') && property_exists($payable, 'service_type')) {
        $serviceType = $payable->service_type;
        if ($serviceType) {
            $productView = "{$this->baseViewPath}.{$serviceType}";
            if (View::exists($productView)) {
                return $productView;  // ← HIGHEST PRIORITY
            }
        }
    }

    // Priority 2: Type-specific template
    $typeTemplate = $payable->getPayableType()->checkoutTemplate();
    $typeView = "{$this->baseViewPath}.{$typeTemplate}";

    if (View::exists($typeView)) {
        return $typeView;  // ← MEDIUM PRIORITY
    }

    // Priority 3: Fallback to generic checkout
    return "{$this->baseViewPath}.checkout";  // ← LOWEST PRIORITY
}
```

**Example Outputs**:

| Input | Output |
|-------|--------|
| Package (service_type = 'esim') | `officeguy::pages.esim` |
| Package (service_type = 'hosting') | `officeguy::pages.infrastructure` |
| MayaNetEsimProduct (DIGITAL_PRODUCT) | `officeguy::pages.digital` |
| Invoice (GENERIC) | `officeguy::pages.checkout` |

---

### 2. `setBaseViewPath(string $path): self`

**Purpose**: Override the default view path namespace

**Parameters**:
- `$path` (string): New base view path (e.g., `'my-app::checkout'`)

**Returns**: `self` - For method chaining

**Use Case**: Allow parent applications to override package templates

**Example**:

```php
$resolver = app(CheckoutViewResolver::class);
$resolver->setBaseViewPath('my-app::custom-checkout');

// Now resolves to: my-app::custom-checkout.digital
$view = $resolver->resolve($payable);
```

---

### 3. `getBaseViewPath(): string`

**Purpose**: Retrieve the current base view path

**Parameters**: None

**Returns**: `string` - Current base path (default: `'officeguy::pages'`)

**Use Case**: Debugging or conditional logic based on view path

---

### 4. `templateExists(string $template): bool`

**Purpose**: Check if a specific template exists in the views directory

**Parameters**:
- `$template` (string): Template name without extension (e.g., `'digital'`, `'infrastructure'`)

**Returns**: `bool` - `true` if template exists, `false` otherwise

**Example**:

```php
$resolver = app(CheckoutViewResolver::class);

if ($resolver->templateExists('esim')) {
    // Use eSIM-specific checkout
} else {
    // Fallback to generic checkout
}
```

---

### 5. `getAvailableTemplates(): array`

**Purpose**: Get all available checkout templates that exist in the views directory

**Parameters**: None

**Returns**: `array<string>` - List of template names

**Algorithm**:

```php
public function getAvailableTemplates(): array
{
    $templates = [
        'checkout',        // Generic fallback (always available)
        'digital',         // DIGITAL_PRODUCT
        'infrastructure',  // INFRASTRUCTURE
        'subscription',    // SUBSCRIPTION
        'service',         // SERVICE
    ];

    return array_filter($templates, fn($template) => $this->templateExists($template));
}
```

**Example Output**:

```php
// If only generic, digital, and infrastructure templates exist:
['checkout', 'digital', 'infrastructure']
```

---

## Priority System

### 4-Tier Priority Hierarchy

| Priority | Check | Example | When Used |
|----------|-------|---------|-----------|
| **1. Product-Specific** | `$payable->service_type` | `esim.blade.php` | Package model with `service_type` property |
| **2. Type-Specific** | `PayableType->checkoutTemplate()` | `digital.blade.php` | Payable implements `getPayableType()` |
| **3. Custom Override** | `setBaseViewPath()` | `my-app::checkout.digital` | Parent app overrides package views |
| **4. Generic Fallback** | Always exists | `checkout.blade.php` | Default for all other cases |

### Priority Flowchart

```
┌─────────────────────────────────────────────────────────────┐
│ START: resolve($payable)                                     │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
          ┌──────────────────────────────┐
          │ Does $payable have           │
          │ service_type property?       │
          └──────────┬───────────────────┘
                     │
          ┌──────────┴──────────┐
          │                     │
         YES                   NO
          │                     │
          ▼                     │
┌──────────────────────┐        │
│ Does view exist?     │        │
│ {base}.{service_type}│        │
└──────────┬───────────┘        │
           │                    │
    ┌──────┴──────┐             │
   YES            NO             │
    │              │             │
    ▼              ▼             ▼
┌─────────┐  ┌────────────────────────────┐
│ RETURN  │  │ Get type template from:    │
│ Product │  │ $payable->getPayableType() │
│ View    │  │    ->checkoutTemplate()    │
└─────────┘  └──────────┬─────────────────┘
                        │
                        ▼
             ┌──────────────────────┐
             │ Does view exist?     │
             │ {base}.{type}        │
             └──────────┬───────────┘
                        │
                 ┌──────┴──────┐
                YES            NO
                 │              │
                 ▼              ▼
          ┌─────────┐    ┌─────────┐
          │ RETURN  │    │ RETURN  │
          │ Type    │    │ Generic │
          │ View    │    │ Fallback│
          └─────────┘    └─────────┘
```

---

## Dependencies

### Required Classes

| Dependency | Type | Usage |
|------------|------|-------|
| `Payable` | Interface | Input parameter for `resolve()` |
| `View` | Facade | Check template existence |
| `PayableType` | Enum | Map types to templates |

### Dependency Injection

```php
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;

class PublicCheckoutController extends Controller
{
    public function show(Request $request, string|int $id): View
    {
        $payable = $this->resolvePayable($request, $id);

        // Inject CheckoutViewResolver via service container
        $resolver = app(CheckoutViewResolver::class);
        $view = $resolver->resolve($payable);

        return view($view, [
            'payable' => $payable,
            // ... other data
        ]);
    }
}
```

### Service Provider Registration

**File**: `src/OfficeGuyServiceProvider.php`

```php
public function register(): void
{
    // CheckoutViewResolver is automatically resolvable via service container
    // No explicit binding needed (uses constructor injection)
    $this->app->bind(CheckoutViewResolver::class, function ($app) {
        return new CheckoutViewResolver();
    });
}
```

---

## Integration Points

### 1. PublicCheckoutController

**File**: `src/Http/Controllers/PublicCheckoutController.php:114-116`

```php
public function show(Request $request, string|int $id): View
{
    // ... resolve payable ...

    // Resolve dynamic checkout template based on PayableType
    $resolver = app(CheckoutViewResolver::class);
    $view = $resolver->resolve($payable);

    return view($view, [
        'payable' => $payable,
        'settings' => $this->getSettings(),
        'maxPayments' => PaymentService::getMaximumPayments($amount),
        // ... other data
    ]);
}
```

### 2. PayableType Enum

**File**: `src/Enums/PayableType.php:107-116`

```php
/**
 * Get checkout template name for this type
 *
 * Templates are loaded from:
 * resources/views/pages/checkout/{template}.blade.php
 */
public function checkoutTemplate(): string
{
    return match ($this) {
        self::INFRASTRUCTURE => 'infrastructure',
        self::DIGITAL_PRODUCT => 'digital',
        self::SUBSCRIPTION => 'subscription',
        self::SERVICE => 'service',
        self::GENERIC => 'checkout',
    };
}
```

### 3. Payable Interface

**File**: `src/Contracts/Payable.php`

```php
interface Payable
{
    // Required method for CheckoutViewResolver
    public function getPayableType(): PayableType;
}
```

---

## View Templates

### Available Templates

**Location**: `resources/views/pages/`

| Template | File | PayableType | Purpose |
|----------|------|-------------|---------|
| **Generic** | `checkout.blade.php` | GENERIC | Default fallback for all types |
| **Digital** | `digital.blade.php` | DIGITAL_PRODUCT | eSIM, software licenses, digital downloads |
| **Infrastructure** | `infrastructure.blade.php` | INFRASTRUCTURE | Domains, SSL, hosting, VPS |
| **Subscription** | `subscription.blade.php` | SUBSCRIPTION | Recurring billing (email, SaaS) |
| **Service** | `service.blade.php` | SERVICE | One-time professional services |

### Template Features Comparison

| Feature | Generic | Digital | Infrastructure | Subscription | Service |
|---------|---------|---------|----------------|--------------|---------|
| **Customer Info** | ✅ Full | ✅ Minimal | ✅ Full | ✅ Full | ✅ Full |
| **Address Fields** | ❌ Optional | ❌ No | ✅ Required | ❌ Optional | ❌ Optional |
| **Phone Required** | ❌ Optional | ❌ Optional | ✅ Required | ✅ Required | ✅ Required |
| **Instant Delivery** | ❌ No | ✅ Yes | ❌ No | ❌ No | ❌ No |
| **Fulfillment Time** | 60 min | 0 min | 30 min | 5 min | 1440 min |
| **Product Details** | ✅ Generic | ✅ eSIM-specific | ✅ WHOIS/SSL | ✅ Billing cycle | ✅ Service scope |

### Template Structure (Example: `digital.blade.php`)

```blade
{{-- Simplified structure --}}
<div class="checkout-container">
    {{-- Product Summary --}}
    <div class="product-card">
        <h2>{{ $payable->getPayableName() }}</h2>
        <p>{{ $payable->getPayableDescription() }}</p>
        <div class="price">{{ $currencySymbol }}{{ number_format($payable->getPayableAmount(), 2) }}</div>
    </div>

    {{-- Customer Information (Minimal for digital products) --}}
    <div class="customer-form">
        <x-filament-forms::field wire:model="customer_name" required />
        <x-filament-forms::field wire:model="customer_email" required />
        {{-- Phone optional for digital products --}}
    </div>

    {{-- Payment Method --}}
    <div class="payment-method">
        {{-- Payment form component --}}
        <x-officeguy::payment-form :settings="$settings" />
    </div>

    {{-- Submit Button --}}
    <button type="submit">Complete Purchase</button>
</div>
```

---

## Best Practices

### 1. Always Use CheckoutViewResolver

❌ **DON'T**:
```php
// Hardcoded template selection
$view = 'officeguy::pages.checkout';
```

✅ **DO**:
```php
// Dynamic template resolution
$resolver = app(CheckoutViewResolver::class);
$view = $resolver->resolve($payable);
```

### 2. Implement getPayableType() Correctly

❌ **DON'T**:
```php
class Order implements Payable
{
    public function getPayableType(): PayableType
    {
        return PayableType::GENERIC; // Always generic!
    }
}
```

✅ **DO**:
```php
class Order implements Payable
{
    public function getPayableType(): PayableType
    {
        // Dynamic based on order content
        if ($this->is_digital_product) {
            return PayableType::DIGITAL_PRODUCT;
        }
        if ($this->is_subscription) {
            return PayableType::SUBSCRIPTION;
        }
        return PayableType::GENERIC;
    }
}
```

### 3. Use service_type for Product-Specific Templates

✅ **DO**:
```php
class Package extends Model implements Payable
{
    // Allows product-specific templates like esim.blade.php
    public function getServiceTypeAttribute(): ?string
    {
        return $this->attributes['service_type'] ?? null;
    }
}
```

### 4. Test Template Existence Before Custom Logic

✅ **DO**:
```php
$resolver = app(CheckoutViewResolver::class);

if ($resolver->templateExists('custom-esim')) {
    // Use custom template
} else {
    // Fallback to standard template
}
```

### 5. Document Custom Templates

When creating custom templates, always document them:

```php
/**
 * Custom eSIM checkout template
 *
 * Template: resources/views/pages/esim.blade.php
 * Used for: MayaNetEsimProduct models with service_type='esim'
 * Priority: Product-specific (highest)
 */
```

---

## Usage Examples

### Example 1: Basic Usage (Standard Flow)

```php
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;

class CheckoutController extends Controller
{
    public function show($id)
    {
        // 1. Resolve payable
        $payable = Order::findOrFail($id);

        // 2. Resolve view template
        $resolver = app(CheckoutViewResolver::class);
        $view = $resolver->resolve($payable);

        // 3. Render view
        return view($view, [
            'payable' => $payable,
            'settings' => config('officeguy'),
        ]);
    }
}
```

**Flow**:
```
Order (GENERIC) → checkoutTemplate() = 'checkout'
                → View::exists('officeguy::pages.checkout') = true
                → Return: 'officeguy::pages.checkout'
```

---

### Example 2: Product-Specific Template (eSIM)

```php
// Model: Package with service_type='esim'
$package = Package::create([
    'name' => 'USA 5GB eSIM',
    'service_type' => 'esim',  // ← Product-specific identifier
    'price' => 29.99,
]);

// Controller
$resolver = app(CheckoutViewResolver::class);
$view = $resolver->resolve($package);

// Result: 'officeguy::pages.esim' (if esim.blade.php exists)
```

**Flow**:
```
Package (service_type='esim') → Priority 1: Check 'officeguy::pages.esim'
                              → View::exists() = true
                              → Return: 'officeguy::pages.esim'
```

---

### Example 3: Type-Specific Template (Digital Product)

```php
// Model: MayaNetEsimProduct implements Payable
class MayaNetEsimProduct extends Model implements Payable
{
    public function getPayableType(): PayableType
    {
        return PayableType::DIGITAL_PRODUCT;
    }
}

// Controller
$esim = MayaNetEsimProduct::findOrFail($id);
$resolver = app(CheckoutViewResolver::class);
$view = $resolver->resolve($esim);

// Result: 'officeguy::pages.digital'
```

**Flow**:
```
MayaNetEsimProduct → Priority 1: No service_type property
                   → Priority 2: getPayableType() = DIGITAL_PRODUCT
                   → checkoutTemplate() = 'digital'
                   → View::exists('officeguy::pages.digital') = true
                   → Return: 'officeguy::pages.digital'
```

---

### Example 4: Custom Base Path (Parent App Override)

```php
// Parent application wants to override package views
// File: app/Providers/AppServiceProvider.php

use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;

public function boot()
{
    // Override base path to use parent app's views
    $this->app->resolving(CheckoutViewResolver::class, function ($resolver) {
        $resolver->setBaseViewPath('my-app::checkout');
    });
}

// Now all resolves use parent app's views:
// 'my-app::checkout.digital'
// 'my-app::checkout.infrastructure'
// 'my-app::checkout.checkout'
```

---

### Example 5: Conditional Template Existence Check

```php
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;

class CheckoutController extends Controller
{
    public function show($id)
    {
        $payable = Package::findOrFail($id);
        $resolver = app(CheckoutViewResolver::class);

        // Check if custom template exists for this product
        if ($payable->service_type && $resolver->templateExists($payable->service_type)) {
            $view = "{$resolver->getBaseViewPath()}.{$payable->service_type}";
        } else {
            $view = $resolver->resolve($payable);
        }

        return view($view, compact('payable'));
    }
}
```

---

### Example 6: List Available Templates (Admin Panel)

```php
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;

class SettingsController extends Controller
{
    public function index()
    {
        $resolver = app(CheckoutViewResolver::class);
        $availableTemplates = $resolver->getAvailableTemplates();

        return view('admin.settings', [
            'templates' => $availableTemplates,
        ]);
    }
}

// Output: ['checkout', 'digital', 'infrastructure']
```

---

## Testing Strategy

### Unit Tests

**File**: `tests/Unit/Services/CheckoutViewResolverTest.php`

```php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;
use Illuminate\Support\Facades\View;

class CheckoutViewResolverTest extends TestCase
{
    /** @test */
    public function it_resolves_product_specific_template_first()
    {
        View::shouldReceive('exists')
            ->with('officeguy::pages.esim')
            ->once()
            ->andReturn(true);

        $payable = $this->createMock(Payable::class);
        $payable->service_type = 'esim';

        $resolver = new CheckoutViewResolver();
        $result = $resolver->resolve($payable);

        $this->assertEquals('officeguy::pages.esim', $result);
    }

    /** @test */
    public function it_resolves_type_specific_template_when_product_not_found()
    {
        View::shouldReceive('exists')
            ->with('officeguy::pages.esim')
            ->once()
            ->andReturn(false);

        View::shouldReceive('exists')
            ->with('officeguy::pages.digital')
            ->once()
            ->andReturn(true);

        $payable = $this->createMock(Payable::class);
        $payable->service_type = 'esim';
        $payable->method('getPayableType')->willReturn(PayableType::DIGITAL_PRODUCT);

        $resolver = new CheckoutViewResolver();
        $result = $resolver->resolve($payable);

        $this->assertEquals('officeguy::pages.digital', $result);
    }

    /** @test */
    public function it_falls_back_to_generic_template()
    {
        View::shouldReceive('exists')
            ->with('officeguy::pages.generic')
            ->once()
            ->andReturn(false);

        $payable = $this->createMock(Payable::class);
        $payable->method('getPayableType')->willReturn(PayableType::GENERIC);

        $resolver = new CheckoutViewResolver();
        $result = $resolver->resolve($payable);

        $this->assertEquals('officeguy::pages.checkout', $result);
    }

    /** @test */
    public function it_allows_custom_base_path()
    {
        $resolver = new CheckoutViewResolver();
        $resolver->setBaseViewPath('my-app::custom');

        $this->assertEquals('my-app::custom', $resolver->getBaseViewPath());
    }

    /** @test */
    public function it_checks_template_existence()
    {
        View::shouldReceive('exists')
            ->with('officeguy::pages.digital')
            ->once()
            ->andReturn(true);

        $resolver = new CheckoutViewResolver();
        $result = $resolver->templateExists('digital');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_available_templates()
    {
        View::shouldReceive('exists')->andReturn(true, true, false, false, true);

        $resolver = new CheckoutViewResolver();
        $templates = $resolver->getAvailableTemplates();

        $this->assertContains('checkout', $templates);
        $this->assertContains('digital', $templates);
        $this->assertNotContains('subscription', $templates);
    }
}
```

### Feature Tests

**File**: `tests/Feature/CheckoutFlowTest.php`

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Package;
use OfficeGuy\LaravelSumitGateway\Enums\PayableType;

class CheckoutFlowTest extends TestCase
{
    /** @test */
    public function it_displays_correct_template_for_digital_products()
    {
        $package = Package::factory()->create([
            'service_type' => 'esim',
            'payable_type' => PayableType::DIGITAL_PRODUCT,
        ]);

        $response = $this->get(route('officeguy.public.checkout.show', $package->id));

        $response->assertOk();
        $response->assertViewIs('officeguy::pages.esim');
    }

    /** @test */
    public function it_displays_fallback_template_for_generic_orders()
    {
        $order = Order::factory()->create();

        $response = $this->get(route('officeguy.public.checkout.show', $order->id));

        $response->assertOk();
        $response->assertViewIs('officeguy::pages.checkout');
    }
}
```

---

## Common Scenarios

### Scenario 1: Add New Product-Specific Template

**Goal**: Create custom checkout for "hosting" products

**Steps**:

1. **Create Template**:
```bash
touch resources/views/pages/hosting.blade.php
```

2. **Set service_type in Model**:
```php
class Package extends Model implements Payable
{
    public function getServiceTypeAttribute(): ?string
    {
        return $this->attributes['service_type'] ?? null;
    }
}
```

3. **Create Package with service_type**:
```php
$package = Package::create([
    'name' => 'Business Hosting Plan',
    'service_type' => 'hosting',  // ← Matches hosting.blade.php
    'price' => 99.99,
]);
```

4. **CheckoutViewResolver Automatically Detects**:
```php
$resolver->resolve($package); // Returns: 'officeguy::pages.hosting'
```

---

### Scenario 2: Override Package Templates in Parent App

**Goal**: Use parent app's views instead of package views

**Steps**:

1. **Create Custom Views in Parent App**:
```bash
mkdir -p resources/views/checkout
cp vendor/officeguy/laravel-sumit-gateway/resources/views/pages/checkout.blade.php resources/views/checkout/
```

2. **Override Base Path in AppServiceProvider**:
```php
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;

public function boot()
{
    $this->app->resolving(CheckoutViewResolver::class, function ($resolver) {
        $resolver->setBaseViewPath('checkout');
    });
}
```

3. **Now All Templates Use Parent App's Views**:
```php
// Before: officeguy::pages.digital
// After:  checkout.digital
```

---

### Scenario 3: Conditional Template Based on User Role

**Goal**: Show different checkout for wholesale customers

**Implementation**:

```php
class CustomCheckoutController extends PublicCheckoutController
{
    public function show(Request $request, $id)
    {
        $payable = $this->resolvePayable($request, $id);
        $resolver = app(CheckoutViewResolver::class);

        // Override base path for wholesale customers
        if (auth()->user()?->isWholesale()) {
            $resolver->setBaseViewPath('wholesale::checkout');
        }

        $view = $resolver->resolve($payable);

        return view($view, [
            'payable' => $payable,
            'settings' => $this->getSettings(),
        ]);
    }
}
```

---

## Summary

### Key Takeaways

| Aspect | Details |
|--------|---------|
| **Purpose** | Dynamic checkout template resolution based on PayableType |
| **Priority** | Product → Type → Custom → Fallback |
| **Flexibility** | Supports custom base paths and product-specific overrides |
| **Performance** | Minimal overhead (view existence checks cached by Laravel) |
| **Extensibility** | Easy to add new templates without code changes |

### When to Use

✅ **Use CheckoutViewResolver when**:
- Rendering checkout pages in controllers
- Building custom checkout flows
- Need type-aware template selection
- Want to support multiple product types with different UX

❌ **Don't use when**:
- Rendering non-checkout views (invoices, success pages)
- Template is explicitly specified by user
- You need to bypass the priority system

### Related Components

| Component | Relationship |
|-----------|-------------|
| **PayableType** | Provides `checkoutTemplate()` method for type mapping |
| **Payable Interface** | Requires `getPayableType()` implementation |
| **PublicCheckoutController** | Primary consumer of CheckoutViewResolver |
| **View Templates** | Target of resolution (checkout.blade.php, digital.blade.php, etc.) |

### Future Enhancements

**Potential improvements** (not yet implemented):

1. **Caching**: Cache resolved templates per PayableType to reduce `View::exists()` calls
2. **Admin UI**: Admin panel interface to preview and select templates
3. **A/B Testing**: Support for template variants with analytics
4. **Fallback Chain**: Allow multiple fallback templates (e.g., `esim` → `digital` → `checkout`)
5. **Custom Resolvers**: Plugin system for custom resolution logic

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-13
**Author**: Claude Code Agent
**Package Version**: v1.21.4+
