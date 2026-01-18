# PayableMappingService Analysis

**File**: `src/Services/PayableMappingService.php`
**Purpose**: Zero-code integration for existing models with Payable interface
**Pattern**: Instance Service with CRUD operations
**Created**: 2025-01-27 (Latest addition to package)
**Package Version**: v1.1.7+

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Problem Solved](#core-problem-solved)
4. [Relationship to Payable Contract](#relationship-to-payable-contract)
5. [Database Schema](#database-schema)
6. [Methods Analysis](#methods-analysis)
7. [Payable Field Metadata System](#payable-field-metadata-system)
8. [Integration Patterns](#integration-patterns)
9. [Usage Examples](#usage-examples)
10. [Comparison to PayableAdapter](#comparison-to-payableadapter)
11. [Best Practices](#best-practices)
12. [Summary](#summary)

---

## Overview

The `PayableMappingService` is a **zero-code integration bridge** that allows existing Laravel models to work with the SUMIT payment gateway **without modifying the model or adding traits**. It provides a database-driven field mapping system that translates your model's fields to the Payable interface contract.

### Core Responsibilities

- **Field Mapping Management**: CRUD operations for model-to-Payable field mappings
- **Metadata Provider**: Comprehensive information about all 16 Payable interface fields
- **Category Grouping**: Organizes fields into logical categories (core, customer, items, tax)
- **Validation Support**: Identifies required vs optional fields
- **Zero-Code Integration**: No need to modify existing models

### Key Features

✅ **Database-Driven**: Mappings stored in `payable_field_mappings` table
✅ **Runtime Configurable**: Create/update mappings via Admin UI or code
✅ **Non-Invasive**: Existing models remain unchanged
✅ **Comprehensive Metadata**: 16 fields with Hebrew/English labels, examples, descriptions
✅ **Category Organization**: Fields grouped into 4 categories (core, customer, items, tax)
✅ **Activation Control**: Enable/disable mappings without deleting them
✅ **Instance-Based**: Uses dependency injection (consistent with WebhookService, CustomerMergeService)

---

## Architecture

### Service Type

**Instance Service Class** (Consistent Pattern ✅)

```php
class PayableMappingService
{
    // All methods are instance-based (no static methods)
    public function getMappingForModel(Model|string $model): ?array { }
    public function upsertMapping(string $modelClass, array $fieldMappings, ?string $label = null): PayableFieldMapping { }
}

// Usage
$service = app(PayableMappingService::class);
$mapping = $service->getMappingForModel(Product::class);
```

**Consistency**: This service follows the **instance-based pattern** used by:
- ✅ WebhookService
- ✅ CustomerMergeService
- ✅ FulfillmentDispatcher
- ❌ Unlike MultiVendorPaymentService (static) - inconsistency

### Design Patterns

#### 1. **Repository Pattern**

The service acts as a repository for `PayableFieldMapping` models:

```php
// Repository methods
public function getMappingForModel(Model|string $model): ?array
public function getAllMappings(): Collection
public function upsertMapping(...): PayableFieldMapping
public function deleteMapping(string $modelClass): bool
```

#### 2. **Metadata Provider Pattern**

Provides rich metadata about Payable interface fields:

```php
public function getPayableFields(): array  // All 16 fields with metadata
public function getPayableFieldsByCategory(): array  // Grouped by category
public function getRequiredPayableFields(): array  // Only required (7 fields)
public function getOptionalPayableFields(): array  // Only optional (9 fields)
```

#### 3. **Bridge Pattern**

Acts as a bridge between existing models and the Payable interface:

```
┌─────────────────────────┐
│ Existing Model          │
│ (Product, Order, etc.)  │
└──────────┬──────────────┘
           │
           │ field_mappings: {
           │   "amount": "final_price_ils",
           │   "customer_name": "client.name"
           │ }
           │
           ▼
┌─────────────────────────┐
│ PayableMappingService   │ ← Bridge
└──────────┬──────────────┘
           │
           │ DynamicPayableWrapper
           │ uses mappings to
           │ implement Payable
           │
           ▼
┌─────────────────────────┐
│ Payable Interface       │
│ (16 required methods)   │
└─────────────────────────┘
```

---

## Core Problem Solved

### The Integration Challenge

When integrating SUMIT payment gateway with an existing Laravel application, you have models like `Product`, `Order`, `Invoice` that need to work with the payment system. The SUMIT package requires these models to implement the `Payable` interface (16 methods).

**Traditional Approaches** (Before PayableMappingService):

#### ❌ Approach 1: Direct Implementation (Invasive)

```php
// Must modify existing model
class Product extends Model implements Payable
{
    public function getPayableId(): string|int
    {
        return $this->id;
    }

    public function getPayableAmount(): float
    {
        return $this->final_price_ils;
    }

    public function getCustomerName(): string
    {
        return $this->client->name;
    }

    // ... 13 more methods
}
```

**Problems**:
- ❌ Modifies existing models
- ❌ Couples business logic with payment logic
- ❌ Hard to maintain when models change
- ❌ Difficult for third-party packages

#### ❌ Approach 2: PayableAdapter Trait (Less Invasive)

```php
// Still modifies model, but less code
class Product extends Model
{
    use PayableAdapter;

    protected array $payableFieldMap = [
        'amount' => 'final_price_ils',
        'customer_name' => 'client.name',
        // ... more mappings
    ];
}
```

**Problems**:
- ❌ Still modifies model (adds trait)
- ❌ Mappings hardcoded in model
- ❌ No runtime configuration
- ❌ Difficult for non-developers to configure

---

### ✅ Solution: PayableMappingService (Zero-Code Integration)

```php
// NO model modification required!
// Configure mapping via Admin UI or code:

$service = app(PayableMappingService::class);
$service->upsertMapping(
    modelClass: Product::class,
    fieldMappings: [
        'payable_id' => 'id',
        'amount' => 'final_price_ils',
        'currency' => 'ILS',
        'customer_name' => 'client.name',
        'customer_email' => 'client.email',
        'customer_phone' => 'client.phone',
        'customer_id' => 'client_id',
        'line_items' => '[]',
        'shipping_amount' => '0',
        'fees' => '[]',
        'tax_enabled' => 'true',
        // ... more fields
    ],
    label: 'Product Payment Mapping'
);

// Now use Product as Payable without modifying it:
$wrapper = new DynamicPayableWrapper($product);
$result = PaymentService::charge($wrapper);
```

**Advantages**:
- ✅ **Zero Code Changes**: Existing models remain untouched
- ✅ **Runtime Configuration**: Change mappings via database
- ✅ **Admin UI Friendly**: Configure via Filament UI
- ✅ **Multi-Environment**: Different mappings per environment
- ✅ **Version Controlled**: Mappings in DB, not code

---

## Relationship to Payable Contract

### Payable Interface (16 Methods)

**File**: `src/Contracts/Payable.php`

The Payable interface defines 16 methods that any billable entity must implement:

```php
interface Payable
{
    // Core Payment Info (3 methods)
    public function getPayableId(): string|int;
    public function getPayableAmount(): float;
    public function getPayableCurrency(): string;

    // Customer Information (7 methods)
    public function getCustomerEmail(): ?string;
    public function getCustomerPhone(): ?string;
    public function getCustomerName(): string;
    public function getCustomerAddress(): ?array;
    public function getCustomerCompany(): ?string;
    public function getCustomerId(): string|int|null;
    public function getCustomerNote(): ?string;

    // Order Items & Costs (4 methods)
    public function getLineItems(): array;
    public function getShippingAmount(): float;
    public function getShippingMethod(): ?string;
    public function getFees(): array;

    // Tax (2 methods)
    public function getVatRate(): ?float;
    public function isTaxEnabled(): bool;
}
```

### PayableMappingService's Role

The service provides **metadata and mapping storage** for these 16 methods:

| Payable Method | Mapped Field | Stored In | Retrieved Via |
|----------------|--------------|-----------|---------------|
| `getPayableId()` | `'id'` | `payable_field_mappings.field_mappings` JSON | `getMappingForModel()` |
| `getPayableAmount()` | `'final_price_ils'` | Same JSON | Same |
| `getCustomerName()` | `'client.name'` | Same JSON | Same |
| ... | ... | ... | ... |

**Flow Diagram**:

```
┌─────────────────────────────────────────────────────────────────┐
│ Developer Configures Mapping (One-Time Setup)                   │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ PayableMappingService::upsertMapping(Product::class, [...])    │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ Database: payable_field_mappings Table                          │
│ {                                                               │
│   model_class: "App\\Models\\Product",                          │
│   field_mappings: {                                             │
│     "payable_id": "id",                                         │
│     "amount": "final_price_ils",                                │
│     "customer_name": "client.name"                              │
│   }                                                             │
│ }                                                               │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       │
┌──────────────────────┴──────────────────────────────────────────┐
│ Runtime: Payment Processing                                     │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ DynamicPayableWrapper::__construct($product)                    │
│ - Loads mapping from PayableMappingService                      │
│ - Implements Payable interface dynamically                      │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ DynamicPayableWrapper::getPayableAmount()                       │
│ {                                                               │
│   $mapping = $this->mappings['amount'];  // "final_price_ils"  │
│   return data_get($this->model, $mapping);  // $product->final_price_ils │
│ }                                                               │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ PaymentService::charge($wrapper)                                │
│ - Calls $wrapper->getPayableAmount()                            │
│ - Gets $product->final_price_ils via mapping                    │
└─────────────────────────────────────────────────────────────────┘
```

### Metadata Support for Payable Interface

The service provides **comprehensive metadata** for all 16 Payable fields:

```php
$service = app(PayableMappingService::class);
$fields = $service->getPayableFields();

// Returns array with full metadata for each field:
[
    'payable_id' => [
        'key' => 'payable_id',
        'label_he' => 'מזהה ייחודי',
        'label_en' => 'Unique ID',
        'method' => 'getPayableId()',
        'return_type' => 'string|int',
        'required' => true,
        'example' => 'id',
        'category' => 'core',
        'description_he' => 'מזהה ייחודי של הפריט הניתן לתשלום (בדרך כלל Primary Key)',
    ],
    'amount' => [
        'key' => 'amount',
        'label_he' => 'סכום לתשלום',
        'label_en' => 'Payment Amount',
        'method' => 'getPayableAmount()',
        'return_type' => 'float',
        'required' => true,
        'example' => 'final_price_ils',
        'category' => 'core',
        'description_he' => 'הסכום הכולל לחיוב (כולל מע"מ אם tax_enabled מופעל)',
    ],
    // ... 14 more fields
]
```

**Use Cases for Metadata**:
1. **Admin UI Forms**: Generate mapping form with Hebrew labels
2. **Validation**: Ensure required fields are mapped
3. **Documentation**: Auto-generate integration guides
4. **Examples**: Show developers example field names

---

## Database Schema

### `payable_field_mappings` Table

**Migration**: `database/migrations/2025_01_27_000001_create_payable_field_mappings_table.php`

```sql
CREATE TABLE payable_field_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Unique model identifier
    model_class VARCHAR(255) UNIQUE NOT NULL
        COMMENT 'Fully qualified model class name',

    -- User-friendly label
    label VARCHAR(255) NULL
        COMMENT 'User-friendly label for this mapping',

    -- JSON field mappings
    field_mappings JSON NOT NULL
        COMMENT 'JSON mapping of Payable fields to model fields',

    -- Activation control
    is_active BOOLEAN DEFAULT TRUE
        COMMENT 'Whether this mapping is active',

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Indexes
    INDEX idx_is_active (is_active)
);
```

**Index Strategy**:
- `UNIQUE(model_class)`: One mapping per model class
- `INDEX(is_active)`: Fast filtering of active mappings

### Example Record

```json
{
    "id": 1,
    "model_class": "App\\Models\\Product",
    "label": "Product Payment Mapping",
    "field_mappings": {
        "payable_id": "id",
        "amount": "final_price_ils",
        "currency": "ILS",
        "customer_name": "client.name",
        "customer_email": "client.email",
        "customer_phone": "client.phone",
        "customer_id": "client_id",
        "customer_address": "shipping_address",
        "customer_company": "client.company_name",
        "customer_note": "description",
        "line_items": "[]",
        "shipping_amount": "0",
        "shipping_method": null,
        "fees": "[]",
        "vat_rate": "0.17",
        "tax_enabled": "true"
    },
    "is_active": true,
    "created_at": "2025-01-27 10:00:00",
    "updated_at": "2025-01-27 10:00:00"
}
```

**Field Mapping Types**:

| Type | Example | Description |
|------|---------|-------------|
| **Direct Field** | `"id"` | Direct model property |
| **Nested Field** | `"client.name"` | Eloquent relationship via dot notation |
| **Literal Value** | `"ILS"` | Static value (for currency, defaults) |
| **Empty Array** | `"[]"` | Empty array literal |
| **Boolean** | `"true"` | Boolean literal |
| **Numeric** | `"0.17"` | Numeric literal |
| **Null** | `null` | Null value (optional fields) |

---

## Methods Analysis

### `getMappingForModel(Model|string $model): ?array`

**Purpose**: Retrieve field mappings for a specific model class.

**Parameters**:
- `$model` (Model|string): Model instance or fully qualified class name

**Return Value**: Array of field mappings or `null` if not found

**Implementation**:
```php
public function getMappingForModel(Model|string $model): ?array
{
    $modelClass = is_string($model) ? $model : get_class($model);

    $mapping = PayableFieldMapping::forModel($modelClass)
        ->active()
        ->first();

    return $mapping?->field_mappings;
}
```

**SQL Query**:
```sql
SELECT field_mappings
FROM payable_field_mappings
WHERE model_class = 'App\\Models\\Product'
  AND is_active = 1
LIMIT 1;
```

**Use Cases**:
- DynamicPayableWrapper initialization
- Validation before payment processing
- Admin UI display of current mappings

**Example**:
```php
$service = app(PayableMappingService::class);

// By model instance
$product = Product::find(1);
$mappings = $service->getMappingForModel($product);

// By class string
$mappings = $service->getMappingForModel(Product::class);

// Returns:
[
    'payable_id' => 'id',
    'amount' => 'final_price_ils',
    'customer_name' => 'client.name',
    // ...
]
```

---

### `upsertMapping(string $modelClass, array $fieldMappings, ?string $label = null): PayableFieldMapping`

**Purpose**: Create or update a field mapping (UPSERT operation).

**Parameters**:
- `$modelClass` (string): Fully qualified model class name
- `$fieldMappings` (array): Mapping of Payable fields to model fields
- `$label` (string|null): User-friendly label (defaults to class basename)

**Return Value**: PayableFieldMapping instance

**Implementation**:
```php
public function upsertMapping(
    string $modelClass,
    array $fieldMappings,
    ?string $label = null
): PayableFieldMapping {
    return PayableFieldMapping::updateOrCreate(
        ['model_class' => $modelClass],  // Where clause
        [
            'field_mappings' => $fieldMappings,
            'label' => $label ?? class_basename($modelClass) . ' Mapping',
        ]
    );
}
```

**SQL Query** (if exists):
```sql
UPDATE payable_field_mappings
SET field_mappings = '{"payable_id":"id", ...}',
    label = 'Product Mapping',
    updated_at = NOW()
WHERE model_class = 'App\\Models\\Product';
```

**SQL Query** (if not exists):
```sql
INSERT INTO payable_field_mappings (model_class, field_mappings, label, is_active, created_at, updated_at)
VALUES ('App\\Models\\Product', '{"payable_id":"id", ...}', 'Product Mapping', 1, NOW(), NOW());
```

**Use Cases**:
- Admin UI "Save Mapping" action
- Package installation seeder
- API endpoint for creating mappings
- Testing setup

**Example**:
```php
$service = app(PayableMappingService::class);

$mapping = $service->upsertMapping(
    modelClass: Product::class,
    fieldMappings: [
        'payable_id' => 'id',
        'amount' => 'final_price_ils',
        'currency' => 'ILS',
        'customer_name' => 'client.name',
        'customer_email' => 'client.email',
        'line_items' => '[]',
        'shipping_amount' => '0',
        'fees' => '[]',
        'tax_enabled' => 'true',
    ],
    label: 'Product Payment Integration'
);

// Returns: PayableFieldMapping instance with id, timestamps, etc.
```

---

### `deleteMapping(string $modelClass): bool`

**Purpose**: Permanently delete a mapping.

**Parameters**:
- `$modelClass` (string): Fully qualified model class name

**Return Value**: `true` if deleted, `false` if not found

**Implementation**:
```php
public function deleteMapping(string $modelClass): bool
{
    return PayableFieldMapping::forModel($modelClass)->delete() > 0;
}
```

**SQL Query**:
```sql
DELETE FROM payable_field_mappings
WHERE model_class = 'App\\Models\\Product';
```

**Use Cases**:
- Admin UI "Delete Mapping" action
- Package uninstallation cleanup
- Testing teardown

**Example**:
```php
$service = app(PayableMappingService::class);

if ($service->deleteMapping(Product::class)) {
    echo "Mapping deleted successfully";
} else {
    echo "Mapping not found";
}
```

**⚠️ Warning**: This is a **hard delete**. Consider using `deactivateMapping()` instead for soft-disable.

---

### `getAllMappings(): Collection`

**Purpose**: Retrieve all active mappings.

**Return Value**: Collection of PayableFieldMapping models

**Implementation**:
```php
public function getAllMappings(): Collection
{
    return PayableFieldMapping::active()->get();
}
```

**SQL Query**:
```sql
SELECT *
FROM payable_field_mappings
WHERE is_active = 1
ORDER BY id;
```

**Use Cases**:
- Admin UI "All Mappings" table
- System health check
- Analytics dashboard

**Example**:
```php
$service = app(PayableMappingService::class);
$mappings = $service->getAllMappings();

foreach ($mappings as $mapping) {
    echo "{$mapping->label}: {$mapping->short_model_name}\n";
    echo "  Mapped fields: {$mapping->mapped_fields_count}\n";
}

// Output:
// Product Mapping: Product
//   Mapped fields: 12
// Invoice Mapping: Invoice
//   Mapped fields: 14
```

---

### `deactivateMapping(string $modelClass): bool`

**Purpose**: Soft-disable a mapping without deleting it.

**Parameters**:
- `$modelClass` (string): Fully qualified model class name

**Return Value**: `true` if deactivated, `false` if not found

**Implementation**:
```php
public function deactivateMapping(string $modelClass): bool
{
    return PayableFieldMapping::forModel($modelClass)
        ->update(['is_active' => false]) > 0;
}
```

**SQL Query**:
```sql
UPDATE payable_field_mappings
SET is_active = 0,
    updated_at = NOW()
WHERE model_class = 'App\\Models\\Product';
```

**Use Cases**:
- Temporarily disable payment for model
- A/B testing different mappings
- Staging environment isolation

**Example**:
```php
$service = app(PayableMappingService::class);

// Deactivate temporarily
$service->deactivateMapping(Product::class);

// Later, re-activate
$service->activateMapping(Product::class);
```

**Advantages over `deleteMapping()`**:
- ✅ Preserves mapping configuration
- ✅ Reversible (can re-activate)
- ✅ Maintains audit trail (created_at unchanged)

---

### `activateMapping(string $modelClass): bool`

**Purpose**: Re-enable a previously deactivated mapping.

**Parameters**:
- `$modelClass` (string): Fully qualified model class name

**Return Value**: `true` if activated, `false` if not found

**Implementation**:
```php
public function activateMapping(string $modelClass): bool
{
    return PayableFieldMapping::where('model_class', $modelClass)
        ->update(['is_active' => true]) > 0;
}
```

**SQL Query**:
```sql
UPDATE payable_field_mappings
SET is_active = 1,
    updated_at = NOW()
WHERE model_class = 'App\\Models\\Product';
```

**Use Cases**:
- Re-enable after testing
- Seasonal product activation
- Feature flag control

**Example**:
```php
$service = app(PayableMappingService::class);

if ($service->activateMapping(Product::class)) {
    echo "Mapping activated successfully";
}
```

---

## Payable Field Metadata System

### `getPayableFields(): array`

**Purpose**: Get comprehensive metadata for all 16 Payable interface fields.

**Return Value**: Array with metadata for each field

**Implementation**: Returns a hardcoded array with full metadata (lines 113-297)

**Metadata Structure**:

Each field contains:
- `key` (string): Field identifier (e.g., "payable_id")
- `label_he` (string): Hebrew label for Admin UI
- `label_en` (string): English label for Admin UI
- `method` (string): Payable interface method name
- `return_type` (string): PHP return type
- `required` (bool): Whether field is required
- `example` (string): Example model field name
- `category` (string): Category (core, customer, items, tax)
- `description_he` (string): Hebrew description

**Complete Field List** (16 fields):

#### Category: Core Payment Info (3 fields)

| Key | Label (EN) | Method | Type | Required |
|-----|------------|--------|------|----------|
| `payable_id` | Unique ID | `getPayableId()` | `string\|int` | ✅ |
| `amount` | Payment Amount | `getPayableAmount()` | `float` | ✅ |
| `currency` | Currency Code | `getPayableCurrency()` | `string` | ✅ |

#### Category: Customer Information (7 fields)

| Key | Label (EN) | Method | Type | Required |
|-----|------------|--------|------|----------|
| `customer_name` | Customer Name | `getCustomerName()` | `string` | ✅ |
| `customer_email` | Customer Email | `getCustomerEmail()` | `?string` | ❌ |
| `customer_phone` | Customer Phone | `getCustomerPhone()` | `?string` | ❌ |
| `customer_id` | Customer ID | `getCustomerId()` | `string\|int\|null` | ❌ |
| `customer_address` | Customer Address | `getCustomerAddress()` | `?array` | ❌ |
| `customer_company` | Company Name | `getCustomerCompany()` | `?string` | ❌ |
| `customer_note` | Customer Note | `getCustomerNote()` | `?string` | ❌ |

#### Category: Order Items & Costs (4 fields)

| Key | Label (EN) | Method | Type | Required |
|-----|------------|--------|------|----------|
| `line_items` | Line Items | `getLineItems()` | `array` | ✅ |
| `shipping_amount` | Shipping Cost | `getShippingAmount()` | `float` | ✅ |
| `shipping_method` | Shipping Method | `getShippingMethod()` | `?string` | ❌ |
| `fees` | Additional Fees | `getFees()` | `array` | ✅ |

#### Category: Tax (2 fields)

| Key | Label (EN) | Method | Type | Required |
|-----|------------|--------|------|----------|
| `vat_rate` | VAT Rate | `getVatRate()` | `?float` | ❌ |
| `tax_enabled` | Tax Enabled | `isTaxEnabled()` | `bool` | ✅ |

**Example Usage**:
```php
$service = app(PayableMappingService::class);
$fields = $service->getPayableFields();

// Generate Admin UI form
foreach ($fields as $field) {
    echo "<label>{$field['label_he']}</label>";
    echo "<input name='{$field['key']}' placeholder='{$field['example']}' />";
    echo "<small>{$field['description_he']}</small>";
}
```

---

### `getPayableFieldsByCategory(): array`

**Purpose**: Get Payable fields grouped by category.

**Return Value**: Array grouped by category (core, customer, items, tax)

**Implementation**:
```php
public function getPayableFieldsByCategory(): array
{
    $allFields = $this->getPayableFields();
    $grouped = [];

    foreach ($allFields as $field) {
        $category = $field['category'];
        $grouped[$category][$field['key']] = $field;
    }

    return $grouped;
}
```

**Return Structure**:
```php
[
    'core' => [
        'payable_id' => [...],
        'amount' => [...],
        'currency' => [...],
    ],
    'customer' => [
        'customer_name' => [...],
        'customer_email' => [...],
        // ... 5 more
    ],
    'items' => [
        'line_items' => [...],
        'shipping_amount' => [...],
        // ... 2 more
    ],
    'tax' => [
        'vat_rate' => [...],
        'tax_enabled' => [...],
    ],
]
```

**Use Cases**:
- Admin UI with tabbed interface
- Step-by-step mapping wizard
- Validation by category

**Example**:
```blade
<!-- Blade template with tabs -->
<x-filament::tabs>
    @foreach($service->getPayableFieldsByCategory() as $category => $fields)
        <x-filament::tabs.item id="{{ $category }}">
            {{ ucfirst($category) }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
```

---

### `getRequiredPayableFields(): array`

**Purpose**: Get only required Payable fields (7 fields).

**Implementation**:
```php
public function getRequiredPayableFields(): array
{
    return array_filter(
        $this->getPayableFields(),
        fn ($field) => $field['required']
    );
}
```

**Return Value**: Array with 7 required fields:
1. `payable_id`
2. `amount`
3. `currency`
4. `customer_name`
5. `line_items`
6. `shipping_amount`
7. `fees`
8. `tax_enabled`

**Use Cases**:
- Validation before payment
- Admin UI required field indicators
- Error messages for incomplete mappings

**Example**:
```php
$service = app(PayableMappingService::class);
$mapping = $service->getMappingForModel(Product::class);
$required = $service->getRequiredPayableFields();

// Validate all required fields are mapped
$missing = [];
foreach ($required as $field) {
    if (empty($mapping[$field['key']])) {
        $missing[] = $field['label_he'];
    }
}

if (!empty($missing)) {
    throw new Exception("Missing required fields: " . implode(', ', $missing));
}
```

---

### `getOptionalPayableFields(): array`

**Purpose**: Get only optional Payable fields (9 fields).

**Implementation**:
```php
public function getOptionalPayableFields(): array
{
    return array_filter(
        $this->getPayableFields(),
        fn ($field) => !$field['required']
    );
}
```

**Return Value**: Array with 9 optional fields:
1. `customer_email`
2. `customer_phone`
3. `customer_id`
4. `customer_address`
5. `customer_company`
6. `customer_note`
7. `shipping_method`
8. `vat_rate`

**Use Cases**:
- Admin UI "Optional" section
- Progressive disclosure (show after required fields)
- Documentation generation

---

## Integration Patterns

### Pattern 1: Admin Seeder (Package Installation)

```php
namespace Database\Seeders;

use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;
use Illuminate\Database\Seeder;

class PayableMappingsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PayableMappingService::class);

        // Product mapping
        $service->upsertMapping(
            modelClass: \App\Models\Product::class,
            fieldMappings: [
                'payable_id' => 'id',
                'amount' => 'final_price_ils',
                'currency' => 'ILS',
                'customer_name' => 'client.name',
                'customer_email' => 'client.email',
                'customer_phone' => 'client.phone',
                'customer_id' => 'client_id',
                'line_items' => '[]',
                'shipping_amount' => '0',
                'fees' => '[]',
                'tax_enabled' => 'true',
            ],
            label: 'Product Payment Mapping'
        );

        // Invoice mapping
        $service->upsertMapping(
            modelClass: \App\Models\Invoice::class,
            fieldMappings: [
                'payable_id' => 'id',
                'amount' => 'total',
                'currency' => 'currency_code',
                'customer_name' => 'customer_name',
                'customer_email' => 'customer_email',
                'customer_id' => 'customer_id',
                'line_items' => 'items',
                'shipping_amount' => 'shipping_cost',
                'fees' => 'additional_fees',
                'vat_rate' => 'vat_percentage',
                'tax_enabled' => 'includes_vat',
            ],
            label: 'Invoice Payment Mapping'
        );
    }
}
```

---

### Pattern 2: Filament Admin Resource

```php
namespace App\Filament\Resources;

use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;
use OfficeGuy\LaravelSumitGateway\Models\PayableFieldMapping;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Tables;

class PayableMappingResource extends Resource
{
    protected static ?string $model = PayableFieldMapping::class;

    public static function form(Form $form): Form
    {
        $service = app(PayableMappingService::class);
        $fields = $service->getPayableFields();

        return $form->schema([
            Forms\Components\TextInput::make('model_class')
                ->label('Model Class')
                ->required()
                ->placeholder('App\\Models\\Product'),

            Forms\Components\TextInput::make('label')
                ->label('Mapping Label')
                ->placeholder('Product Payment Mapping'),

            Forms\Components\Section::make('Field Mappings')
                ->schema(
                    collect($fields)->map(fn($field) =>
                        Forms\Components\TextInput::make("field_mappings.{$field['key']}")
                            ->label($field['label_he'])
                            ->helperText($field['description_he'])
                            ->placeholder($field['example'])
                            ->required($field['required'])
                    )->toArray()
                ),

            Forms\Components\Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('short_model_name')
                ->label('Model')
                ->searchable(),

            Tables\Columns\TextColumn::make('label')
                ->label('Label')
                ->searchable(),

            Tables\Columns\TextColumn::make('mapped_fields_count')
                ->label('Mapped Fields')
                ->badge(),

            Tables\Columns\ToggleColumn::make('is_active')
                ->label('Active'),
        ]);
    }
}
```

---

### Pattern 3: Runtime Wrapper Usage

```php
namespace App\Services;

use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;
use OfficeGuy\LaravelSumitGateway\Support\DynamicPayableWrapper;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use App\Models\Product;

class CheckoutService
{
    public function __construct(
        protected PayableMappingService $mappingService,
        protected PaymentService $paymentService
    ) {}

    public function processProductPayment(Product $product, string $token): array
    {
        // Get mapping
        $mappings = $this->mappingService->getMappingForModel($product);

        if (!$mappings) {
            throw new \Exception("No payment mapping configured for Product");
        }

        // Wrap model with Payable interface
        $wrapper = new DynamicPayableWrapper($product, $mappings);

        // Process payment
        return $this->paymentService->charge([
            'payable' => $wrapper,
            'token' => $token,
            'installments' => 1,
        ]);
    }
}
```

---

## Usage Examples

### Example 1: Basic Mapping Creation

```php
use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;
use App\Models\Product;

$service = app(PayableMappingService::class);

$mapping = $service->upsertMapping(
    modelClass: Product::class,
    fieldMappings: [
        'payable_id' => 'id',
        'amount' => 'final_price_ils',
        'currency' => 'ILS',
        'customer_name' => 'client.name',
        'customer_email' => 'client.email',
        'line_items' => '[]',
        'shipping_amount' => '0',
        'fees' => '[]',
        'tax_enabled' => 'true',
    ]
);

echo "Mapping created with ID: {$mapping->id}";
```

---

### Example 2: Validation Before Payment

```php
use OfficeGuy\LaravelSumitGateway\Services\PayableMappingService;

$service = app(PayableMappingService::class);
$mapping = $service->getMappingForModel($product);

if (!$mapping) {
    return back()->withErrors(['payment' => 'Payment not configured for this product']);
}

// Validate required fields
$required = $service->getRequiredPayableFields();
$missing = [];

foreach ($required as $field) {
    if (empty($mapping[$field['key']])) {
        $missing[] = $field['label_he'];
    }
}

if (!empty($missing)) {
    return back()->withErrors([
        'payment' => 'Missing required mappings: ' . implode(', ', $missing)
    ]);
}

// Proceed with payment
$wrapper = new DynamicPayableWrapper($product, $mapping);
$result = PaymentService::charge($wrapper);
```

---

### Example 3: Admin UI Field List

```php
@php
    $service = app(\OfficeGuy\LaravelSumitGateway\Services\PayableMappingService::class);
    $categories = $service->getPayableFieldsByCategory();
@endphp

<x-filament::tabs>
    @foreach($categories as $category => $fields)
        <x-filament::tabs.item id="{{ $category }}">
            {{ ucfirst($category) }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>

<div class="mt-4">
    @foreach($categories as $category => $fields)
        <div id="content-{{ $category }}" class="tab-content">
            <h3>{{ ucfirst($category) }} Fields</h3>
            <table>
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Label (Hebrew)</th>
                        <th>Required</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fields as $field)
                        <tr>
                            <td>{{ $field['key'] }}</td>
                            <td>{{ $field['label_he'] }}</td>
                            <td>{{ $field['required'] ? '✅' : '❌' }}</td>
                            <td><code>{{ $field['example'] }}</code></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
```

---

## Comparison to PayableAdapter

### PayableAdapter Trait (Existing Solution)

**File**: `src/Support/Traits/PayableAdapter.php`

```php
// Model must use trait
class Product extends Model
{
    use PayableAdapter;

    protected array $payableFieldMap = [
        'amount' => 'final_price_ils',
        'customer_name' => 'client.name',
        // ... hardcoded mappings
    ];
}
```

**Characteristics**:
- ❌ Modifies model (adds trait)
- ❌ Hardcoded mappings in model
- ❌ No runtime configuration
- ✅ Type-safe (PHP code)
- ✅ IDE autocomplete

---

### PayableMappingService (New Solution)

```php
// NO model modification!
// Configure via database:
$service->upsertMapping(Product::class, [...]);

// Use DynamicPayableWrapper
$wrapper = new DynamicPayableWrapper($product);
```

**Characteristics**:
- ✅ Zero code changes to model
- ✅ Database-driven configuration
- ✅ Runtime configurable (Admin UI)
- ✅ Multi-environment support
- ❌ No type safety (string-based)
- ❌ No IDE autocomplete

---

### When to Use Each?

| Scenario | Use PayableAdapter | Use PayableMappingService |
|----------|-------------------|---------------------------|
| **You control the model** | ✅ | ✅ |
| **Third-party package model** | ❌ | ✅ |
| **Need IDE autocomplete** | ✅ | ❌ |
| **Need runtime configuration** | ❌ | ✅ |
| **Different mappings per environment** | ❌ | ✅ |
| **Admin-configurable** | ❌ | ✅ |
| **Type safety** | ✅ | ❌ |

**Recommendation**: Use **PayableMappingService** for:
- Third-party package models
- Admin-configurable systems
- Multi-environment deployments

Use **PayableAdapter** for:
- Internal models you control
- Need type safety and IDE support
- Mappings won't change at runtime

---

## Best Practices

### 1. Always Validate Mappings Before Payment

❌ **WRONG**:
```php
$wrapper = new DynamicPayableWrapper($product);
$result = PaymentService::charge($wrapper);  // May fail!
```

✅ **CORRECT**:
```php
$service = app(PayableMappingService::class);
$mapping = $service->getMappingForModel($product);

if (!$mapping) {
    throw new Exception("No payment mapping configured");
}

// Validate required fields
$required = $service->getRequiredPayableFields();
foreach ($required as $field) {
    if (empty($mapping[$field['key']])) {
        throw new Exception("Missing required field: {$field['label_he']}");
    }
}

// Now safe to proceed
$wrapper = new DynamicPayableWrapper($product, $mapping);
$result = PaymentService::charge($wrapper);
```

---

### 2. Use Seeder for Initial Setup

✅ **Create seeder** for package installation:
```php
// database/seeders/PayableMappingsSeeder.php
public function run(): void
{
    $service = app(PayableMappingService::class);

    $service->upsertMapping(Product::class, [
        'payable_id' => 'id',
        'amount' => 'final_price_ils',
        // ... complete mappings
    ]);
}
```

**Run on installation**:
```bash
php artisan db:seed --class=PayableMappingsSeeder
```

---

### 3. Provide Admin UI for Non-Developers

✅ **Create Filament resource** for mapping management:
```php
namespace App\Filament\Resources;

class PayableMappingResource extends Resource
{
    // Allow admins to configure mappings via UI
}
```

**Why**: Non-technical admins can configure payment integration without code changes.

---

### 4. Use Category Grouping in UI

✅ **Organize fields by category** for better UX:
```php
$categories = $service->getPayableFieldsByCategory();

// Display in tabs: Core | Customer | Items | Tax
foreach ($categories as $category => $fields) {
    // Render category section
}
```

---

### 5. Test Mappings Before Production

✅ **Create test** to validate mappings:
```php
namespace Tests\Feature;

class PayableMappingTest extends TestCase
{
    public function test_product_mapping_is_complete()
    {
        $service = app(PayableMappingService::class);
        $mapping = $service->getMappingForModel(Product::class);

        $this->assertNotNull($mapping);

        // Validate all required fields are mapped
        $required = $service->getRequiredPayableFields();
        foreach ($required as $field) {
            $this->assertArrayHasKey($field['key'], $mapping);
            $this->assertNotEmpty($mapping[$field['key']]);
        }
    }
}
```

---

## Summary

### Key Takeaways

1. **Zero-Code Integration**: Enable payment processing without modifying existing models
2. **Database-Driven**: Mappings stored in `payable_field_mappings` table
3. **16 Payable Fields**: Comprehensive metadata for all Payable interface methods
4. **4 Categories**: Fields organized into core, customer, items, tax
5. **Runtime Configuration**: Change mappings via Admin UI without code deployment
6. **Instance-Based Service**: Consistent with WebhookService, CustomerMergeService
7. **Validation Support**: Identify required vs optional fields
8. **Admin-Friendly**: Designed for configuration via Filament UI

---

### Relationship to Payable Contract

```
Payable Interface (16 methods)
        ↕
PayableMappingService (metadata + storage)
        ↕
payable_field_mappings table (JSON mappings)
        ↕
DynamicPayableWrapper (runtime implementation)
        ↕
Your Model (unchanged!)
```

---

### When to Use This Service

✅ **Use PayableMappingService when**:
- Integrating third-party package models
- Need admin-configurable payment fields
- Want zero-code integration
- Different mappings per environment
- Building SaaS with customer-configurable mappings

❌ **Don't use PayableMappingService for**:
- Internal models you fully control (use PayableAdapter trait)
- Need strong type safety (use direct implementation)
- Simple single-environment deployments

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-13
**Maintainer**: NM-DigitalHub
**Package**: officeguy/laravel-sumit-gateway v1.1.7+
