# DonationService - Comprehensive Analysis

**File**: `src/Services/DonationService.php`
**Version**: v1.21.4
**Last Updated**: 2026-01-13
**Maintained By**: NM-DigitalHub

## Table of Contents

1. [Overview](#overview)
2. [Donation Receipts (תעודת זיכוי)](#donation-receipts)
3. [Core Methods](#core-methods)
4. [Tax Benefits & Legal Requirements](#tax-benefits--legal-requirements)
5. [Integration with Other Services](#integration-with-other-services)
6. [Configuration](#configuration)
7. [Best Practices](#best-practices)
8. [Code Examples](#code-examples)
9. [Migration from WooCommerce](#migration-from-woocommerce)
10. [Summary](#summary)

---

## Overview

The `DonationService` handles **donation product detection** and **special document generation** for non-profit organizations using the SUMIT payment gateway. This service is a direct port of the `OfficeGuyDonation.php` class from the original WooCommerce plugin.

### Purpose

- **Detect donation items** in orders/carts
- **Validate cart composition** (prevent mixing donations with regular products)
- **Generate proper tax receipts** (תעודת זיכוי) for Israeli tax deductions
- **Calculate donation totals** separately from regular product totals

### Key Features

✅ **Donation Detection**: Identifies donation items via multiple field patterns
✅ **Mixed Cart Prevention**: Enforces separation of donations and regular products
✅ **Document Type Selection**: Returns correct SUMIT document type (320 = DonationReceipt)
✅ **Item Splitting**: Separates donations from regular products for reporting
✅ **Total Calculations**: Computes donation and regular totals independently
✅ **Flexible Item Format**: Works with `Payable` instances or raw item arrays

### Technical Details

- **Namespace**: `OfficeGuy\LaravelSumitGateway\Services`
- **Type**: Static service class (no instantiation required)
- **Dependencies**:
  - `OfficeGuy\LaravelSumitGateway\Contracts\Payable` interface
- **Return Types**: All methods use strict PHP 8.2 type declarations
- **Immutable**: All methods are stateless and side-effect free

---

## Donation Receipts (תעודת זיכוי)

### What is a Donation Receipt?

In Israel, **non-profit organizations (amutot/עמותות)** that have received **Section 46 tax exemption status** from the Israeli Tax Authority can issue **donation receipts (תעודת זיכוי)** to donors. These receipts allow donors to claim **tax deductions** on their annual income tax returns.

### SUMIT Document Type

**Document Type Code**: `320` (DonationReceipt)
**API Identifier**: `"DonationReceipt"`
**Hebrew Name**: תעודת זיכוי

### Legal Requirements (Israeli Tax Law)

To issue valid donation receipts, organizations must:

1. **Be a registered non-profit** (amuta/עמותה) in Israel
2. **Have Section 46 tax exemption** from the Israeli Tax Authority
3. **Include donor's ID number** (teudat zehut/תעודת זהות) on the receipt
4. **Issue receipts within the same tax year** as the donation
5. **Maintain proper records** for at least 7 years (Israeli law)

**Important**: The package does NOT validate tax-exempt status. Organizations must ensure they are legally authorized to issue donation receipts before using this feature.

### Document Fields (תעודת זיכוי)

When generating a donation receipt, the following fields are required:

| Field | Hebrew | Required | Description |
|-------|--------|----------|-------------|
| Donor Name | שם התורם | ✅ Yes | Full legal name |
| ID Number | תעודת זהות | ✅ Yes | 9-digit Israeli ID |
| Donation Amount | סכום התרומה | ✅ Yes | Total donated |
| Donation Date | תאריך התרומה | ✅ Yes | Date of donation |
| Receipt Number | מספר קבלה | ✅ Yes | Unique identifier |
| Organization Name | שם העמותה | ✅ Yes | Registered NPO name |
| Organization Number | מספר עמותה | ✅ Yes | Tax Authority ID |

### Tax Deduction Rules (2026)

**Individual Donors**:
- **Deduction Rate**: 35% of donation amount
- **Maximum Deduction**: Up to 30% of taxable income or 2.5M ILS (whichever is lower)
- **Minimum Donation**: No minimum (any amount is deductible)

**Corporate Donors**:
- **Deduction Rate**: Same as individual (35%)
- **Maximum Deduction**: Up to 30% of taxable profit
- **Record Keeping**: Must maintain receipts for 7 years

**Example**: A donor who donates 10,000 ILS can deduct 3,500 ILS from their taxable income, potentially saving 1,750 ILS in taxes (at 50% marginal rate).

---

## Core Methods

### 1. `containsDonation()`

**Purpose**: Check if an order/cart contains any donation items.

**Signature**:
```php
public static function containsDonation(Payable|array $orderOrItems): bool
```

**Parameters**:
- `$orderOrItems`: `Payable` instance (order) or raw array of line items

**Returns**: `true` if at least one donation item exists, `false` otherwise

**Port From**: `CartContainsDonation()` in `OfficeGuyDonation.php`

**Algorithm**:
```
1. Extract items (from Payable::getLineItems() or use raw array)
2. Loop through each item
3. Check if item is donation (using isDonationItem())
4. Return true on first donation found
5. Return false if no donations found
```

**Example**:
```php
$order = Order::find(123);
$hasDonations = DonationService::containsDonation($order);

if ($hasDonations) {
    // Generate donation receipt (type 320)
}
```

---

### 2. `containsNonDonation()`

**Purpose**: Check if an order/cart contains any **non-donation** (regular) items.

**Signature**:
```php
public static function containsNonDonation(Payable|array $orderOrItems): bool
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: `true` if at least one regular item exists, `false` otherwise

**Port From**: `CartContainsNonDonation()` in `OfficeGuyDonation.php`

**Algorithm**:
```
1. Extract items (from Payable::getLineItems() or use raw array)
2. Loop through each item
3. Check if item is NOT a donation (using !isDonationItem())
4. Return true on first non-donation found
5. Return false if all items are donations
```

**Use Case**: Validate that a "donations-only" order doesn't contain regular products.

---

### 3. `isDonationItem()`

**Purpose**: Check if a single item is marked as a donation.

**Signature**:
```php
public static function isDonationItem(array $item): bool
```

**Parameters**:
- `$item`: Array containing item data (from `getLineItems()`)

**Returns**: `true` if item is a donation, `false` otherwise

**Detection Logic**:
```php
return ($item['is_donation'] ?? false) === true
    || ($item['is_donation'] ?? false) === 'yes'
    || ($item['OfficeGuyDonation'] ?? false) === 'yes'
    || ($item['OfficeGuyDonation'] ?? false) === true;
```

**Supported Field Patterns**:

| Field Name | Valid Values | Source |
|------------|--------------|--------|
| `is_donation` | `true`, `'yes'` | Laravel convention |
| `OfficeGuyDonation` | `true`, `'yes'` | WooCommerce legacy field |

**Why Multiple Patterns?**

The service checks multiple field names for **backward compatibility** with the WooCommerce plugin:

1. **`is_donation`**: Modern Laravel convention (boolean or string)
2. **`OfficeGuyDonation`**: Original WooCommerce field name (preserved for data migration)

This allows seamless migration from WooCommerce to Laravel without changing database schemas.

---

### 4. `hasMixedItems()`

**Purpose**: Check if order/cart contains BOTH donations AND regular products (mixed cart).

**Signature**:
```php
public static function hasMixedItems(Payable|array $orderOrItems): bool
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: `true` if cart contains both types, `false` otherwise

**Algorithm**:
```php
return containsDonation($orderOrItems) && containsNonDonation($orderOrItems);
```

**Use Case**: Prevent invalid cart composition (donations and regular products cannot be mixed).

---

### 5. `validateCart()`

**Purpose**: Validate that cart doesn't have mixed donations and regular products. Returns validation result with error message if invalid.

**Signature**:
```php
public static function validateCart(Payable|array $orderOrItems): array
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: Associative array with keys:
- `valid` (bool): `true` if valid, `false` if invalid
- `message` (string): Error message (empty if valid)

**Port From**: `UpdateAvailableGateways()` logic in `OfficeGuyDonation.php`

**Validation Rule**: **Donations and regular products cannot be in the same order.**

**Return Values**:

```php
// Valid cart (all donations OR all regular items)
[
    'valid' => true,
    'message' => '',
]

// Invalid cart (mixed items)
[
    'valid' => false,
    'message' => 'Donations cannot be combined with regular products in the same order. Please complete separate orders.',
]
```

**Example Usage**:
```php
$validation = DonationService::validateCart($order);

if (!$validation['valid']) {
    throw new \Exception($validation['message']);
}
```

**Integration Point**: This method should be called in your checkout flow BEFORE processing payment:

```php
// In CheckoutController or PaymentService
public function processPayment(Payable $order): array
{
    // Validate cart composition
    $validation = DonationService::validateCart($order);

    if (!$validation['valid']) {
        return [
            'success' => false,
            'error' => $validation['message'],
        ];
    }

    // Continue with payment processing
    // ...
}
```

---

### 6. `getDocumentType()`

**Purpose**: Get the SUMIT document type identifier for the order (string format).

**Signature**:
```php
public static function getDocumentType(Payable|array $orderOrItems): string
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: Document type string:
- `"DonationReceipt"` (for donations-only orders)
- `"1"` (default: Invoice/Receipt for regular orders)

**Algorithm**:
```
1. Check if order contains donations (containsDonation)
2. Check if order contains non-donations (containsNonDonation)
3. If ONLY donations (no regular items):
   → Return 'DonationReceipt'
4. Otherwise:
   → Return '1' (default document type)
```

**Use Case**: Pass to `DocumentService::createDocument()` to generate the correct document type.

**Example**:
```php
$documentType = DonationService::getDocumentType($order);

DocumentService::createDocument(
    order: $order,
    customer: $customer,
    paymentDescription: 'Credit Card',
    documentType: $documentType  // ← 'DonationReceipt' or '1'
);
```

---

### 7. `getDocumentTypeCode()`

**Purpose**: Get the numeric SUMIT document type code for the order (integer format).

**Signature**:
```php
public static function getDocumentTypeCode(Payable|array $orderOrItems): int
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: Document type code (integer):
- `320` (for donations-only orders)
- `1` (default: Invoice/Receipt for regular orders)

**Algorithm**: Same as `getDocumentType()` but returns integer codes instead of strings.

**Use Case**: Used internally by `DocumentService` when building API requests to SUMIT.

**SUMIT API Document Type Codes**:

| Code | Type | Hebrew Name | Use Case |
|------|------|-------------|----------|
| `1` | Invoice/Receipt | חשבונית/קבלה | Regular sales |
| `2` | Receipt | קבלה | Payment receipt |
| `3` | Credit Note | זיכוי | Refunds/corrections |
| `8` | Order | הזמנה | Order confirmation |
| `320` | Donation Receipt | תעודת זיכוי | Tax-deductible donations |

**Example**:
```php
$typeCode = DonationService::getDocumentTypeCode($order);

if ($typeCode === 320) {
    // This is a donation - special handling required
    // Ensure customer has ID number for tax deduction
}
```

---

### 8. `splitItems()`

**Purpose**: Separate order items into two groups: donations and regular products.

**Signature**:
```php
public static function splitItems(Payable|array $orderOrItems): array
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: Associative array with keys:
- `donations` (array): All donation items
- `regular` (array): All non-donation items

**Algorithm**:
```
1. Extract items (from Payable::getLineItems() or use raw array)
2. Initialize empty arrays: $donations, $regular
3. Loop through each item:
   a. If isDonationItem() → add to $donations
   b. Else → add to $regular
4. Return ['donations' => [...], 'regular' => [...]]
```

**Use Case**: Reporting, analytics, or generating separate invoices for mixed carts (if allowed by configuration).

**Example**:
```php
$split = DonationService::splitItems($order);

$donationCount = count($split['donations']);
$regularCount = count($split['regular']);

// Generate separate reports
Report::generate('Donations', $split['donations']);
Report::generate('Sales', $split['regular']);
```

**Output Format**:
```php
[
    'donations' => [
        [
            'name' => 'Annual Donation',
            'unit_price' => 1000.00,
            'quantity' => 1,
            'is_donation' => true,
        ],
    ],
    'regular' => [
        [
            'name' => 'T-Shirt',
            'unit_price' => 50.00,
            'quantity' => 2,
            'is_donation' => false,
        ],
    ],
]
```

---

### 9. `getDonationTotal()`

**Purpose**: Calculate the **total amount** for donation items only (excludes regular products).

**Signature**:
```php
public static function getDonationTotal(Payable|array $orderOrItems): float
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: Total donation amount (rounded to 2 decimal places)

**Algorithm**:
```
1. Extract items (from Payable::getLineItems() or use raw array)
2. Initialize $total = 0
3. Loop through each item:
   a. If isDonationItem():
      → $total += (unit_price × quantity)
4. Return round($total, 2)
```

**Use Case**: Display donation total separately on checkout page or generate donation summary reports.

**Example**:
```php
$donationTotal = DonationService::getDonationTotal($order);
$regularTotal = DonationService::getRegularTotal($order);

echo "Donation Amount: {$donationTotal} ILS (tax deductible)";
echo "Product Total: {$regularTotal} ILS";
```

**Calculation Details**:

```php
// Given items:
$items = [
    ['name' => 'Donation', 'unit_price' => 500, 'quantity' => 1, 'is_donation' => true],
    ['name' => 'Shirt', 'unit_price' => 100, 'quantity' => 2, 'is_donation' => false],
    ['name' => 'Extra Donation', 'unit_price' => 250, 'quantity' => 2, 'is_donation' => true],
];

// Donation Total:
// (500 × 1) + (250 × 2) = 500 + 500 = 1000.00 ILS
$donationTotal = DonationService::getDonationTotal($items); // 1000.00
```

---

### 10. `getRegularTotal()`

**Purpose**: Calculate the **total amount** for non-donation items only (excludes donations).

**Signature**:
```php
public static function getRegularTotal(Payable|array $orderOrItems): float
```

**Parameters**:
- `$orderOrItems`: `Payable` instance or raw array of line items

**Returns**: Total regular product amount (rounded to 2 decimal places)

**Algorithm**:
```
1. Extract items (from Payable::getLineItems() or use raw array)
2. Initialize $total = 0
3. Loop through each item:
   a. If NOT isDonationItem():
      → $total += (unit_price × quantity)
4. Return round($total, 2)
```

**Use Case**: Display regular product total separately, or generate sales reports excluding donations.

**Example**:
```php
// Using the same items from getDonationTotal() example:
$regularTotal = DonationService::getRegularTotal($items); // 200.00

// Regular Total:
// (100 × 2) = 200.00 ILS
```

---

## Tax Benefits & Legal Requirements

### Israeli Tax Law Overview

**Who Can Issue Donation Receipts?**

Only registered **non-profit organizations (amutot/עמותות)** with **Section 46 tax exemption status** from the Israeli Tax Authority.

**What is Section 46?**

Section 46 of the Israeli Income Tax Ordinance grants tax-exempt status to qualified non-profits. Organizations with this status can issue donation receipts (תעודת זיכוי) that allow donors to claim tax deductions.

**How to Obtain Section 46 Status?**

1. Register as a non-profit (amuta) with the Registrar of Non-Profits
2. Apply to the Israeli Tax Authority for Section 46 exemption
3. Meet criteria: public benefit, no profit distribution, proper governance
4. Receive approval letter from Tax Authority (valid for 3-5 years)
5. Renew status periodically

### Tax Deduction Mechanics

**For Donors (2026 Tax Year)**:

| Donation Amount | Tax Deduction (35%) | Estimated Tax Savings (50% marginal rate) |
|-----------------|---------------------|-------------------------------------------|
| 1,000 ILS | 350 ILS | 175 ILS |
| 5,000 ILS | 1,750 ILS | 875 ILS |
| 10,000 ILS | 3,500 ILS | 1,750 ILS |
| 50,000 ILS | 17,500 ILS | 8,750 ILS |
| 100,000 ILS | 35,000 ILS | 17,500 ILS |

**Maximum Deduction Limits**:

- **Individuals**: Up to 30% of taxable income OR 2.5M ILS (whichever is lower)
- **Companies**: Up to 30% of taxable profit

**Example Calculation**:

```
Donor: Individual with 200,000 ILS annual income
Donation: 50,000 ILS to qualified NPO

Step 1: Check maximum deduction limit
→ 30% of 200,000 ILS = 60,000 ILS (allowed)

Step 2: Calculate deduction
→ 35% of 50,000 ILS = 17,500 ILS

Step 3: Calculate tax savings (at 50% marginal rate)
→ 17,500 ILS × 50% = 8,750 ILS saved

Net Cost of Donation: 50,000 - 8,750 = 41,250 ILS
```

### Required Documentation

**On Donation Receipt (תעודת זיכוי)**:

✅ **Donor Information**:
- Full legal name (as appears on ID)
- Israeli ID number (teudat zehut)
- Address (optional but recommended)

✅ **Donation Details**:
- Donation amount (in ILS)
- Date of donation
- Payment method
- Receipt number (unique identifier)

✅ **Organization Information**:
- Registered organization name
- Organization number (from Tax Authority)
- Section 46 approval number
- Organization address
- Signature of authorized signatory

✅ **Legal Disclaimer** (recommended):
```
"This receipt is valid for tax deduction purposes in Israel
for donations made to organizations with Section 46 tax-exempt status."
```

### Record Keeping Requirements

**Non-Profit Organizations Must**:

- Maintain all donation receipts for **7 years** (Israeli law)
- Keep donor records (name, ID, amount, date)
- Store Section 46 approval documents
- Generate annual donation reports for Tax Authority
- Issue receipts within the same tax year as donation

**Donors Must**:

- Keep original donation receipts for 7 years
- Include receipts with annual tax return (Form 1301)
- Declare donations on Schedule 1 (Appendix 1)

### Compliance & Audits

**Tax Authority Audits**:

The Israeli Tax Authority may audit:

1. Organization's Section 46 status (verify eligibility)
2. Donation receipt records (verify authenticity)
3. Donor claims (cross-check with organization records)

**Penalties for Violations**:

- Issuing invalid receipts: Fines + loss of Section 46 status
- False donor claims: Tax penalties + interest

### Best Practices

✅ **Do**:
- Verify Section 46 status is current before issuing receipts
- Issue receipts within 30 days of donation
- Include all required fields on receipts
- Maintain digital and physical backups
- Generate annual donation reports

❌ **Don't**:
- Issue receipts without Section 46 approval
- Backdate receipts to previous tax years
- Issue receipts for goods/services received (not donations)
- Forget to include donor ID numbers

---

## Integration with Other Services

### DocumentService Integration

The `DonationService` integrates seamlessly with `DocumentService` to generate proper donation receipts.

**Flow**:

```
DonationService::getDocumentType($order)
       ↓
Returns 'DonationReceipt' (if donations only)
       ↓
DocumentService::createDocument($order, $customer, 'Credit Card', 'DonationReceipt')
       ↓
Generates document with type 320 (תעודת זיכוי)
       ↓
OfficeGuyDocument model saved with document_type = '320'
```

**Example**:

```php
use OfficeGuy\LaravelSumitGateway\Services\DonationService;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

// After successful payment
$order = Order::find($orderId);

// Determine document type
$documentType = DonationService::getDocumentType($order);

// Create document (donation receipt or invoice)
$error = DocumentService::createDocument(
    order: $order,
    customer: $customer,
    paymentDescription: 'Credit Card',
    documentType: $documentType
);

if ($error) {
    \Log::error("Failed to create document: {$error}");
}
```

### PaymentService Integration

The `PaymentService` can use `DonationService` to validate carts before processing payments.

**Flow**:

```
PaymentService::processPayment($order)
       ↓
DonationService::validateCart($order)
       ↓
If invalid (mixed items):
   → Return error: "Cannot mix donations with regular products"
       ↓
If valid:
   → Continue with payment processing
```

**Example**:

```php
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\DonationService;

class PaymentService
{
    public static function processPayment(Payable $order): array
    {
        // Validate cart composition
        $validation = DonationService::validateCart($order);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['message'],
            ];
        }

        // Check if this is a donation order
        $isDonation = DonationService::containsDonation($order)
                      && !DonationService::containsNonDonation($order);

        // Process payment with SUMIT API
        // ...

        return [
            'success' => true,
            'is_donation' => $isDonation,
        ];
    }
}
```

### OfficeGuyTransaction Model Integration

The `OfficeGuyTransaction` model includes an `is_donation` column to track donation transactions.

**Migration** (`2025_01_01_000007_add_donation_and_vendor_fields.php`):

```php
if (! Schema::hasColumn('officeguy_transactions', 'is_donation')) {
    $table->boolean('is_donation')->default(false)->after('is_upsell');
}
```

**Usage**:

```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\DonationService;

// After creating transaction
$transaction = OfficeGuyTransaction::create([
    'order_id' => $order->id,
    'amount' => $order->getTotalAmount(),
    'status' => 'Success',
    'is_donation' => DonationService::containsDonation($order)
                     && !DonationService::containsNonDonation($order),
]);

// Later: Query donation transactions
$donationTransactions = OfficeGuyTransaction::where('is_donation', true)->get();
$totalDonations = $donationTransactions->sum('amount');
```

### Filament Admin Integration

**Admin Dashboard Widget** (example):

```php
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class DonationStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $donationTransactions = OfficeGuyTransaction::where('is_donation', true)
            ->where('status', 'Success')
            ->whereYear('created_at', now()->year);

        return [
            Stat::make('Total Donations (2026)', number_format($donationTransactions->sum('amount'), 2) . ' ILS'),
            Stat::make('Donation Count', $donationTransactions->count()),
            Stat::make('Avg Donation', number_format($donationTransactions->avg('amount'), 2) . ' ILS'),
        ];
    }
}
```

**TransactionResource Filter**:

```php
use Filament\Tables\Filters\Filter;

public static function table(Table $table): Table
{
    return $table
        ->filters([
            Filter::make('is_donation')
                ->label('Donations Only')
                ->query(fn (Builder $query) => $query->where('is_donation', true)),
        ]);
}
```

---

## Configuration

### Config File Settings

**File**: `config/officeguy.php`

```php
'donations' => [
    'enabled' => env('OFFICEGUY_DONATIONS_ENABLED', true),
    'allow_mixed_cart' => env('OFFICEGUY_DONATIONS_ALLOW_MIXED', false),
    'default_document_type' => env('OFFICEGUY_DONATIONS_DOCUMENT_TYPE', '320'),
],
```

### Settings Breakdown

#### 1. `donations.enabled`

**Type**: `boolean`
**Default**: `true`
**Environment Variable**: `OFFICEGUY_DONATIONS_ENABLED`

**Purpose**: Enable/disable donation functionality globally.

**Usage**:
```php
if (config('officeguy.donations.enabled')) {
    // Show donation products
}
```

**When to Disable**:
- Organization loses Section 46 status
- Temporarily disable donations during site maintenance
- Non-profit switches to different platform

---

#### 2. `donations.allow_mixed_cart`

**Type**: `boolean`
**Default**: `false` ⚠️
**Environment Variable**: `OFFICEGUY_DONATIONS_ALLOW_MIXED`

**Purpose**: Allow donations and regular products in the same cart.

**Default Behavior** (`false`):
- `DonationService::validateCart()` returns error if mixed items detected
- Customers must complete separate orders for donations and products

**When Set to `true`**:
- Mixed carts are allowed
- Your application must handle splitting items for document generation
- Generate TWO documents: one donation receipt (320) + one invoice (1)

**Example** (if allowing mixed carts):

```php
if (config('officeguy.donations.allow_mixed_cart') && DonationService::hasMixedItems($order)) {
    // Split items
    $split = DonationService::splitItems($order);

    // Create donation receipt for donation items
    DocumentService::createDocument($order, $customer, 'Credit Card', 'DonationReceipt');

    // Create invoice for regular items
    DocumentService::createDocument($order, $customer, 'Credit Card', '1');
}
```

**⚠️ Warning**: Allowing mixed carts requires custom implementation. The default package behavior assumes separate orders.

---

#### 3. `donations.default_document_type`

**Type**: `string` (numeric code)
**Default**: `'320'`
**Environment Variable**: `OFFICEGUY_DONATIONS_DOCUMENT_TYPE`

**Purpose**: Specify the SUMIT document type code for donation receipts.

**Valid Values**:
- `'320'`: Donation Receipt (תעודת זיכוי) - **RECOMMENDED**
- `'1'`: Invoice/Receipt (חשבונית/קבלה) - Not valid for tax deductions

**Usage**:
```php
$documentType = config('officeguy.donations.default_document_type'); // '320'
```

**When to Change**: Never. Always use `320` for donation receipts to ensure tax deductibility.

---

### Environment Variable Setup

**File**: `.env`

```env
# Donation Settings
OFFICEGUY_DONATIONS_ENABLED=true
OFFICEGUY_DONATIONS_ALLOW_MIXED=false
OFFICEGUY_DONATIONS_DOCUMENT_TYPE=320
```

### Admin Settings Page Integration

**File**: `src/Filament/Pages/OfficeGuySettings.php`

Add donation settings tab:

```php
use Filament\Forms;

Schemas\Tabs\Tab::make('Donations')
    ->schema([
        Forms\Components\Toggle::make('donations.enabled')
            ->label('Enable Donation Receipts')
            ->helperText('Allow issuing donation receipts (תעודת זיכוי). Requires Section 46 tax-exempt status.')
            ->default(true),

        Forms\Components\Toggle::make('donations.allow_mixed_cart')
            ->label('Allow Mixed Carts')
            ->helperText('Allow donations and regular products in the same order (requires custom implementation).')
            ->default(false),

        Forms\Components\TextInput::make('donations.default_document_type')
            ->label('Donation Document Type Code')
            ->helperText('SUMIT document type for donation receipts (default: 320)')
            ->default('320')
            ->disabled() // Don't allow changing this
            ->dehydrated(true),

        Forms\Components\Placeholder::make('donation_warning')
            ->content('**Important**: Only issue donation receipts if your organization has Section 46 tax-exempt status from the Israeli Tax Authority.')
            ->columnSpanFull(),
    ]),
```

---

## Best Practices

### 1. Cart Validation

**Always validate carts** before processing payments:

```php
// In CheckoutController or PaymentService
public function processPayment(Request $request, Order $order): RedirectResponse
{
    // Validate donation cart composition
    $validation = DonationService::validateCart($order);

    if (!$validation['valid']) {
        return back()->withErrors(['cart' => $validation['message']]);
    }

    // Continue with payment processing
    // ...
}
```

### 2. Document Type Selection

**Let DonationService determine document type**:

```php
// ✅ Good: Let DonationService decide
$documentType = DonationService::getDocumentType($order);
DocumentService::createDocument($order, $customer, 'Credit Card', $documentType);

// ❌ Bad: Hardcoding document type
DocumentService::createDocument($order, $customer, 'Credit Card', '320'); // What if it's not a donation?
```

### 3. Transaction Tracking

**Always set `is_donation` flag** on transactions:

```php
// ✅ Good: Track donation status
$transaction = OfficeGuyTransaction::create([
    'order_id' => $order->id,
    'amount' => $order->getTotalAmount(),
    'status' => 'Success',
    'is_donation' => DonationService::containsDonation($order)
                     && !DonationService::containsNonDonation($order),
]);

// ❌ Bad: Forgetting to set is_donation
$transaction = OfficeGuyTransaction::create([
    'order_id' => $order->id,
    'amount' => $order->getTotalAmount(),
    'status' => 'Success',
    // Missing is_donation flag!
]);
```

### 4. Customer ID Validation

**Ensure donor has Israeli ID** for tax deductions:

```php
// In checkout validation
if (DonationService::containsDonation($order)) {
    $validator = Validator::make($request->all(), [
        'citizen_id' => 'required|digits:9', // Israeli ID is 9 digits
    ], [
        'citizen_id.required' => 'Israeli ID number is required for donation receipts.',
        'citizen_id.digits' => 'Israeli ID must be exactly 9 digits.',
    ]);

    if ($validator->fails()) {
        return back()->withErrors($validator);
    }
}
```

### 5. Reporting & Analytics

**Use `splitItems()` and totals** for reporting:

```php
// Monthly donation report
$orders = Order::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->get();

$donationTotal = 0;
$regularTotal = 0;

foreach ($orders as $order) {
    $donationTotal += DonationService::getDonationTotal($order);
    $regularTotal += DonationService::getRegularTotal($order);
}

Report::generate([
    'donations' => $donationTotal,
    'sales' => $regularTotal,
    'total' => $donationTotal + $regularTotal,
]);
```

### 6. Error Handling

**Handle validation errors gracefully**:

```php
try {
    $validation = DonationService::validateCart($order);

    if (!$validation['valid']) {
        // Log error
        \Log::warning('Mixed donation cart rejected', [
            'order_id' => $order->id,
            'message' => $validation['message'],
        ]);

        // Show user-friendly error
        return response()->json([
            'error' => $validation['message'],
            'suggestion' => 'Please remove either donation items or regular products and try again.',
        ], 400);
    }
} catch (\Exception $e) {
    \Log::error('Donation validation failed', [
        'order_id' => $order->id,
        'exception' => $e->getMessage(),
    ]);

    return response()->json([
        'error' => 'Unable to validate cart. Please try again.',
    ], 500);
}
```

### 7. Testing

**Write tests for donation scenarios**:

```php
use Tests\TestCase;
use OfficeGuy\LaravelSumitGateway\Services\DonationService;

class DonationServiceTest extends TestCase
{
    public function test_donation_item_is_detected(): void
    {
        $item = ['name' => 'Donation', 'is_donation' => true];

        $this->assertTrue(DonationService::isDonationItem($item));
    }

    public function test_mixed_cart_is_rejected(): void
    {
        $items = [
            ['name' => 'Donation', 'is_donation' => true, 'unit_price' => 500, 'quantity' => 1],
            ['name' => 'Shirt', 'is_donation' => false, 'unit_price' => 100, 'quantity' => 1],
        ];

        $validation = DonationService::validateCart($items);

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('cannot be combined', $validation['message']);
    }

    public function test_donation_total_calculated_correctly(): void
    {
        $items = [
            ['name' => 'Donation 1', 'is_donation' => true, 'unit_price' => 500, 'quantity' => 1],
            ['name' => 'Shirt', 'is_donation' => false, 'unit_price' => 100, 'quantity' => 2],
            ['name' => 'Donation 2', 'is_donation' => true, 'unit_price' => 250, 'quantity' => 2],
        ];

        $donationTotal = DonationService::getDonationTotal($items);

        // (500 × 1) + (250 × 2) = 1000.00
        $this->assertEquals(1000.00, $donationTotal);
    }
}
```

---

## Code Examples

### Example 1: Complete Donation Checkout Flow

```php
use OfficeGuy\LaravelSumitGateway\Services\DonationService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use App\Models\Order;

class CheckoutController extends Controller
{
    public function processPayment(Request $request, Order $order)
    {
        // Step 1: Validate cart composition
        $validation = DonationService::validateCart($order);

        if (!$validation['valid']) {
            return back()->withErrors(['cart' => $validation['message']]);
        }

        // Step 2: Check if this is a donation order
        $isDonation = DonationService::containsDonation($order)
                      && !DonationService::containsNonDonation($order);

        // Step 3: If donation, validate customer has ID number
        if ($isDonation) {
            $request->validate([
                'citizen_id' => 'required|digits:9',
            ], [
                'citizen_id.required' => 'Israeli ID number is required for donation receipts.',
            ]);
        }

        // Step 4: Process payment
        $paymentResult = PaymentService::processPayment($order);

        if (!$paymentResult['success']) {
            return back()->withErrors(['payment' => $paymentResult['error']]);
        }

        // Step 5: Determine document type
        $documentType = DonationService::getDocumentType($order);

        // Step 6: Generate document (donation receipt or invoice)
        $customer = [
            'name' => $order->customer_name,
            'citizen_id' => $request->input('citizen_id'),
            'email' => $order->customer_email,
            'phone' => $order->customer_phone,
        ];

        $error = DocumentService::createDocument(
            order: $order,
            customer: $customer,
            paymentDescription: 'Credit Card',
            documentType: $documentType
        );

        if ($error) {
            \Log::error("Failed to create document: {$error}");
        }

        // Step 7: Record transaction
        OfficeGuyTransaction::create([
            'order_id' => $order->id,
            'transaction_id' => $paymentResult['transaction_id'],
            'amount' => $order->getTotalAmount(),
            'status' => 'Success',
            'is_donation' => $isDonation,
        ]);

        // Step 8: Show success message
        $message = $isDonation
            ? 'Thank you for your donation! Your tax receipt has been emailed to you.'
            : 'Payment successful! Your invoice has been emailed to you.';

        return redirect()->route('order.success', $order)->with('success', $message);
    }
}
```

---

### Example 2: Admin Donation Report

```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\DonationService;
use App\Models\Order;

class DonationReportController extends Controller
{
    public function generateAnnualReport(int $year)
    {
        // Get all successful donation transactions for the year
        $transactions = OfficeGuyTransaction::where('is_donation', true)
            ->where('status', 'Success')
            ->whereYear('created_at', $year)
            ->with('order')
            ->get();

        $report = [
            'year' => $year,
            'total_donations' => $transactions->sum('amount'),
            'donation_count' => $transactions->count(),
            'avg_donation' => $transactions->avg('amount'),
            'monthly_breakdown' => [],
        ];

        // Monthly breakdown
        for ($month = 1; $month <= 12; $month++) {
            $monthlyTransactions = $transactions->filter(fn($t) => $t->created_at->month === $month);

            $report['monthly_breakdown'][$month] = [
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'count' => $monthlyTransactions->count(),
                'total' => $monthlyTransactions->sum('amount'),
            ];
        }

        // Generate PDF or Excel export
        return view('reports.donations', compact('report'));
    }
}
```

---

### Example 3: Filament Client Panel - Donation History

```php
use Filament\Resources\Resource;
use Filament\Tables;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

class ClientDonationHistoryResource extends Resource
{
    protected static ?string $model = OfficeGuyTransaction::class;
    protected static ?string $navigationLabel = 'My Donations';
    protected static ?string $navigationIcon = 'heroicon-o-heart';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) =>
                $query->where('is_donation', true)
                      ->where('user_id', auth()->id())
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Donation Date')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('ILS')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Success' => 'success',
                        'Pending' => 'warning',
                        'Failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('document.document_number')
                    ->label('Receipt Number')
                    ->url(fn (OfficeGuyTransaction $record) =>
                        route('documents.download', $record->document_id)
                    ),
            ])
            ->filters([
                Tables\Filters\Filter::make('year')
                    ->form([
                        Forms\Components\Select::make('year')
                            ->options(range(now()->year, now()->year - 10))
                            ->default(now()->year),
                    ])
                    ->query(fn (Builder $query, array $data) =>
                        $query->whereYear('created_at', $data['year'] ?? now()->year)
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('download_receipt')
                    ->label('Download Receipt')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (OfficeGuyTransaction $record) =>
                        route('documents.download', $record->document_id)
                    ),
            ]);
    }
}
```

---

### Example 4: API Endpoint - Donation Summary

```php
use Illuminate\Http\Request;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;
use OfficeGuy\LaravelSumitGateway\Services\DonationService;

class DonationApiController extends Controller
{
    /**
     * Get donation summary for authenticated user
     *
     * GET /api/donations/summary?year=2026
     */
    public function getSummary(Request $request)
    {
        $year = $request->input('year', now()->year);
        $userId = auth()->id();

        $transactions = OfficeGuyTransaction::where('is_donation', true)
            ->where('status', 'Success')
            ->where('user_id', $userId)
            ->whereYear('created_at', $year)
            ->get();

        $totalDonations = $transactions->sum('amount');
        $taxDeduction = $totalDonations * 0.35; // 35% deduction
        $estimatedSavings = $taxDeduction * 0.50; // Assuming 50% marginal rate

        return response()->json([
            'year' => $year,
            'summary' => [
                'total_donations' => $totalDonations,
                'donation_count' => $transactions->count(),
                'tax_deduction' => round($taxDeduction, 2),
                'estimated_tax_savings' => round($estimatedSavings, 2),
            ],
            'transactions' => $transactions->map(fn($t) => [
                'date' => $t->created_at->format('d/m/Y'),
                'amount' => $t->amount,
                'receipt_number' => $t->document?->document_number,
                'receipt_url' => route('documents.download', $t->document_id),
            ]),
        ]);
    }
}
```

---

## Migration from WooCommerce

### Differences from WooCommerce Plugin

The Laravel `DonationService` is a **1:1 port** of the WooCommerce `OfficeGuyDonation.php` class, with modernizations:

| Feature | WooCommerce Plugin | Laravel Package |
|---------|-------------------|-----------------|
| Donation Detection | `get_post_meta($product_id, 'OfficeGuyDonation')` | `$item['is_donation']` or `$item['OfficeGuyDonation']` |
| Cart Validation | `UpdateAvailableGateways()` hook | `DonationService::validateCart()` method |
| Item Iteration | `$Order->get_items()` | `Payable::getLineItems()` interface |
| Document Type | Hardcoded in payment gateway | `DonationService::getDocumentType()` |
| Admin UI | WooCommerce product checkbox | Your application's product management |

### Migration Steps

**Step 1: Export WooCommerce Products**

```sql
-- Export products marked as donations
SELECT
    p.ID as product_id,
    p.post_title as name,
    pm.meta_value as is_donation
FROM wp_posts p
JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'product'
  AND pm.meta_key = 'OfficeGuyDonation'
  AND pm.meta_value = 'yes';
```

**Step 2: Import to Laravel**

```php
use App\Models\Product;

// In your migration/seeder
$wooProducts = DB::connection('woocommerce')->table('wp_posts')
    ->join('wp_postmeta', 'wp_posts.ID', '=', 'wp_postmeta.post_id')
    ->where('wp_posts.post_type', 'product')
    ->where('wp_postmeta.meta_key', 'OfficeGuyDonation')
    ->where('wp_postmeta.meta_value', 'yes')
    ->get();

foreach ($wooProducts as $wooProduct) {
    Product::create([
        'name' => $wooProduct->post_title,
        'is_donation' => true, // ← Laravel convention
        // ... other fields
    ]);
}
```

**Step 3: Update Product Model**

```php
// database/migrations/xxxx_add_is_donation_to_products.php
Schema::table('products', function (Blueprint $table) {
    $table->boolean('is_donation')->default(false)->after('price');
});

// app/Models/Product.php
class Product extends Model
{
    protected $fillable = ['name', 'price', 'is_donation'];

    protected $casts = [
        'is_donation' => 'boolean',
    ];

    public function isDonation(): bool
    {
        return $this->is_donation === true;
    }
}
```

**Step 4: Update Order Item Format**

Ensure your `Payable::getLineItems()` returns items in the correct format:

```php
// app/Models/Order.php (implement Payable interface)
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

class Order extends Model implements Payable
{
    public function getLineItems(): array
    {
        return $this->items->map(function ($item) {
            return [
                'name' => $item->product->name,
                'unit_price' => $item->price,
                'quantity' => $item->quantity,
                'is_donation' => $item->product->is_donation, // ← Required for DonationService
                // or keep WooCommerce compatibility:
                // 'OfficeGuyDonation' => $item->product->is_donation ? 'yes' : 'no',
            ];
        })->toArray();
    }
}
```

**Step 5: Update Checkout Flow**

```php
// Replace WooCommerce hooks with direct method calls
// Before (WooCommerce):
// add_filter('woocommerce_available_payment_gateways', 'OfficeGuyDonation::UpdateAvailableGateways');

// After (Laravel):
$validation = DonationService::validateCart($order);
if (!$validation['valid']) {
    return back()->withErrors(['cart' => $validation['message']]);
}
```

### Backward Compatibility

The package supports **both** field naming conventions:

```php
// Modern Laravel convention
$item = ['name' => 'Donation', 'is_donation' => true];

// WooCommerce legacy convention
$item = ['name' => 'Donation', 'OfficeGuyDonation' => 'yes'];

// Both work!
DonationService::isDonationItem($item); // true for both
```

This allows gradual migration without breaking existing data.

---

## Summary

The `DonationService` is a **critical component** for non-profit organizations using the SUMIT payment gateway to issue **tax-deductible donation receipts (תעודת זיכוי)** in Israel.

### Key Takeaways

✅ **Core Functionality**:
- Detects donation items via `is_donation` or `OfficeGuyDonation` fields
- Validates cart composition (prevents mixing donations with regular products)
- Determines correct document type (320 = DonationReceipt)
- Calculates donation totals separately from regular product totals

✅ **Tax Benefits**:
- Donors can deduct 35% of donation amount from taxable income
- Requires Section 46 tax-exempt status from Israeli Tax Authority
- Organizations must maintain records for 7 years

✅ **Integration**:
- Works seamlessly with `DocumentService` to generate donation receipts
- Integrates with `PaymentService` for cart validation
- Supports Filament admin/client panels for donation tracking
- Backward compatible with WooCommerce field names

✅ **Best Practices**:
- Always validate carts before processing payments
- Set `is_donation` flag on transactions
- Validate customer has Israeli ID for tax deductions
- Use `getDocumentType()` instead of hardcoding document types

### When to Use

Use `DonationService` if your application:

1. Serves **non-profit organizations** with Section 46 tax-exempt status
2. Needs to issue **tax-deductible donation receipts** (תעודת זיכוי)
3. Wants to **track donations separately** from regular sales
4. Requires **cart validation** to prevent mixing donations with products

### When NOT to Use

Skip `DonationService` if:

1. Your organization is **not a registered non-profit** in Israel
2. You **don't have Section 46 tax-exempt status**
3. You're selling **regular products only** (no donations)
4. Your application doesn't need donation tracking

### Final Notes

The `DonationService` is a **stateless, static service** that requires no instantiation. All methods are **pure functions** (no side effects), making them easy to test and integrate.

For questions or support, contact **NM-DigitalHub** at info@nm-digitalhub.com.

---

**Document Version**: 1.0.0
**Generated**: 2026-01-13
**Author**: Claude Code + NM-DigitalHub
**License**: MIT (same as package)
