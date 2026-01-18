# MultiVendorPaymentService Analysis

**File**: `src/Services/MultiVendorPaymentService.php`
**Purpose**: Handle multi-vendor marketplace payments with vendor-specific SUMIT credentials
**Pattern**: Static Service with Strategy Pattern (vendor resolver callback)
**Created**: Ported from WooCommerce plugin (2024)
**Package Version**: v1.1.6+

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Port Origins](#port-origins)
4. [Core Concepts](#core-concepts)
5. [Methods Analysis](#methods-analysis)
6. [Multi-Vendor Flow](#multi-vendor-flow)
7. [Vendor Resolution Strategy](#vendor-resolution-strategy)
8. [Integration Examples](#integration-examples)
9. [Inconsistencies](#inconsistencies)
10. [Best Practices](#best-practices)
11. [Summary](#summary)

---

## Overview

The `MultiVendorPaymentService` enables marketplace-style payment splitting where a single order can contain products from multiple vendors, and each vendor is charged separately using their own SUMIT credentials. This is essential for platforms like Dokan, WCFM Marketplace, and WC Vendors.

### Core Responsibilities

- **Vendor Resolution**: Identify which vendor owns each product in the order
- **Item Grouping**: Group line items by vendor for separate transactions
- **Credential Management**: Retrieve vendor-specific SUMIT credentials
- **Split Charging**: Process separate payments for each vendor's items
- **Event Dispatching**: Fire success/failure events for multi-vendor payments

### Key Features

✅ **Vendor-Agnostic**: Works with any marketplace plugin via resolver callback
✅ **Zero-Code Integration**: Configurable via callback, no code changes needed
✅ **Split Transactions**: Each vendor gets a separate transaction record
✅ **Independent Credentials**: Each vendor uses their own SUMIT account
✅ **Fallback Support**: Items without vendor use store credentials
✅ **Event-Driven**: Dispatches MultiVendorPaymentCompleted/Failed events

---

## Architecture

### Service Type

**Static Service Class** (Inconsistent with other services)

```php
class MultiVendorPaymentService
{
    protected static $vendorResolver = null;  // Static property

    public static function processMultiVendorCharge(...)  // Static methods
    {
        // All methods are static
    }
}
```

**Inconsistency**: This service is static, but `WebhookService`, `CustomerMergeService`, and `FulfillmentDispatcher` use instance-based DI. This creates inconsistency in the codebase.

### Design Patterns

#### 1. **Strategy Pattern** (Vendor Resolver)

The service uses a configurable callback to resolve vendors, allowing zero-code integration with any marketplace plugin:

```php
// Set resolver in AppServiceProvider
MultiVendorPaymentService::setVendorResolver(function (array $item) {
    return Product::find($item['product_id'])?->vendor;
});
```

#### 2. **Group-by Pattern**

Items are grouped by vendor using a normalized vendor key:

```php
$groups = [
    'default' => [],                              // Store items
    'App\Models\Vendor_123' => [...],            // Vendor 123's items
    'App\Models\Vendor_456' => [...],            // Vendor 456's items
];
```

#### 3. **Split-Charge Pattern**

Each vendor group is charged separately with its own credentials:

```
Order ($300) → Split by Vendor
    ├─ Vendor A ($100) → SUMIT charge with Vendor A credentials
    ├─ Vendor B ($150) → SUMIT charge with Vendor B credentials
    └─ Store ($50) → SUMIT charge with store credentials
```

---

## Port Origins

This service is a 1:1 port from **4 WooCommerce plugin files**:

### 1. **OfficeGuyMultiVendor.php** (Base)
- Core multi-vendor logic
- `HasMultipleVendorsInCart()` → `hasMultipleVendors()`
- `HasVendorInCart()` → `hasVendorItems()`

### 2. **OfficeGuyDokanMarketplace.php** (Dokan)
- Dokan-specific vendor resolution
- Grouped item charging

### 3. **OfficeGuyWCFMMarketplace.php** (WCFM)
- WCFM vendor detection
- Vendor count logic → `countVendors()`

### 4. **OfficeGuyWCVendorsMarketplace.php** (WC Vendors)
- WC Vendors integration
- Product-to-vendor mapping

**Laravel Adaptation**:
- Replaced WooCommerce globals (`$woocommerce`, `WC()`) with Laravel patterns
- Replaced `get_user_meta()` with Eloquent relationships
- Added Payable contract for order abstraction
- Static class instead of WordPress hooks

---

## Core Concepts

### 1. Vendor Entity

A **vendor** can be any of the following:

```php
// Eloquent Model
$vendor = App\Models\Vendor::find(123);

// VendorCredential model
$vendor = VendorCredential::find(456);

// Raw ID (integer/string)
$vendor = 789;
```

The service normalizes all vendor types into a string key:
```php
// Model: App\Models\Vendor_123
$vendorKey = get_class($vendor) . '_' . $vendor->getKey();

// Raw ID: 789
$vendorKey = (string) $vendor;
```

### 2. Line Items Structure

Expected line item array structure:

```php
$item = [
    'product_id' => 123,           // Product ID (required)
    'variation_id' => 456,         // Variation ID (optional)
    'name' => 'Product Name',      // Display name
    'sku' => 'PROD-123',          // Stock keeping unit
    'unit_price' => 50.00,        // Unit price
    'quantity' => 2,               // Quantity
    'vendor_id' => 789,           // Vendor ID (optional, overridden by resolver)
    'vendor' => $vendorModel,      // Vendor model (optional)
];
```

### 3. VendorCredential Model

Each vendor has their own SUMIT credentials:

```php
VendorCredential {
    id: 1
    vendor_id: 123                          // Vendor reference
    company_id: "1082100759"                // SUMIT Company ID
    private_key: "encrypted_key"            // SUMIT Private Key
    merchant_number: "1234567890"           // SUMIT Merchant Number
    is_active: true                         // Enable/disable
}
```

**Database Table**: `vendor_credentials`

**Retrieval**:
```php
// By vendor ID
$creds = VendorCredential::forVendor(123);

// By vendor model
$creds = $vendor->vendorCredential;
```

### 4. Default vs Vendor Items

Items are classified into two categories:

| Category | Description | Credentials Used |
|----------|-------------|------------------|
| **Default Items** | Products without vendor | Store SUMIT credentials |
| **Vendor Items** | Products with vendor | Vendor's SUMIT credentials |

**Example Order**:
```
Order Total: $300
├─ Item 1: Product A ($100) → No vendor → Store credentials
├─ Item 2: Product B ($150) → Vendor 123 → Vendor 123 credentials
└─ Item 3: Product C ($50) → Vendor 456 → Vendor 456 credentials

Results in 3 separate SUMIT charges:
1. Store charge for $100
2. Vendor 123 charge for $150
3. Vendor 456 charge for $50
```

---

## Methods Analysis

### `setVendorResolver(callable $resolver): void`

**Purpose**: Set the vendor resolver callback.

**Parameters**:
- `$resolver` (callable): Function that receives `array $item` and returns vendor (Model, ID, or null)

**Implementation**:
```php
public static function setVendorResolver(callable $resolver): void
{
    self::$vendorResolver = $resolver;
}
```

**Usage**:
```php
// In AppServiceProvider::boot()
MultiVendorPaymentService::setVendorResolver(function (array $item) {
    // Dokan plugin
    return dokan()->vendor->get($item['product_id']);

    // Or custom logic
    $product = Product::find($item['product_id']);
    return $product?->vendor;
});
```

**When to Use**:
- During application bootstrap (AppServiceProvider)
- When integrating with marketplace plugins
- For custom vendor resolution logic

---

### `getVendorForItem(array $item): mixed`

**Purpose**: Resolve the vendor for a specific line item.

**Resolution Priority**:
1. **Custom Resolver** (if set via `setVendorResolver()`)
2. **$item['vendor_id']** field
3. **$item['vendor']** field
4. **null** (no vendor)

**Implementation**:
```php
public static function getVendorForItem(array $item): mixed
{
    if (self::$vendorResolver) {
        return call_user_func(self::$vendorResolver, $item);
    }

    return $item['vendor_id'] ?? $item['vendor'] ?? null;
}
```

**Return Types**:
- `App\Models\Vendor` (Eloquent model)
- `VendorCredential` (Eloquent model)
- `int|string` (vendor ID)
- `null` (no vendor)

**Example**:
```php
$item = ['product_id' => 123, 'vendor_id' => 456];
$vendor = MultiVendorPaymentService::getVendorForItem($item);
// Returns: 456
```

---

### `getCredentialsForItem(array $item): ?VendorCredential`

**Purpose**: Retrieve the VendorCredential for a line item.

**Resolution Flow**:
```
1. Get vendor for item → getVendorForItem($item)
2. If no vendor → return null (use store credentials)
3. If vendor is VendorCredential → return it
4. If vendor has vendorCredential() method → return $vendor->vendorCredential
5. Otherwise → VendorCredential::forVendor($vendor)
```

**Implementation**:
```php
public static function getCredentialsForItem(array $item): ?VendorCredential
{
    $vendor = self::getVendorForItem($item);

    if (!$vendor) {
        return null;  // Use store credentials
    }

    if ($vendor instanceof VendorCredential) {
        return $vendor;
    }

    // If vendor model has credentials relationship
    if (is_object($vendor) && method_exists($vendor, 'vendorCredential')) {
        return $vendor->vendorCredential;
    }

    // Lookup by vendor ID
    return VendorCredential::forVendor($vendor);
}
```

**Return Values**:
- `VendorCredential` instance → Use vendor's SUMIT credentials
- `null` → Use store's SUMIT credentials (default)

**Example**:
```php
$item = ['product_id' => 123, 'vendor_id' => 456];
$creds = MultiVendorPaymentService::getCredentialsForItem($item);
// Returns: VendorCredential instance or null
```

---

### `groupItemsByVendor(Payable $order): array`

**Purpose**: Group order line items by vendor for separate charging.

**Implementation**:
```php
public static function groupItemsByVendor(Payable $order): array
{
    $grouped = [
        'default' => [],  // Items without vendor
    ];

    foreach ($order->getLineItems() as $item) {
        $vendor = self::getVendorForItem($item);

        if ($vendor) {
            // Create vendor key (normalized)
            $vendorKey = is_object($vendor)
                ? get_class($vendor) . '_' . $vendor->getKey()
                : (string) $vendor;

            if (!isset($grouped[$vendorKey])) {
                $grouped[$vendorKey] = [
                    'vendor' => $vendor,
                    'items' => [],
                ];
            }
            $grouped[$vendorKey]['items'][] = $item;
        } else {
            $grouped['default'][] = $item;
        }
    }

    // Remove empty default group
    if (empty($grouped['default'])) {
        unset($grouped['default']);
    }

    return $grouped;
}
```

**Return Structure**:
```php
[
    'default' => [
        ['product_id' => 1, 'unit_price' => 50.00, 'quantity' => 1],
        ['product_id' => 2, 'unit_price' => 30.00, 'quantity' => 2],
    ],
    'App\Models\Vendor_123' => [
        'vendor' => Vendor instance,
        'items' => [
            ['product_id' => 3, 'unit_price' => 100.00, 'quantity' => 1],
        ],
    ],
    'App\Models\Vendor_456' => [
        'vendor' => Vendor instance,
        'items' => [
            ['product_id' => 4, 'unit_price' => 75.00, 'quantity' => 1],
        ],
    ],
]
```

**Key Features**:
- Normalizes vendor keys (handles models and IDs)
- Preserves vendor object reference
- Removes empty 'default' group
- Supports multiple items per vendor

---

### `hasMultipleVendors(Payable $order): bool`

**Purpose**: Check if order has items from multiple vendors.

**Port**: `HasMultipleVendorsInCart()` from `OfficeGuyMultiVendor.php`

**Implementation**:
```php
public static function hasMultipleVendors(Payable $order): bool
{
    $groups = self::groupItemsByVendor($order);
    return count($groups) > 1;
}
```

**Use Cases**:
- Display "Multiple Vendors" notice in checkout
- Enable/disable split payment logic
- Show vendor breakdown in order summary

**Example**:
```php
if (MultiVendorPaymentService::hasMultipleVendors($order)) {
    // Show vendor breakdown
    $groups = MultiVendorPaymentService::groupItemsByVendor($order);
    foreach ($groups as $vendorKey => $data) {
        echo "Vendor: {$vendorKey} - Total: " . calculateTotal($data['items']);
    }
}
```

---

### `hasVendorItems(Payable $order): bool`

**Purpose**: Check if order has any vendor items (vs all store items).

**Port**: `HasVendorInCart()` from `OfficeGuyMultiVendor.php`

**Implementation**:
```php
public static function hasVendorItems(Payable $order): bool
{
    foreach ($order->getLineItems() as $item) {
        if (self::getVendorForItem($item)) {
            return true;
        }
    }
    return false;
}
```

**Use Cases**:
- Decide whether to use multi-vendor logic at all
- Show "Marketplace Order" badge
- Enable vendor-specific shipping options

**Example**:
```php
if (MultiVendorPaymentService::hasVendorItems($order)) {
    // Enable multi-vendor features
    $this->enableVendorNotifications();
    $this->showVendorBreakdown();
}
```

---

### `countVendors(Payable $order): int`

**Purpose**: Count the number of unique vendors (excluding store).

**Port**: `VendorsInCartCount()` from marketplace classes

**Implementation**:
```php
public static function countVendors(Payable $order): int
{
    $groups = self::groupItemsByVendor($order);
    return count($groups) - (isset($groups['default']) ? 1 : 0);
}
```

**Return Values**:
- `0` → All items from store
- `1` → Single vendor (may include store items)
- `2+` → Multiple vendors

**Use Cases**:
- Display vendor count in UI
- Calculate marketplace commission splits
- Analytics and reporting

**Example**:
```php
$vendorCount = MultiVendorPaymentService::countVendors($order);
echo "This order includes {$vendorCount} vendors";
```

---

### `getProductVendorCredentials(Payable $order): array`

**Purpose**: Get all VendorCredentials for products in the order.

**Implementation**:
```php
public static function getProductVendorCredentials(Payable $order): array
{
    $credentials = [];

    foreach ($order->getLineItems() as $item) {
        $productId = $item['product_id'] ?? $item['id'] ?? null;
        if (!$productId) {
            continue;
        }

        $credential = self::getCredentialsForItem($item);
        if ($credential) {
            $credentials[$productId] = $credential;
        }
    }

    return $credentials;
}
```

**Return Structure**:
```php
[
    123 => VendorCredential {company_id: "...", private_key: "..."},
    456 => VendorCredential {company_id: "...", private_key: "..."},
]
```

**Use Cases**:
- Pre-validate all vendor credentials exist
- Display vendor info in checkout
- Admin panel vendor credential audit

**Example**:
```php
$credentials = MultiVendorPaymentService::getProductVendorCredentials($order);
foreach ($credentials as $productId => $cred) {
    if (!$cred->is_active) {
        throw new Exception("Vendor credentials inactive for product {$productId}");
    }
}
```

---

### `processMultiVendorCharge(Payable $order, int $paymentsCount, bool $redirectMode, array $extra): array`

**Purpose**: Main entry point for processing multi-vendor payments.

**Parameters**:
- `$order` (Payable): The order to charge
- `$paymentsCount` (int): Number of installments (default: 1)
- `$redirectMode` (bool): Whether to use redirect flow (default: false)
- `$extra` (array): Additional request overrides

**Process Flow**:
```
1. Group items by vendor → groupItemsByVendor($order)
2. For each vendor group:
   a. If 'default' → chargeVendorItems($order, $items, null, ...)
   b. Else → chargeVendorItems($order, $items, $credentials, ...)
3. Collect all results
4. Determine overall success (all vendors must succeed)
5. Fire event:
   - MultiVendorPaymentCompleted (if all success)
   - MultiVendorPaymentFailed (if any failed)
6. Return aggregated results
```

**Implementation**:
```php
public static function processMultiVendorCharge(
    Payable $order,
    int $paymentsCount = 1,
    bool $redirectMode = false,
    array $extra = []
): array {
    $groups = self::groupItemsByVendor($order);
    $results = [];
    $allSuccess = true;

    foreach ($groups as $vendorKey => $groupData) {
        // For default items, use standard credentials
        if ($vendorKey === 'default') {
            $items = $groupData;
            $result = self::chargeVendorItems($order, $items, null, $paymentsCount, $redirectMode, $extra);
        } else {
            $items = $groupData['items'];
            $vendor = $groupData['vendor'];
            $credentials = self::getCredentialsForItem($items[0]);

            $result = self::chargeVendorItems($order, $items, $credentials, $paymentsCount, $redirectMode, $extra);
        }

        $results[$vendorKey] = $result;

        if (!$result['success']) {
            $allSuccess = false;
        }
    }

    // Fire events
    if ($allSuccess) {
        event(new MultiVendorPaymentCompleted($order->getPayableId(), $results));
    } else {
        event(new MultiVendorPaymentFailed($order->getPayableId(), $results));
    }

    return [
        'success' => $allSuccess,
        'vendor_results' => $results,
    ];
}
```

**Return Structure**:
```php
[
    'success' => true,  // Overall success (all vendors)
    'vendor_results' => [
        'default' => [
            'success' => true,
            'payment' => [...],
            'response' => [...],
        ],
        'App\Models\Vendor_123' => [
            'success' => true,
            'payment' => [...],
            'response' => [...],
        ],
    ],
]
```

**Critical Logic**: All vendors must succeed for overall success. If any vendor fails, the entire order is marked as failed.

---

### `chargeVendorItems(Payable $order, array $items, ?VendorCredential $credentials, int $paymentsCount, bool $redirectMode, array $extra): array` (Protected)

**Purpose**: Charge items for a specific vendor using their credentials.

**Process Flow**:
```
1. Calculate total for vendor's items
2. Build API items array
3. Get credentials (vendor or store)
4. Build SUMIT request
5. Add payment method (SingleUseToken or PCI)
6. Call SUMIT API (charge or redirect endpoint)
7. Handle response:
   - Redirect mode: Return redirect URL
   - Charge mode: Create OfficeGuyTransaction + return result
```

**Implementation Highlights**:

**1. Total Calculation**:
```php
$total = 0;
$apiItems = [];

foreach ($items as $item) {
    $unitPrice = round($item['unit_price'], 2);
    $quantity = $item['quantity'];
    $total += $unitPrice * $quantity;

    $apiItems[] = [
        'Item' => [
            'ExternalIdentifier' => $item['variation_id'] ?? $item['product_id'],
            'Name' => $item['name'],
            'SKU' => $item['sku'] ?? '',
            'SearchMode' => 'Automatic',
        ],
        'Quantity' => $quantity,
        'UnitPrice' => $unitPrice,
        'Currency' => $order->getPayableCurrency(),
    ];
}
```

**2. Credentials Selection**:
```php
$requestCredentials = $credentials
    ? $credentials->getCredentials()  // Vendor credentials
    : PaymentService::getCredentials();  // Store credentials
```

**3. Request Building**:
```php
$request = [
    'Credentials' => $requestCredentials,
    'Items' => $apiItems,
    'VATIncluded' => 'true',
    'VATRate' => PaymentService::getOrderVatRate($order),
    'Customer' => PaymentService::getOrderCustomer($order),
    'AuthoriseOnly' => config('officeguy.authorize_only', false) ? 'true' : 'false',
    'DraftDocument' => config('officeguy.draft_document', false) ? 'true' : 'false',
    'SendDocumentByEmail' => config('officeguy.email_document', true) ? 'true' : 'false',
    'DocumentDescription' => __('Order number') . ': ' . $order->getPayableId(),
    'Payments_Count' => $paymentsCount,
    'MaximumPayments' => PaymentService::getMaximumPayments($total),
    'DocumentLanguage' => PaymentService::getOrderLanguage(),
];

// Add vendor merchant number if available
if ($credentials && $credentials->merchant_number) {
    $request['MerchantNumber'] = $credentials->merchant_number;
}
```

**4. Payment Method**:
```php
if (!$redirectMode) {
    $pciMode = config('officeguy.pci', 'no');
    if ($pciMode === 'yes') {
        $request['PaymentMethod'] = TokenService::getPaymentMethodPCI();
    } else {
        $request['PaymentMethod'] = [
            'SingleUseToken' => RequestHelpers::post('og-token'),
            'Type' => 1,
        ];
    }
}
```

**5. API Call**:
```php
$environment = config('officeguy.environment', 'www');
$endpoint = $redirectMode
    ? '/billing/payments/beginredirect/'
    : '/billing/payments/charge/';

$response = OfficeGuyApi::post($request, $endpoint, $environment, !$redirectMode);
```

**6. Response Handling**:
```php
// Redirect mode
if ($redirectMode) {
    if ($response && isset($response['Data']['RedirectURL'])) {
        return [
            'success' => true,
            'redirect_url' => $response['Data']['RedirectURL'],
            'response' => $response,
        ];
    }
    return ['success' => false, 'message' => __('Something went wrong.')];
}

// Charge mode - success
$status = $response['Status'] ?? null;
$payment = $response['Data']['Payment'] ?? null;

if ($status === 0 && $payment && ($payment['ValidPayment'] ?? false) === true) {
    // Create transaction record
    OfficeGuyTransaction::create([
        'order_id' => $order->getPayableId(),
        'order_type' => get_class($order),
        'payment_id' => $payment['ID'] ?? null,
        'document_id' => $response['Data']['DocumentID'] ?? null,
        'customer_id' => $response['Data']['CustomerID'] ?? null,
        'auth_number' => $payment['AuthNumber'] ?? null,
        'amount' => $payment['Amount'] ?? $total,
        'status' => 'completed',
        'payment_method' => 'card',
        'vendor_id' => $credentials ? ($credentials->vendor_id ?? null) : null,
        'raw_request' => $request,
        'raw_response' => $response,
        // ... more fields
    ]);

    return [
        'success' => true,
        'payment' => $payment,
        'response' => $response,
    ];
}

// Failure
$message = $status !== 0
    ? ($response['UserErrorMessage'] ?? 'Gateway error')
    : ($payment['StatusDescription'] ?? 'Declined');

return [
    'success' => false,
    'message' => __('Payment failed') . ' - ' . $message,
    'response' => $response,
];
```

**Critical Fields**:
- `vendor_id`: Links transaction to vendor (important for commission tracking)
- `MerchantNumber`: Vendor-specific merchant number (if provided)
- `Credentials`: Vendor-specific SUMIT credentials (Company ID + Private Key)

---

## Multi-Vendor Flow

### Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ Customer Submits Order with Mixed Vendor Items                  │
│ - Product A ($100) from Store                                   │
│ - Product B ($150) from Vendor 123                              │
│ - Product C ($50) from Vendor 456                               │
│ Total: $300                                                     │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ processMultiVendorCharge($order)                                │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ├──> 1. Group Items by Vendor
                       │    groupItemsByVendor($order)
                       │    Returns:
                       │    {
                       │      'default': [Product A],
                       │      'Vendor_123': [Product B],
                       │      'Vendor_456': [Product C]
                       │    }
                       │
                       ├──> 2. For Each Vendor Group
                       │    │
                       │    ├─> Group: 'default' ($100)
                       │    │   ├─ Credentials: Store SUMIT
                       │    │   ├─ API Call: /billing/payments/charge/
                       │    │   └─ Result: Success
                       │    │
                       │    ├─> Group: 'Vendor_123' ($150)
                       │    │   ├─ Credentials: Vendor 123 SUMIT
                       │    │   ├─ API Call: /billing/payments/charge/
                       │    │   └─ Result: Success
                       │    │
                       │    └─> Group: 'Vendor_456' ($50)
                       │        ├─ Credentials: Vendor 456 SUMIT
                       │        ├─ API Call: /billing/payments/charge/
                       │        └─ Result: Success
                       │
                       ├──> 3. Evaluate Overall Success
                       │    if (all vendor charges succeeded) {
                       │        $allSuccess = true;
                       │    } else {
                       │        $allSuccess = false;
                       │    }
                       │
                       └──> 4. Fire Event
                            if ($allSuccess) {
                                event(new MultiVendorPaymentCompleted($order, $results));
                            } else {
                                event(new MultiVendorPaymentFailed($order, $results));
                            }
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ Return Aggregated Results                                       │
│ {                                                               │
│   success: true,                                                │
│   vendor_results: {                                             │
│     'default': {success: true, payment: {...}},                 │
│     'Vendor_123': {success: true, payment: {...}},              │
│     'Vendor_456': {success: true, payment: {...}}               │
│   }                                                             │
│ }                                                               │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│ Database Records Created (3 OfficeGuyTransaction records)       │
│                                                                 │
│ Transaction 1:                                                  │
│   order_id: 789                                                 │
│   amount: $100                                                  │
│   vendor_id: NULL (store)                                       │
│   status: completed                                             │
│                                                                 │
│ Transaction 2:                                                  │
│   order_id: 789                                                 │
│   amount: $150                                                  │
│   vendor_id: 123                                                │
│   status: completed                                             │
│                                                                 │
│ Transaction 3:                                                  │
│   order_id: 789                                                 │
│   amount: $50                                                   │
│   vendor_id: 456                                                │
│   status: completed                                             │
└─────────────────────────────────────────────────────────────────┘
```

### Key Observations

1. **Independent Charges**: Each vendor is charged separately via SUMIT API
2. **Separate Transactions**: Each charge creates its own `OfficeGuyTransaction` record
3. **All-or-Nothing Success**: If ANY vendor fails, overall result is failure
4. **Vendor Tracking**: `vendor_id` field links transaction to vendor
5. **Same Order ID**: All transactions share the same `order_id`

---

## Vendor Resolution Strategy

### Resolution Mechanisms

The service supports **3 vendor resolution mechanisms** with the following priority:

```
Priority 1: Custom Resolver (setVendorResolver)
    ↓ (if not set)
Priority 2: $item['vendor_id'] field
    ↓ (if not set)
Priority 3: $item['vendor'] field
    ↓ (if not set)
Result: null (use store credentials)
```

### Mechanism 1: Custom Resolver (Recommended)

**Best for**: Integrating with marketplace plugins (Dokan, WCFM, WC Vendors)

**Setup**:
```php
// In AppServiceProvider::boot()
use OfficeGuy\LaravelSumitGateway\Services\MultiVendorPaymentService;

MultiVendorPaymentService::setVendorResolver(function (array $item) {
    // Dokan
    if (function_exists('dokan')) {
        $product = wc_get_product($item['product_id']);
        return dokan()->vendor->get($product->get_id());
    }

    // Custom logic
    return Product::find($item['product_id'])?->vendor;
});
```

**Advantages**:
- ✅ Zero-code integration with existing marketplace plugins
- ✅ Centralized vendor resolution logic
- ✅ Supports complex resolution rules
- ✅ Can query database, call APIs, etc.

### Mechanism 2: Item Field (`vendor_id`)

**Best for**: Simple setups where vendor ID is known at order creation

**Setup**:
```php
// When creating order line items
$items = [
    [
        'product_id' => 123,
        'vendor_id' => 456,  // ← Set vendor_id field
        'unit_price' => 50.00,
        'quantity' => 2,
    ],
];
```

**Advantages**:
- ✅ Simple and explicit
- ✅ No callback needed
- ❌ Requires modifying order creation code

### Mechanism 3: Item Field (`vendor`)

**Best for**: When vendor model is already loaded

**Setup**:
```php
$vendor = Vendor::find(456);

$items = [
    [
        'product_id' => 123,
        'vendor' => $vendor,  // ← Set vendor object
        'unit_price' => 50.00,
        'quantity' => 2,
    ],
];
```

**Advantages**:
- ✅ Avoids N+1 queries (vendor already loaded)
- ✅ Can pass full vendor model
- ❌ More memory usage

---

## Integration Examples

### Example 1: Dokan Marketplace Integration

```php
// In AppServiceProvider::boot()
use OfficeGuy\LaravelSumitGateway\Services\MultiVendorPaymentService;

public function boot(): void
{
    // Set vendor resolver for Dokan
    MultiVendorPaymentService::setVendorResolver(function (array $item) {
        if (!function_exists('dokan')) {
            return null;
        }

        $product = wc_get_product($item['product_id']);
        if (!$product) {
            return null;
        }

        $vendorId = get_post_field('post_author', $product->get_id());
        return App\Models\Vendor::where('user_id', $vendorId)->first();
    });
}
```

### Example 2: Processing Multi-Vendor Order

```php
use OfficeGuy\LaravelSumitGateway\Services\MultiVendorPaymentService;
use App\Models\Order;

class CheckoutController extends Controller
{
    public function processPayment(Request $request, Order $order)
    {
        // Check if multi-vendor order
        if (!MultiVendorPaymentService::hasVendorItems($order)) {
            // Use standard PaymentService
            return $this->processStandardPayment($order);
        }

        // Process multi-vendor payment
        $result = MultiVendorPaymentService::processMultiVendorCharge(
            order: $order,
            paymentsCount: $request->input('installments', 1),
            redirectMode: false,
            extra: []
        );

        if ($result['success']) {
            return redirect()->route('order.success', ['order' => $order]);
        }

        // Handle failure
        return back()->withErrors([
            'payment' => 'Multi-vendor payment failed. Please try again.',
        ]);
    }
}
```

### Example 3: Displaying Vendor Breakdown

```blade
<!-- resources/views/checkout/summary.blade.php -->
@php
    use OfficeGuy\LaravelSumitGateway\Services\MultiVendorPaymentService;

    $groups = MultiVendorPaymentService::groupItemsByVendor($order);
@endphp

@if(count($groups) > 1)
    <div class="multi-vendor-notice">
        <h3>{{ __('This order includes items from multiple vendors') }}</h3>

        @foreach($groups as $vendorKey => $data)
            <div class="vendor-group">
                @if($vendorKey === 'default')
                    <h4>{{ __('Store Items') }}</h4>
                @else
                    <h4>{{ __('Vendor') }}: {{ $data['vendor']->name }}</h4>
                @endif

                <ul>
                    @foreach($data['items'] ?? $data as $item)
                        <li>{{ $item['name'] }} ({{ $item['quantity'] }}x)</li>
                    @endforeach
                </ul>

                <p class="vendor-total">
                    {{ __('Subtotal') }}: {{ number_format(array_sum(array_column($data['items'] ?? $data, 'unit_price')), 2) }}
                </p>
            </div>
        @endforeach
    </div>
@endif
```

### Example 4: Event Listener (Commission Tracking)

```php
namespace App\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\MultiVendorPaymentCompleted;
use App\Models\VendorCommission;

class TrackVendorCommissions
{
    public function handle(MultiVendorPaymentCompleted $event): void
    {
        $orderId = $event->orderId;
        $results = $event->results;

        foreach ($results as $vendorKey => $result) {
            if ($vendorKey === 'default' || !$result['success']) {
                continue;
            }

            // Extract vendor ID from key
            preg_match('/_(\d+)$/', $vendorKey, $matches);
            $vendorId = $matches[1] ?? null;

            if (!$vendorId) {
                continue;
            }

            // Calculate commission (e.g., 10%)
            $amount = $result['payment']['Amount'] ?? 0;
            $commission = $amount * 0.10;

            // Record commission
            VendorCommission::create([
                'order_id' => $orderId,
                'vendor_id' => $vendorId,
                'amount' => $amount,
                'commission' => $commission,
                'status' => 'pending',
            ]);
        }
    }
}

// In EventServiceProvider::$listen
protected $listen = [
    MultiVendorPaymentCompleted::class => [
        TrackVendorCommissions::class,
    ],
];
```

---

## Inconsistencies

### 1. Static vs Instance Pattern ⚠️

**Issue**: This service is **static**, but other services use **instance-based DI**.

**Comparison**:
```php
// MultiVendorPaymentService (STATIC)
public static function processMultiVendorCharge(...) { }

// WebhookService (INSTANCE)
public function __construct(protected SettingsService $settings) {}
public function dispatch(...) { }

// CustomerMergeService (INSTANCE)
public function __construct(protected SettingsService $settings) {}
public function sync(...) { }

// FulfillmentDispatcher (INSTANCE)
public function __construct() {}
public function dispatch(...) { }
```

**Impact**:
- ❌ Inconsistent service architecture
- ❌ Harder to test (static methods)
- ❌ No dependency injection
- ❌ Cannot mock in tests

**Recommendation**: Refactor to instance-based service:
```php
class MultiVendorPaymentService
{
    protected $vendorResolver = null;  // Instance property

    public function setVendorResolver(callable $resolver): void
    {
        $this->vendorResolver = $resolver;
    }

    public function processMultiVendorCharge(...): array
    {
        // Non-static implementation
    }
}

// Usage
$service = app(MultiVendorPaymentService::class);
$result = $service->processMultiVendorCharge($order);
```

### 2. Mixed Responsibility in chargeVendorItems()

**Issue**: The `chargeVendorItems()` method duplicates logic from `PaymentService::charge()`.

**Duplicated Code**:
- API request building
- Payment method handling
- Response parsing
- Transaction creation

**Impact**:
- ❌ Code duplication (~150 lines)
- ❌ Maintenance burden (changes needed in 2 places)
- ❌ Inconsistent behavior risk

**Recommendation**: Extract shared logic to PaymentService:
```php
// In PaymentService
public static function chargeItems(
    array $items,
    Payable $order,
    ?array $credentials = null,
    array $options = []
): array {
    // Shared charging logic
}

// In MultiVendorPaymentService
protected static function chargeVendorItems(...): array
{
    return PaymentService::chargeItems($items, $order, $credentials, $options);
}
```

### 3. No Validation for Inactive Credentials

**Issue**: The service doesn't validate that vendor credentials are active before charging.

**Current Flow**:
```php
$credentials = self::getCredentialsForItem($item);
// No check for $credentials->is_active!
$result = self::chargeVendorItems($order, $items, $credentials, ...);
```

**Impact**:
- ❌ May attempt charge with inactive credentials
- ❌ Poor error messages for merchants
- ❌ Wasted API calls

**Recommendation**: Add validation:
```php
$credentials = self::getCredentialsForItem($item);

if ($credentials && !$credentials->is_active) {
    return [
        'success' => false,
        'message' => __('Vendor credentials are inactive for this product'),
        'vendor_id' => $credentials->vendor_id,
    ];
}
```

### 4. Missing Transaction Rollback

**Issue**: If Vendor A succeeds but Vendor B fails, Vendor A's charge remains (no rollback).

**Current Flow**:
```
Vendor A charge → Success ($100 charged)
Vendor B charge → Failure
Result: $allSuccess = false
Problem: Vendor A's $100 is still charged!
```

**Impact**:
- ❌ Customer charged incorrectly
- ❌ Partial payment state
- ❌ Manual refund required

**Recommendation**: Implement 2-phase commit or refund-on-failure:
```php
$successfulCharges = [];

foreach ($groups as $vendorKey => $groupData) {
    $result = self::chargeVendorItems(...);

    if (!$result['success']) {
        // Refund all successful charges
        foreach ($successfulCharges as $charge) {
            PaymentService::refund($charge['payment_id'], $charge['amount']);
        }

        return [
            'success' => false,
            'message' => __('Multi-vendor payment failed. All charges refunded.'),
        ];
    }

    $successfulCharges[] = $result;
}
```

---

## Best Practices

### 1. Always Set Vendor Resolver in AppServiceProvider

❌ **WRONG**:
```php
// Setting resolver in controller (too late!)
class CheckoutController {
    public function __construct() {
        MultiVendorPaymentService::setVendorResolver(...);
    }
}
```

✅ **CORRECT**:
```php
// In AppServiceProvider::boot()
public function boot(): void
{
    MultiVendorPaymentService::setVendorResolver(function (array $item) {
        return Product::find($item['product_id'])?->vendor;
    });
}
```

**Why**: Resolver must be set before any order processing occurs.

---

### 2. Validate Credentials Before Charging

❌ **WRONG**:
```php
$result = MultiVendorPaymentService::processMultiVendorCharge($order);
// Hope for the best!
```

✅ **CORRECT**:
```php
// Pre-validate all vendor credentials exist and are active
$credentials = MultiVendorPaymentService::getProductVendorCredentials($order);

foreach ($credentials as $productId => $cred) {
    if (!$cred->is_active) {
        return back()->withErrors([
            'payment' => "Vendor credentials inactive for product #{$productId}",
        ]);
    }
}

// Now process payment
$result = MultiVendorPaymentService::processMultiVendorCharge($order);
```

---

### 3. Handle Partial Failures Gracefully

❌ **WRONG**:
```php
$result = MultiVendorPaymentService::processMultiVendorCharge($order);

if (!$result['success']) {
    return back()->withErrors(['payment' => 'Payment failed']);
}
```

✅ **CORRECT**:
```php
$result = MultiVendorPaymentService::processMultiVendorCharge($order);

if (!$result['success']) {
    // Show which vendors failed
    $failures = [];
    foreach ($result['vendor_results'] as $vendorKey => $vendorResult) {
        if (!$vendorResult['success']) {
            $failures[] = $vendorKey;
        }
    }

    return back()->withErrors([
        'payment' => "Payment failed for vendors: " . implode(', ', $failures),
    ]);
}
```

---

### 4. Display Vendor Breakdown to Customer

✅ **Always show vendor breakdown in multi-vendor orders**:
```php
@if(MultiVendorPaymentService::hasMultipleVendors($order))
    <div class="vendor-breakdown">
        <p>This order will be processed as {{ MultiVendorPaymentService::countVendors($order) }} separate payments.</p>
        <!-- Show vendor groups -->
    </div>
@endif
```

**Why**: Transparency improves customer trust and reduces support requests.

---

## Summary

### Key Takeaways

1. **Zero-Code Integration**: Vendor resolver callback enables integration with any marketplace plugin
2. **Separate Transactions**: Each vendor is charged independently with their own SUMIT credentials
3. **All-or-Nothing Success**: All vendors must succeed for overall success
4. **Event-Driven**: MultiVendorPaymentCompleted/Failed events for custom logic
5. **Vendor Tracking**: `vendor_id` field links transactions to vendors for commission tracking

---

### When to Use This Service

✅ **Use MultiVendorPaymentService when**:
- Building a marketplace with multiple vendors
- Each vendor has their own SUMIT account
- Need to split payments by vendor
- Need separate invoices/receipts per vendor
- Tracking vendor commissions

❌ **Don't use MultiVendorPaymentService for**:
- Single-vendor stores (use PaymentService)
- Split payments to same vendor (use installments)
- Partial payments (use PaymentService with authorize-only)

---

### Critical Issues Requiring Attention

🔴 **CRITICAL**:
1. **No Rollback on Partial Failure**: Successful charges remain if later vendors fail
2. **Static Pattern Inconsistency**: Should be instance-based like other services

🟡 **IMPORTANT**:
3. **Code Duplication**: `chargeVendorItems()` duplicates PaymentService logic
4. **No Credential Validation**: Doesn't check `is_active` before charging

🟢 **MINOR**:
5. **Missing PHPDoc**: Some methods lack full documentation
6. **No Rate Limiting**: Multiple API calls without throttling

---

**Document Version**: 1.0.0
**Last Updated**: 2025-01-13
**Maintainer**: NM-DigitalHub
**Package**: officeguy/laravel-sumit-gateway v1.1.6+
