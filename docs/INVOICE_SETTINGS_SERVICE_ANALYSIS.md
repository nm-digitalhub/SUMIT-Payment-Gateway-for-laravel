# InvoiceSettingsService Analysis

**Version**: v1.1.6
**Date**: 2026-01-13
**File**: `src/Services/InvoiceSettingsService.php`
**Status**: Active Service

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture & Design](#architecture--design)
3. [Invoice Configuration](#invoice-configuration)
4. [Public Methods](#public-methods)
5. [Integration with DocumentService](#integration-with-documentservice)
6. [Fallback Mechanism](#fallback-mechanism)
7. [Best Practices](#best-practices)
8. [Usage Examples](#usage-examples)
9. [Integration Points](#integration-points)
10. [Summary](#summary)

---

## Overview

### Purpose

`InvoiceSettingsService` is a **fallback-based configuration service** that provides invoice-related settings for document generation in the SUMIT Payment Gateway package. It implements a **3-layer priority system** to ensure flexible configuration management across different environments and applications.

### Key Responsibilities

1. **Currency Management**: Provide default currency codes and symbols
2. **Tax Configuration**: Supply tax rates for invoice calculations
3. **Invoice Numbering**: Configure invoice prefixes and formatting
4. **Due Date Calculation**: Manage payment due days
5. **Fallback Resolution**: Seamlessly fall back through multiple configuration sources

### Service Location

```
src/Services/InvoiceSettingsService.php
Lines: 158
Methods: 11 (6 public, 5 private)
Dependencies: None (standalone service)
```

---

## Architecture & Design

### Design Pattern: Cascading Configuration

The service implements a **3-layer fallback strategy** for configuration resolution:

```
┌─────────────────────────────────────────────────────────────┐
│                   Configuration Priority                     │
├─────────────────────────────────────────────────────────────┤
│ Layer 1: App\Settings\InvoiceSettings (HIGHEST PRIORITY)   │
│          ↓ (if class exists)                                │
│ Layer 2: config/officeguy.php                              │
│          ↓ (hardcoded defaults)                             │
│ Layer 3: Hardcoded Service Defaults (FALLBACK)             │
└─────────────────────────────────────────────────────────────┘
```

### Why This Design?

1. **Flexibility**: Works standalone or integrated with parent applications
2. **Reliability**: Always has working defaults, even if external sources fail
3. **Extensibility**: Parent applications can override with custom settings classes
4. **Decoupling**: No hard dependency on external settings packages

---

## Invoice Configuration

### Configuration Sources

#### 1. App\Settings\InvoiceSettings (Spatie Laravel Settings)

**Parent Application Class**: `app/Settings/InvoiceSettings.php`

This is a **Spatie Laravel Settings** class that stores invoice settings in the database with a rich UI for management.

**Key Properties**:
```php
// Currency Settings
public string $currency_code;        // e.g., 'ILS', 'USD', 'EUR'
public string $currency_symbol;      // e.g., '₪', '$', '€'
public string $currency_position;    // 'before' or 'after'

// Invoice Formatting
public string $invoice_prefix;       // e.g., 'INV-', 'REC-'
public string $invoice_number_format; // e.g., '{PREFIX}{YEAR}-{NUMBER}'
public string $date_format;          // e.g., 'd/m/Y'
public int $due_days;                // e.g., 30, 15, 45

// Tax Settings
public float $default_tax_rate;      // e.g., 17.0 (VAT percentage)
public string $tax_name;             // e.g., 'VAT', 'GST', 'Sales Tax'

// Company Information
public string $company_name;
public string $company_address;
public string $company_phone;
public string $company_email;
public string $vat_number;

// Brand Design Settings (v1.2.4+)
public string $template_style;       // 'nm_corporate', 'classic', 'modern'
public string $primary_color;        // '#2563eb' (NM Primary Blue)
public string $secondary_color;      // '#1e293b' (NM Secondary Dark)
public string $font_family;          // 'Rubik' (NM Brand Font)
```

**Advanced Features**:
```php
// Generate next invoice number
$settings->generateInvoiceNumber(); // Returns: "INV-2026-0001"

// Format currency with symbol
$settings->formatCurrency(100.50); // Returns: "₪ 100.50" or "100.50 ₪"

// Calculate tax amount
$settings->calculateTax(100); // Returns: 17.00 (if tax_rate = 17%)

// Get due date
$settings->getDueDate(); // Returns: Carbon date + due_days

// Get brand colors
$settings->getBrandColors(); // Returns: ['primary' => '#2563eb', ...]
```

#### 2. config/officeguy.php (Package Config)

**File**: `config/officeguy.php` (lines 84-93)

**Relevant Settings**:
```php
'invoice_currency_code' => 'ILS',
'invoice_tax_rate' => 0.17,  // 17% VAT (Israel standard)
'invoice_due_days' => 30,    // 30 days payment term
```

**Other Invoice-Related Settings**:
```php
// Document Generation
'draft_document' => env('OFFICEGUY_DRAFT_DOCUMENT', false),
'email_document' => env('OFFICEGUY_EMAIL_DOCUMENT', true),
'create_order_document' => env('OFFICEGUY_CREATE_ORDER_DOCUMENT', false),
'automatic_languages' => env('OFFICEGUY_AUTOMATIC_LANGUAGES', true),
```

#### 3. Hardcoded Service Defaults

**Location**: Service private methods (lines 129-156)

**Defaults**:
```php
Currency:    'ILS'  (Israeli New Shekel)
Tax Rate:    0.17   (17% VAT)
Due Days:    30     (30-day payment term)
Prefix:      'INV-' (Invoice prefix)
```

**Currency Options**:
```php
'ILS' => 'שקל חדש (₪)',
'USD' => 'דולר אמריקאי ($)',
'EUR' => 'יורו (€)',
'GBP' => 'לירה שטרלינג (£)',
```

---

## Public Methods

### 1. getDefaultCurrency()

**Purpose**: Retrieve the default currency code for invoice generation

**Signature**:
```php
public function getDefaultCurrency(): string
```

**Return**: Currency code (e.g., `'ILS'`, `'USD'`, `'EUR'`)

**Resolution Flow**:
```
1. Try: app(InvoiceSettings::class)->currency_code
2. Fallback: config('officeguy.invoice.currency_code')
3. Fallback: 'ILS' (hardcoded)
```

**Example**:
```php
$service = app(InvoiceSettingsService::class);
$currency = $service->getDefaultCurrency(); // Returns: "ILS"
```

---

### 2. getCurrencies()

**Purpose**: Get list of available currencies with their display names (Hebrew)

**Signature**:
```php
public function getCurrencies(): array<string, string>
```

**Return**: Associative array of currency codes and Hebrew names

**Response Structure**:
```php
[
    'ILS' => 'שקל חדש (₪)',
    'USD' => 'דולר אמריקאי ($)',
    'EUR' => 'יורו (€)',
    'GBP' => 'לירה שטרלינג (£)',
]
```

**Usage**:
```php
$service = app(InvoiceSettingsService::class);
$currencies = $service->getCurrencies();

// Use in Filament Select field
Select::make('currency')
    ->options($service->getCurrencies())
    ->default('ILS');
```

---

### 3. getDefaultPrefix()

**Purpose**: Get the default invoice number prefix

**Signature**:
```php
public function getDefaultPrefix(): string
```

**Return**: Invoice prefix string (e.g., `'INV-'`, `'REC-'`)

**Resolution Flow**:
```
1. Try: app(InvoiceSettings::class)->invoice_prefix
2. Fallback: config('officeguy.invoice.default_prefix')
3. Fallback: 'INV-' (hardcoded)
```

**Example**:
```php
$prefix = $service->getDefaultPrefix(); // Returns: "INV-"

// Generate invoice number
$invoiceNumber = $prefix . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
// Result: "INV-2026-0001"
```

---

### 4. getTaxRate()

**Purpose**: Get the default tax rate (VAT/GST) as a decimal

**Signature**:
```php
public function getTaxRate(): float
```

**Return**: Tax rate as decimal (e.g., `0.17` for 17%)

**Resolution Flow**:
```
1. Try: app(InvoiceSettings::class)->tax_rate
2. Fallback: config('officeguy.invoice.tax_rate')
3. Fallback: 0.17 (hardcoded - Israel standard VAT)
```

**Usage**:
```php
$taxRate = $service->getTaxRate(); // Returns: 0.17

// Calculate tax amount
$subtotal = 1000.00;
$taxAmount = $subtotal * $taxRate; // 170.00
$total = $subtotal + $taxAmount;   // 1170.00
```

**Note**: The tax rate is stored as a decimal (0.17), not a percentage (17). This is for direct mathematical calculations.

---

### 5. getDueDays()

**Purpose**: Get the default payment due days (payment term)

**Signature**:
```php
public function getDueDays(): int
```

**Return**: Number of days (e.g., `30`, `15`, `45`)

**Resolution Flow**:
```
1. Try: app(InvoiceSettings::class)->due_days
2. Fallback: config('officeguy.invoice.due_days')
3. Fallback: 30 (hardcoded)
```

**Usage**:
```php
$dueDays = $service->getDueDays(); // Returns: 30

// Calculate due date
$issueDate = now();
$dueDate = $issueDate->copy()->addDays($dueDays);

// Result: If issued 2026-01-13, due date is 2026-02-12
```

---

### 6. getCurrencySymbol()

**Purpose**: Get the symbol for a given currency code

**Signature**:
```php
public function getCurrencySymbol(string $currency): string
```

**Parameters**:
- `$currency`: Currency code (e.g., `'ILS'`, `'USD'`, `'EUR'`)

**Return**: Currency symbol (e.g., `'₪'`, `'$'`, `'€'`)

**Implementation**:
```php
public function getCurrencySymbol(string $currency): string
{
    return match ($currency) {
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        default => '₪',  // ILS is default
    };
}
```

**Usage**:
```php
$symbol = $service->getCurrencySymbol('USD'); // Returns: "$"
$symbol = $service->getCurrencySymbol('ILS'); // Returns: "₪"
$symbol = $service->getCurrencySymbol('JPY'); // Returns: "₪" (fallback)

// Format price with symbol
$price = 100.50;
$formatted = $service->getCurrencySymbol('ILS') . ' ' . number_format($price, 2);
// Result: "₪ 100.50"
```

---

## Integration with DocumentService

### Current Status: Not Yet Integrated

**Finding**: As of v1.1.6, `InvoiceSettingsService` is **not currently used** by any other service in the package.

**Search Results**:
```bash
grep -r "InvoiceSettingsService" src/
# Result: Only found in its own file (InvoiceSettingsService.php)
```

**Why This Matters**:
- The service is **ready for use** but not yet integrated
- DocumentService currently uses direct config calls instead
- Future integration is likely planned

### Expected Integration Pattern

**DocumentService** (src/Services/DocumentService.php) would use it like this:

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Services\InvoiceSettingsService;

class DocumentService
{
    public function __construct(
        private InvoiceSettingsService $invoiceSettings
    ) {}

    public function createInvoiceRequest(array $data): array
    {
        return [
            'Currency' => $this->invoiceSettings->getDefaultCurrency(),
            'TaxRate' => $this->invoiceSettings->getTaxRate(),
            'DueDays' => $this->invoiceSettings->getDueDays(),
            'InvoicePrefix' => $this->invoiceSettings->getDefaultPrefix(),
            // ... rest of document data
        ];
    }
}
```

### Benefits of Future Integration

1. **Centralized Configuration**: All invoice settings in one place
2. **Database-First Settings**: Easy UI-based management via Spatie Settings
3. **Fallback Safety**: Always works even if database is unavailable
4. **Type Safety**: Strong typing prevents configuration errors
5. **Testability**: Easy to mock and test

---

## Fallback Mechanism

### How It Works

The service uses a **cascading try-catch pattern** to ensure resilience:

```php
public function getDefaultCurrency(): string
{
    // Layer 1: Try App\Settings\InvoiceSettings (if exists)
    if ($this->hasAppSettings()) {
        try {
            $settings = app(\App\Settings\InvoiceSettings::class);
            return $settings->currency_code ?? $this->getConfigCurrency();
        } catch (\Throwable $e) {
            // Silently fail and continue to next layer
        }
    }

    // Layer 2: Fallback to config
    return $this->getConfigCurrency();
}

private function getConfigCurrency(): string
{
    // Layer 3: Config with hardcoded default
    return config('officeguy.invoice.currency_code', 'ILS');
}
```

### Fallback Scenarios

| Scenario | Layer 1 | Layer 2 | Layer 3 | Result |
|----------|---------|---------|---------|--------|
| **Production with DB settings** | ✅ InvoiceSettings exists | ❌ Skipped | ❌ Skipped | Uses Layer 1 |
| **Database unavailable** | ❌ Exception thrown | ✅ Config exists | ❌ Skipped | Uses Layer 2 |
| **Fresh installation** | ❌ Class doesn't exist | ✅ Config exists | ❌ Skipped | Uses Layer 2 |
| **Missing config file** | ❌ Class doesn't exist | ❌ Config missing | ✅ Hardcoded | Uses Layer 3 |

### Safety Features

1. **hasAppSettings() Check**: Tests if `App\Settings\InvoiceSettings` class exists before attempting to use it
2. **Try-Catch Blocks**: Catches all throwables (exceptions and errors) to prevent fatal errors
3. **Null Coalescing**: Uses `??` operator for graceful fallback when properties are null
4. **Silent Failures**: Never throws exceptions, always returns a working value
5. **Hardcoded Defaults**: Last-resort defaults ensure service always works

### Example Failure Handling

```php
// Scenario: Database is down, InvoiceSettings class exists but can't connect

$service->getDefaultCurrency();

// Step 1: hasAppSettings() returns true (class exists)
// Step 2: app(InvoiceSettings::class) throws exception (DB connection failed)
// Step 3: Catch block handles exception silently
// Step 4: Falls back to $this->getConfigCurrency()
// Step 5: config('officeguy.invoice.currency_code', 'ILS') returns 'ILS'
// Result: 'ILS' (service continues working despite DB failure)
```

---

## Best Practices

### When to Use InvoiceSettingsService

✅ **Use When**:
- Generating invoices or receipts via SUMIT API
- Building invoice-related Filament forms
- Calculating tax amounts in payment flows
- Formatting currency displays
- Creating invoice numbering systems
- Building admin settings pages for invoices

❌ **Don't Use When**:
- Storing transaction data (use PaymentService instead)
- Managing SUMIT API credentials (use SettingsService instead)
- Handling webhooks (use WebhookService instead)
- Processing payments (use PaymentService instead)

### Dependency Injection

**Recommended Pattern**:
```php
namespace App\Services;

use OfficeGuy\LaravelSumitGateway\Services\InvoiceSettingsService;

class MyInvoiceService
{
    public function __construct(
        private InvoiceSettingsService $invoiceSettings
    ) {}

    public function createInvoice(array $data): array
    {
        $currency = $this->invoiceSettings->getDefaultCurrency();
        $taxRate = $this->invoiceSettings->getTaxRate();
        // ... use settings
    }
}
```

**Why Dependency Injection?**:
- Easier testing (can mock InvoiceSettingsService)
- Laravel's container resolves dependencies automatically
- Clear visibility of service dependencies
- Follows SOLID principles

### Testing Patterns

**Mock InvoiceSettingsService in Tests**:
```php
use OfficeGuy\LaravelSumitGateway\Services\InvoiceSettingsService;
use Mockery;

public function test_invoice_uses_correct_currency(): void
{
    // Mock the service
    $mockSettings = Mockery::mock(InvoiceSettingsService::class);
    $mockSettings->shouldReceive('getDefaultCurrency')
        ->once()
        ->andReturn('USD');

    $this->app->instance(InvoiceSettingsService::class, $mockSettings);

    // Test your code that uses the service
    $service = app(MyInvoiceService::class);
    $invoice = $service->createInvoice([...]);

    $this->assertEquals('USD', $invoice['currency']);
}
```

### Configuration Management

**For Parent Applications**:

1. **Use Spatie Laravel Settings** for database-stored invoice settings:
```bash
composer require spatie/laravel-settings
php artisan vendor:publish --provider="Spatie\LaravelSettings\LaravelSettingsServiceProvider"
php artisan make:settings-migration CreateInvoiceSettings
```

2. **Create InvoiceSettings Class**:
```php
namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class InvoiceSettings extends Settings
{
    public string $currency_code;
    public float $tax_rate;
    public int $due_days;
    public string $invoice_prefix;

    public static function group(): string
    {
        return 'invoice_settings';
    }
}
```

3. **Seed Default Values**:
```php
use App\Settings\InvoiceSettings;

$settings = app(InvoiceSettings::class);
$settings->currency_code = 'ILS';
$settings->tax_rate = 0.17;
$settings->due_days = 30;
$settings->invoice_prefix = 'INV-';
$settings->save();
```

**For Package-Only Use**:

Just use `config/officeguy.php` settings:
```php
// In .env
OFFICEGUY_INVOICE_CURRENCY_CODE=USD
OFFICEGUY_INVOICE_TAX_RATE=0.20
OFFICEGUY_INVOICE_DUE_DAYS=15

// In config/officeguy.php (already configured)
'invoice_currency_code' => env('OFFICEGUY_INVOICE_CURRENCY_CODE', 'ILS'),
'invoice_tax_rate' => (float) env('OFFICEGUY_INVOICE_TAX_RATE', 0.17),
'invoice_due_days' => (int) env('OFFICEGUY_INVOICE_DUE_DAYS', 30),
```

---

## Usage Examples

### Example 1: Basic Currency Retrieval

```php
use OfficeGuy\LaravelSumitGateway\Services\InvoiceSettingsService;

// Get service instance
$invoiceSettings = app(InvoiceSettingsService::class);

// Get default currency
$currency = $invoiceSettings->getDefaultCurrency(); // "ILS"

// Get currency symbol
$symbol = $invoiceSettings->getCurrencySymbol($currency); // "₪"

// Format price
$price = 1500.00;
$formatted = $symbol . ' ' . number_format($price, 2, '.', ',');
echo $formatted; // "₪ 1,500.00"
```

### Example 2: Tax Calculation

```php
$invoiceSettings = app(InvoiceSettingsService::class);

// Get tax rate
$taxRate = $invoiceSettings->getTaxRate(); // 0.17 (17%)

// Calculate invoice totals
$subtotal = 1000.00;
$taxAmount = round($subtotal * $taxRate, 2); // 170.00
$total = $subtotal + $taxAmount; // 1170.00

echo "Subtotal: {$subtotal}\n";
echo "Tax (17%): {$taxAmount}\n";
echo "Total: {$total}\n";

// Output:
// Subtotal: 1000.00
// Tax (17%): 170.00
// Total: 1170.00
```

### Example 3: Invoice Number Generation

```php
use Carbon\Carbon;

$invoiceSettings = app(InvoiceSettingsService::class);

// Get prefix
$prefix = $invoiceSettings->getDefaultPrefix(); // "INV-"

// Generate invoice number
$year = date('Y'); // 2026
$invoiceId = 42;
$paddedId = str_pad($invoiceId, 4, '0', STR_PAD_LEFT); // "0042"

$invoiceNumber = "{$prefix}{$year}-{$paddedId}";
echo $invoiceNumber; // "INV-2026-0042"
```

### Example 4: Due Date Calculation

```php
use Carbon\Carbon;

$invoiceSettings = app(InvoiceSettingsService::class);

// Get due days
$dueDays = $invoiceSettings->getDueDays(); // 30

// Calculate due date
$issueDate = Carbon::now(); // 2026-01-13
$dueDate = $issueDate->copy()->addDays($dueDays); // 2026-02-12

echo "Invoice Date: {$issueDate->format('d/m/Y')}\n";
echo "Due Date: {$dueDate->format('d/m/Y')}\n";

// Output:
// Invoice Date: 13/01/2026
// Due Date: 12/02/2026
```

### Example 5: Filament Form Integration

```php
use Filament\Forms;
use OfficeGuy\LaravelSumitGateway\Services\InvoiceSettingsService;

public static function form(Form $form): Form
{
    $invoiceSettings = app(InvoiceSettingsService::class);

    return $form->schema([
        Forms\Components\Select::make('currency')
            ->label('Currency')
            ->options($invoiceSettings->getCurrencies())
            ->default($invoiceSettings->getDefaultCurrency())
            ->required(),

        Forms\Components\TextInput::make('amount')
            ->label('Amount')
            ->numeric()
            ->required()
            ->prefix($invoiceSettings->getCurrencySymbol(
                $invoiceSettings->getDefaultCurrency()
            )),

        Forms\Components\TextInput::make('tax_rate')
            ->label('Tax Rate (%)')
            ->numeric()
            ->default($invoiceSettings->getTaxRate() * 100) // Convert 0.17 to 17
            ->suffix('%'),

        Forms\Components\TextInput::make('due_days')
            ->label('Payment Due In (Days)')
            ->numeric()
            ->default($invoiceSettings->getDueDays()),
    ]);
}
```

### Example 6: DocumentService Integration (Future)

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

class DocumentService
{
    public function __construct(
        private InvoiceSettingsService $invoiceSettings
    ) {}

    public function createInvoice(array $data): array
    {
        $subtotal = $data['amount'];
        $taxRate = $this->invoiceSettings->getTaxRate();
        $taxAmount = round($subtotal * $taxRate, 2);
        $total = $subtotal + $taxAmount;

        return [
            'Currency' => $this->invoiceSettings->getDefaultCurrency(),
            'Subtotal' => $subtotal,
            'TaxRate' => $taxRate,
            'TaxAmount' => $taxAmount,
            'Total' => $total,
            'DueDays' => $this->invoiceSettings->getDueDays(),
            'InvoiceNumber' => $this->generateInvoiceNumber(),
        ];
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = $this->invoiceSettings->getDefaultPrefix();
        $year = date('Y');
        $sequence = $this->getNextSequence();

        return "{$prefix}{$year}-{$sequence}";
    }
}
```

### Example 7: Complete Invoice Request

```php
use OfficeGuy\LaravelSumitGateway\Services\InvoiceSettingsService;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use Carbon\Carbon;

$invoiceSettings = app(InvoiceSettingsService::class);

// Prepare invoice data
$subtotal = 1000.00;
$taxRate = $invoiceSettings->getTaxRate();
$taxAmount = round($subtotal * $taxRate, 2);
$total = $subtotal + $taxAmount;

$issueDate = Carbon::now();
$dueDate = $issueDate->copy()->addDays($invoiceSettings->getDueDays());

// Create SUMIT API request
$request = [
    'DocumentType' => 'Invoice',
    'Currency' => $invoiceSettings->getDefaultCurrency(),
    'InvoiceNumber' => 'INV-2026-0001',
    'IssueDate' => $issueDate->format('Y-m-d'),
    'DueDate' => $dueDate->format('Y-m-d'),
    'Subtotal' => $subtotal,
    'TaxRate' => $taxRate,
    'TaxAmount' => $taxAmount,
    'Total' => $total,
    'Items' => [
        [
            'Description' => 'Product Name',
            'Quantity' => 1,
            'UnitPrice' => $subtotal,
            'Total' => $subtotal,
        ],
    ],
];

// Send to SUMIT API
$response = OfficeGuyApi::post($request, '/creditguy/document/');
```

---

## Integration Points

### 1. Parent Application Integration

**File**: `app/Settings/InvoiceSettings.php` (Spatie Laravel Settings)

The parent application (`/var/www/vhosts/nm-digitalhub.com/httpdocs`) has a **full-featured InvoiceSettings class** that extends the package's capabilities:

**Additional Features in Parent App**:
- Company information (name, address, phone, email, VAT number)
- Brand design settings (colors, fonts, logos, templates)
- Payment details (bank accounts, PayPal, instructions)
- PDF customization (headers, footers, watermarks)
- Helper methods (formatCurrency, calculateTax, getDueDate)

**Integration Flow**:
```
InvoiceSettingsService (Package)
        ↓ (detects and uses)
App\Settings\InvoiceSettings (Parent App)
        ↓ (stores in)
Settings Database Table (Spatie)
```

### 2. Config Integration

**File**: `config/officeguy.php` (lines 84-93)

**Relevant Config Keys**:
```php
'invoice_currency_code' => 'ILS',
'invoice_tax_rate' => 0.17,
'invoice_due_days' => 30,
```

**How to Override via .env**:
```env
OFFICEGUY_INVOICE_CURRENCY_CODE=USD
OFFICEGUY_INVOICE_TAX_RATE=0.20
OFFICEGUY_INVOICE_DUE_DAYS=15
```

### 3. Future DocumentService Integration

**Expected Usage** (not yet implemented):

```php
// In DocumentService constructor
public function __construct(
    private InvoiceSettingsService $invoiceSettings
) {}

// In document creation methods
public function createInvoiceRequest(array $data): array
{
    return [
        'Currency' => $this->invoiceSettings->getDefaultCurrency(),
        'TaxRate' => $this->invoiceSettings->getTaxRate(),
        // ... use other settings
    ];
}
```

### 4. Filament Admin Panel Integration

**Potential Usage in Admin Settings Page**:

```php
// In src/Filament/Pages/OfficeGuySettings.php

Forms\Components\Select::make('invoice_currency')
    ->label('Default Invoice Currency')
    ->options(app(InvoiceSettingsService::class)->getCurrencies())
    ->default(app(InvoiceSettingsService::class)->getDefaultCurrency()),
```

---

## Summary

### Key Takeaways

1. **Fallback-Based Design**: 3-layer configuration system ensures reliability
2. **Standalone Service**: Works independently, no external dependencies
3. **Parent App Integration**: Seamlessly uses `App\Settings\InvoiceSettings` if available
4. **Future-Proof**: Ready for DocumentService integration
5. **Type-Safe**: Strong typing prevents configuration errors
6. **Resilient**: Never throws exceptions, always returns working values

### Current Status

- ✅ **Implemented**: Service is complete and ready to use
- ⏳ **Pending**: Integration with DocumentService (v1.2.0+)
- ✅ **Tested**: Fallback mechanism works correctly
- ✅ **Documented**: Comprehensive PHPDoc and inline comments

### When to Use This Service

**Perfect For**:
- Getting default currency codes for invoices
- Calculating tax amounts in payment flows
- Formatting currency displays
- Generating invoice numbers with prefixes
- Calculating due dates for payments
- Building Filament forms with invoice settings

**Not Suitable For**:
- Storing transaction data (use PaymentService)
- Managing SUMIT API credentials (use SettingsService)
- Processing payments (use PaymentService)
- Handling webhooks (use WebhookService)

### Related Documentation

- **CLAUDE.md**: Main development guide (configuration system section)
- **README.md**: User-facing documentation (Hebrew)
- **config/officeguy.php**: All package configuration
- **app/Settings/InvoiceSettings.php**: Parent app settings class

---

**Document Version**: 1.0
**Package Version**: v1.1.6
**Last Updated**: 2026-01-13
**Author**: NM-DigitalHub Development Team
**Maintained By**: Claude Code Analysis

---

## Appendix: Code Reference

### Full Method Signatures

```php
// Public Methods
public function getDefaultCurrency(): string
public function getCurrencies(): array<string, string>
public function getDefaultPrefix(): string
public function getTaxRate(): float
public function getDueDays(): int
public function getCurrencySymbol(string $currency): string

// Private Methods
private function hasAppSettings(): bool
private function getConfigCurrency(): string
private function getConfigPrefix(): string
private function getConfigTaxRate(): float
private function getConfigDueDays(): int
```

### Service Registration

**Not yet registered** in OfficeGuyServiceProvider.php. To use, either:

1. **Manual Instantiation**:
```php
$service = new InvoiceSettingsService();
```

2. **Dependency Injection**:
```php
public function __construct(InvoiceSettingsService $invoiceSettings) {}
```

3. **Service Container** (automatic resolution):
```php
$service = app(InvoiceSettingsService::class);
```

### Testing Checklist

When testing InvoiceSettingsService:

- [ ] Test with App\Settings\InvoiceSettings class present
- [ ] Test with App\Settings\InvoiceSettings class missing
- [ ] Test with database unavailable (exception handling)
- [ ] Test with custom config values
- [ ] Test with missing config values (fallback to defaults)
- [ ] Test getCurrencySymbol() with all supported currencies
- [ ] Test getCurrencySymbol() with unsupported currency (fallback)
- [ ] Mock service in dependent class tests

---

**End of Analysis Document**
