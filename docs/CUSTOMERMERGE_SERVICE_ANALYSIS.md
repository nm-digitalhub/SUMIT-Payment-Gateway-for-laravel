# CustomerMergeService Analysis

**File**: `src/Services/CustomerMergeService.php`
**Lines**: 347
**Type**: Instance Service Class (uses dependency injection)
**Purpose**: Bidirectional synchronization between SUMIT customers and local customer models

---

## Overview

CustomerMergeService provides **zero-code integration** between the SUMIT package and existing customer/user models in Laravel applications. Instead of requiring developers to modify their models, it uses **configurable field mapping** to synchronize customer data bidirectionally.

### Key Value Proposition

**Problem**: Every Laravel application has different customer/user models with different field names:
```php
App A: User model with 'email', 'name', 'phone'
App B: Customer model with 'email_address', 'full_name', 'mobile'
App C: Account model with 'contact_email', 'account_name', 'telephone'
```

**Solution**: Configure field mapping once in Admin Settings, no code changes needed:
```php
// Admin Settings Page → Customer Sync
customer_model = 'App\Models\User'
customer_field_email = 'email'
customer_field_name = 'name'
customer_field_phone = 'phone'
customer_field_sumit_id = 'sumit_customer_id'
```

### Key Responsibilities

1. **Sync from SUMIT**: Create/update local customers from SUMIT webhook data
2. **Sync to SUMIT**: Push local customer data to SUMIT API (partially implemented)
3. **Field Mapping**: Translate between SUMIT field names and local field names
4. **Deduplication**: Find existing customers by SUMIT ID or email
5. **Graceful Degradation**: Return null if sync disabled or fails (non-blocking)

---

## Class Structure

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Database\Eloquent\Model;

class CustomerMergeService
{
    protected SettingsService $settings;  // ← Instance property!

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    // Configuration
    public function isEnabled(): bool
    public function getModelClass(): ?string
    public function getFieldMapping(): array

    // Sync Operations
    public function syncFromSumit(array $sumitCustomer): ?Model
    public function syncToSumit(Model $customer): ?array

    // Find Operations
    public function findBySumitId($sumitId): ?Model
    public function findByEmail(string $email): ?Model

    // Internal Helpers (protected)
    protected function findLocalCustomer(...)
    protected function updateCustomer(...)
    protected function createCustomer(...)
    protected function mapSumitToLocal(...)
    protected function mapLocalToSumit(...)
}
```

**⚠️ Important**: Like WebhookService, this is an **instance service** (NOT static) using dependency injection.

---

## Methods Analysis

### 1. `isEnabled()` - Check if Sync Enabled

**Lines**: 28-31
**Signature**:
```php
public function isEnabled(): bool
```

**Purpose**: Check if customer synchronization is enabled in settings

**Implementation**:
```php
public function isEnabled(): bool
{
    return (bool) $this->settings->get('customer_sync_enabled', false);
}
```

**Configuration**:
```php
// Admin Settings Page → Customer Sync
customer_sync_enabled = true/false
```

**Usage Pattern**:
```php
if (!$this->isEnabled()) {
    return null;  // Graceful degradation
}
```

---

### 2. `getModelClass()` - Get Configured Model

**Lines**: 36-39
**Signature**:
```php
public function getModelClass(): ?string
```

**Purpose**: Get the fully-qualified class name of the local customer model

**Implementation**:
```php
public function getModelClass(): ?string
{
    return $this->settings->get('customer_model');
}
```

**Configuration Examples**:
```php
// Common configurations:
customer_model = 'App\Models\User'          // Laravel default
customer_model = 'App\Models\Customer'      // E-commerce apps
customer_model = 'App\Models\Account'       // B2B apps
customer_model = 'App\Models\Client'        // Agency/CRM apps
```

---

### 3. `getFieldMapping()` - Get Field Mapping Configuration

**Lines**: 44-57
**Signature**:
```php
public function getFieldMapping(): array
```

**Purpose**: Get mapping between SUMIT field names and local model field names

**Implementation**:
```php
public function getFieldMapping(): array
{
    return [
        'email' => $this->settings->get('customer_field_email', 'email'),
        'name' => $this->settings->get('customer_field_name', 'name'),
        'phone' => $this->settings->get('customer_field_phone', 'phone'),
        'first_name' => $this->settings->get('customer_field_first_name'),
        'last_name' => $this->settings->get('customer_field_last_name'),
        'company' => $this->settings->get('customer_field_company'),
        'address' => $this->settings->get('customer_field_address'),
        'city' => $this->settings->get('customer_field_city'),
        'sumit_id' => $this->settings->get('customer_field_sumit_id', 'sumit_customer_id'),
    ];
}
```

**Return Value Example**:
```php
[
    'email' => 'email',                    // Local field name
    'name' => 'full_name',                 // Local field name
    'phone' => 'mobile',                   // Local field name
    'first_name' => 'first_name',         // Local field name
    'last_name' => 'last_name',           // Local field name
    'company' => 'company_name',          // Local field name
    'address' => 'billing_address',       // Local field name
    'city' => 'billing_city',             // Local field name
    'sumit_id' => 'sumit_customer_id',    // Local field name (stores SUMIT ID)
]
```

**Configuration in Admin Settings**:
```
Customer Sync Tab:
├─ Email Field: [email]
├─ Name Field: [name]
├─ Phone Field: [phone]
├─ First Name Field: [first_name]
├─ Last Name Field: [last_name]
├─ Company Field: [company_name]
├─ Address Field: [billing_address]
├─ City Field: [billing_city]
└─ SUMIT ID Field: [sumit_customer_id]
```

---

### 4. `syncFromSumit()` - Sync Customer from SUMIT

**Lines**: 65-108
**Signature**:
```php
public function syncFromSumit(array $sumitCustomer): ?Model
```

**Purpose**: Find or create local customer from SUMIT customer data (webhooks, API responses)

**Parameters**:
- `$sumitCustomer` - SUMIT customer data array

**Process Flow**:
```
1. Check if sync enabled
   └─ If disabled → return null (graceful degradation)

2. Validate model class configured
   └─ If missing or invalid → return null

3. Extract email and SUMIT ID from data
   ├─ Email: $sumitCustomer['Email'] or ['email']
   └─ ID: $sumitCustomer['ID'] or ['CustomerID']

4. Find existing local customer
   ├─ First: Try by SUMIT ID (most reliable)
   └─ Then: Try by email (fallback)

5. If customer found:
   └─ Update existing customer with new SUMIT data

6. If customer NOT found:
   └─ Create new local customer

7. Return customer model
```

**Implementation**:
```php
public function syncFromSumit(array $sumitCustomer): ?Model
{
    // 1. Check if enabled
    if (!$this->isEnabled()) {
        return null;
    }

    // 2. Validate model class
    $modelClass = $this->getModelClass();
    if (!$modelClass || !class_exists($modelClass)) {
        Log::warning('CustomerMergeService: Invalid or missing customer model class', [
            'configured_class' => $modelClass,
        ]);
        return null;
    }

    // 3. Extract identifiers
    $fieldMap = $this->getFieldMapping();
    $email = $sumitCustomer['Email'] ?? $sumitCustomer['email'] ?? null;
    $sumitId = $sumitCustomer['ID'] ?? $sumitCustomer['id'] ?? $sumitCustomer['CustomerID'] ?? null;

    if (!$email && !$sumitId) {
        Log::warning('CustomerMergeService: No email or SUMIT ID in customer data');
        return null;
    }

    try {
        // 4. Try to find existing customer
        $customer = $this->findLocalCustomer($modelClass, $fieldMap, $email, $sumitId);

        if ($customer) {
            // 5. Update existing customer
            $customer = $this->updateCustomer($customer, $sumitCustomer, $fieldMap);
        } else {
            // 6. Create new customer
            $customer = $this->createCustomer($modelClass, $sumitCustomer, $fieldMap);
        }

        return $customer;
    } catch (\Exception $e) {
        Log::error('CustomerMergeService: Failed to sync customer', [
            'error' => $e->getMessage(),
            'sumit_customer' => $sumitCustomer,
        ]);
        return null;  // Graceful degradation
    }
}
```

**SUMIT Customer Data Example**:
```php
[
    'ID' => 123456,                    // SUMIT customer ID
    'Email' => 'yossi@example.com',
    'FirstName' => 'יוסי',
    'LastName' => 'כהן',
    'Phone' => '0501234567',
    'CompanyName' => 'Example Ltd',
    'Address' => 'רחוב הרצל 1',
    'City' => 'תל אביב',
]
```

**Return Value**:
```php
// Success: Local customer model
User {
    id: 789,
    email: 'yossi@example.com',
    name: 'יוסי כהן',
    phone: '0501234567',
    sumit_customer_id: 123456,  // ← SUMIT ID stored locally
}

// Failure: null (sync disabled, invalid config, or error)
null
```

**Triggered By**:
- SUMIT webhooks (payment completed, document created)
- Payment processing (customer data from SUMIT response)
- Manual sync commands

---

### 5. `findLocalCustomer()` - Find Existing Customer

**Lines**: 113-131
**Signature**:
```php
protected function findLocalCustomer(string $modelClass, array $fieldMap, ?string $email, $sumitId): ?Model
```

**Purpose**: Find local customer by SUMIT ID (priority) or email (fallback)

**Search Priority**:
```
1️⃣ SUMIT ID (HIGHEST PRIORITY)
   └─ Most reliable identifier
   └─ Never changes

2️⃣ Email (FALLBACK)
   └─ Less reliable (can change)
   └─ Used if SUMIT ID not found
```

**Implementation**:
```php
protected function findLocalCustomer(string $modelClass, array $fieldMap, ?string $email, $sumitId): ?Model
{
    $query = $modelClass::query();

    // First try to find by SUMIT ID (most reliable)
    if ($sumitId && !empty($fieldMap['sumit_id'])) {
        $customer = $query->where($fieldMap['sumit_id'], $sumitId)->first();
        if ($customer) {
            return $customer;  // ← Found by SUMIT ID (best case)
        }
    }

    // Then try to find by email
    if ($email && !empty($fieldMap['email'])) {
        return $modelClass::where($fieldMap['email'], $email)->first();
    }

    return null;  // Customer not found
}
```

**Search Examples**:
```php
// Example 1: Found by SUMIT ID
User::where('sumit_customer_id', 123456)->first()
// Returns: User {id: 789, sumit_customer_id: 123456}

// Example 2: SUMIT ID not found, try email
User::where('sumit_customer_id', 999999)->first()  // null
User::where('email', 'yossi@example.com')->first()  // Found!
// Returns: User {id: 790, email: 'yossi@example.com'}

// Example 3: Customer doesn't exist
// Returns: null → Will create new customer
```

---

### 6. `updateCustomer()` - Update Existing Customer

**Lines**: 136-150
**Signature**:
```php
protected function updateCustomer(Model $customer, array $sumitData, array $fieldMap): Model
```

**Purpose**: Update existing local customer with new SUMIT data

**Process Flow**:
```
1. Map SUMIT data to local field names
   └─ Call mapSumitToLocal()

2. Preserve email if it's the unique identifier
   └─ Don't overwrite email (prevents identifier change)

3. Update customer with mapped data
   └─ Only if updates exist

4. Return updated customer
```

**Implementation**:
```php
protected function updateCustomer(Model $customer, array $sumitData, array $fieldMap): Model
{
    // 1. Map SUMIT data to local fields
    $updates = $this->mapSumitToLocal($sumitData, $fieldMap);

    // 2. Don't update email if it's the unique identifier
    if (!empty($fieldMap['email']) && $customer->getAttribute($fieldMap['email'])) {
        unset($updates[$fieldMap['email']]);  // ← Preserve email
    }

    // 3. Update if there are changes
    if (!empty($updates)) {
        $customer->update($updates);
    }

    return $customer;
}
```

**Why Preserve Email?**
```
Problem: If email is used as unique identifier (login), changing it could:
├─ Break user authentication
├─ Duplicate customers (old email + new email)
└─ Violate unique constraints

Solution: Only update email when creating new customer, never when updating
```

**Update Example**:
```php
// Before update:
User {
    id: 789,
    email: 'yossi@example.com',   // ← Will NOT be updated
    name: 'Yossi',
    phone: '0501111111',
    sumit_customer_id: 123456,
}

// SUMIT data:
[
    'Email' => 'yossi@example.com',
    'FirstName' => 'יוסי',
    'Phone' => '0502222222',  // ← Changed
]

// After update:
User {
    id: 789,
    email: 'yossi@example.com',   // ← Unchanged (preserved)
    name: 'יוסי',                 // ← Updated
    phone: '0502222222',           // ← Updated
    sumit_customer_id: 123456,
}
```

---

### 7. `createCustomer()` - Create New Customer

**Lines**: 155-160
**Signature**:
```php
protected function createCustomer(string $modelClass, array $sumitData, array $fieldMap): Model
```

**Purpose**: Create new local customer from SUMIT data

**Implementation**:
```php
protected function createCustomer(string $modelClass, array $sumitData, array $fieldMap): Model
{
    $data = $this->mapSumitToLocal($sumitData, $fieldMap);

    return $modelClass::create($data);
}
```

**Creation Example**:
```php
// SUMIT data:
[
    'ID' => 123456,
    'Email' => 'yossi@example.com',
    'FirstName' => 'יוסי',
    'LastName' => 'כהן',
    'Phone' => '0501234567',
]

// Created customer:
User::create([
    'email' => 'yossi@example.com',
    'name' => 'יוסי כהן',
    'phone' => '0501234567',
    'sumit_customer_id' => 123456,
]);
```

**Mass Assignment Protection**:
```php
// In User model:
protected $fillable = [
    'email',
    'name',
    'phone',
    'sumit_customer_id',  // ← Must be in fillable!
];
```

---

### 8. `mapSumitToLocal()` - Map SUMIT Data to Local Fields

**Lines**: 165-233
**Signature**:
```php
protected function mapSumitToLocal(array $sumitData, array $fieldMap): array
```

**Purpose**: Translate SUMIT field names to local model field names

**Mapping Logic**:
```
SUMIT Field Name → Local Field Name (configured)

Email → email (or email_address, contact_email, etc.)
FirstName → first_name (or fname, given_name, etc.)
LastName → last_name (or lname, family_name, etc.)
Phone → phone (or mobile, telephone, contact_phone, etc.)
CompanyName → company (or company_name, organization, etc.)
Address → address (or billing_address, street_address, etc.)
City → city (or billing_city, town, etc.)
ID → sumit_customer_id (or sumit_id, external_id, etc.)
```

**Implementation Highlights**:
```php
protected function mapSumitToLocal(array $sumitData, array $fieldMap): array
{
    $mapped = [];

    // Map email
    if (!empty($fieldMap['email'])) {
        $email = $sumitData['Email'] ?? $sumitData['email'] ?? null;
        if ($email) {
            $mapped[$fieldMap['email']] = $email;
        }
    }

    // Map phone (try multiple SUMIT fields)
    if (!empty($fieldMap['phone'])) {
        $phone = $sumitData['Phone'] ?? $sumitData['phone'] ?? $sumitData['Mobile'] ?? null;
        if ($phone) {
            $mapped[$fieldMap['phone']] = $phone;
        }
    }

    // Map name (combined or separate)
    $firstName = $sumitData['FirstName'] ?? $sumitData['first_name'] ?? '';
    $lastName = $sumitData['LastName'] ?? $sumitData['last_name'] ?? '';
    $fullName = $sumitData['Name'] ?? $sumitData['name'] ?? trim("$firstName $lastName");

    if (!empty($fieldMap['name']) && $fullName) {
        $mapped[$fieldMap['name']] = $fullName;
    }
    if (!empty($fieldMap['first_name']) && $firstName) {
        $mapped[$fieldMap['first_name']] = $firstName;
    }
    if (!empty($fieldMap['last_name']) && $lastName) {
        $mapped[$fieldMap['last_name']] = $lastName;
    }

    // Map SUMIT ID
    if (!empty($fieldMap['sumit_id'])) {
        $sumitId = $sumitData['ID'] ?? $sumitData['id'] ?? $sumitData['CustomerID'] ?? null;
        if ($sumitId) {
            $mapped[$fieldMap['sumit_id']] = $sumitId;
        }
    }

    // ... (company, address, city - similar pattern)

    return $mapped;
}
```

**Mapping Example**:
```php
// SUMIT data:
[
    'ID' => 123456,
    'Email' => 'yossi@example.com',
    'FirstName' => 'יוסי',
    'LastName' => 'כהן',
    'Phone' => '0501234567',
]

// Field mapping config:
[
    'email' => 'email',
    'name' => 'full_name',
    'first_name' => 'first_name',
    'last_name' => 'last_name',
    'phone' => 'mobile',
    'sumit_id' => 'sumit_customer_id',
]

// Mapped result:
[
    'email' => 'yossi@example.com',
    'full_name' => 'יוסי כהן',
    'first_name' => 'יוסי',
    'last_name' => 'כהן',
    'mobile' => '0501234567',
    'sumit_customer_id' => 123456,
]
```

---

### 9. `syncToSumit()` - Sync Customer to SUMIT

**Lines**: 241-253
**Signature**:
```php
public function syncToSumit(Model $customer): ?array
```

**Purpose**: Push local customer data to SUMIT API (partially implemented)

**Current Implementation**:
```php
public function syncToSumit(Model $customer): ?array
{
    if (!$this->isEnabled()) {
        return null;
    }

    $fieldMap = $this->getFieldMapping();
    $data = $this->mapLocalToSumit($customer, $fieldMap);

    // This would need to use the SUMIT API to create/update customer
    // For now, return the mapped data for the developer to use
    return $data;  // ← Returns mapped data, doesn't call SUMIT API yet
}
```

**Status**: **Partially Implemented**
- ✅ Maps local data to SUMIT format
- ❌ Does NOT call SUMIT API (developer must do this manually)
- ⚠️ Intended for future enhancement

**Usage**:
```php
$customerMergeService = app(CustomerMergeService::class);
$sumitData = $customerMergeService->syncToSumit($user);

// Then manually call SUMIT API:
$response = OfficeGuyApi::post([
    'Credentials' => PaymentService::getCredentials(),
    'Customer' => $sumitData,
], '/customer/create/');
```

---

### 10. `mapLocalToSumit()` - Map Local Data to SUMIT Format

**Lines**: 258-302
**Signature**:
```php
protected function mapLocalToSumit(Model $customer, array $fieldMap): array
```

**Purpose**: Translate local model fields to SUMIT field names

**Implementation**:
```php
protected function mapLocalToSumit(Model $customer, array $fieldMap): array
{
    $data = [];

    if (!empty($fieldMap['email'])) {
        $data['Email'] = $customer->getAttribute($fieldMap['email']);
    }

    if (!empty($fieldMap['phone'])) {
        $data['Phone'] = $customer->getAttribute($fieldMap['phone']);
    }

    if (!empty($fieldMap['name'])) {
        $data['Name'] = $customer->getAttribute($fieldMap['name']);
    }

    if (!empty($fieldMap['first_name'])) {
        $data['FirstName'] = $customer->getAttribute($fieldMap['first_name']);
    }

    if (!empty($fieldMap['last_name'])) {
        $data['LastName'] = $customer->getAttribute($fieldMap['last_name']);
    }

    if (!empty($fieldMap['company'])) {
        $data['CompanyName'] = $customer->getAttribute($fieldMap['company']);
    }

    if (!empty($fieldMap['address'])) {
        $data['Address'] = $customer->getAttribute($fieldMap['address']);
    }

    if (!empty($fieldMap['city'])) {
        $data['City'] = $customer->getAttribute($fieldMap['city']);
    }

    if (!empty($fieldMap['sumit_id'])) {
        $sumitId = $customer->getAttribute($fieldMap['sumit_id']);
        if ($sumitId) {
            $data['ID'] = $sumitId;
        }
    }

    return $data;
}
```

**Mapping Example**:
```php
// Local customer:
User {
    email: 'yossi@example.com',
    full_name: 'יוסי כהן',
    first_name: 'יוסי',
    last_name: 'כהן',
    mobile: '0501234567',
    sumit_customer_id: 123456,
}

// Mapped to SUMIT format:
[
    'Email' => 'yossi@example.com',
    'Name' => 'יוסי כהן',
    'FirstName' => 'יוסי',
    'LastName' => 'כהן',
    'Phone' => '0501234567',
    'ID' => 123456,
]
```

---

### 11. `findBySumitId()` - Find by SUMIT ID

**Lines**: 307-324
**Signature**:
```php
public function findBySumitId($sumitId): ?Model
```

**Purpose**: Find local customer by SUMIT customer ID

**Usage**:
```php
$customerMergeService = app(CustomerMergeService::class);
$customer = $customerMergeService->findBySumitId(123456);

if ($customer) {
    echo "Found customer: {$customer->email}";
}
```

---

### 12. `findByEmail()` - Find by Email

**Lines**: 329-346
**Signature**:
```php
public function findByEmail(string $email): ?Model
```

**Purpose**: Find local customer by email address

**Usage**:
```php
$customerMergeService = app(CustomerMergeService::class);
$customer = $customerMergeService->findByEmail('yossi@example.com');

if ($customer) {
    echo "Found customer: {$customer->name}";
}
```

---

## Configuration Setup

### Admin Settings Page

**Location**: `/admin/office-guy-settings` → "Customer Sync" tab

**Required Settings**:
```
Enable Customer Sync: [✓]
Customer Model: [App\Models\User]

Field Mapping:
├─ Email Field: [email]
├─ Name Field: [name]
├─ Phone Field: [phone]
├─ First Name Field: [first_name]
├─ Last Name Field: [last_name]
├─ Company Field: [company_name]
├─ Address Field: [billing_address]
├─ City Field: [billing_city]
└─ SUMIT ID Field: [sumit_customer_id]
```

### Migration Required

**Add sumit_customer_id to Customer Model**:
```php
// database/migrations/xxxx_add_sumit_customer_id_to_users.php
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('sumit_customer_id')->nullable()->unique()->after('id');
    $table->index('sumit_customer_id');
});
```

### Model Mass Assignment

```php
// app/Models/User.php
protected $fillable = [
    'name',
    'email',
    'phone',
    'sumit_customer_id',  // ← Add this!
];
```

---

## Dependencies

### Service Dependencies

```
CustomerMergeService
└─ SettingsService (injected)
   ├─ get('customer_sync_enabled')
   ├─ get('customer_model')
   ├─ get('customer_field_email')
   ├─ get('customer_field_name')
   └─ ... (all field mappings)
```

### Configuration Dependencies

```php
config('officeguy.customer_sync_enabled')    // true/false
config('officeguy.customer_model')           // 'App\Models\User'
config('officeguy.customer_field_email')     // 'email'
config('officeguy.customer_field_name')      // 'name'
config('officeguy.customer_field_phone')     // 'phone'
config('officeguy.customer_field_sumit_id')  // 'sumit_customer_id'
// ... etc for all fields
```

---

## Integration Points

### With Payment Processing

```php
// In PaymentService::processCharge()
$sumitCustomer = $response['Customer'] ?? [];

if (!empty($sumitCustomer)) {
    $customerMergeService = app(CustomerMergeService::class);
    $localCustomer = $customerMergeService->syncFromSumit($sumitCustomer);

    // Now $localCustomer is synced with SUMIT data
}
```

### With Webhooks

```php
// In SumitWebhookController
$sumitCustomer = $request->input('Customer');

$customerMergeService = app(CustomerMergeService::class);
$localCustomer = $customerMergeService->syncFromSumit($sumitCustomer);
```

### With Event Listeners

```php
// In CustomerSyncListener
public function handle(PaymentCompleted $event)
{
    $sumitCustomer = $event->transaction->raw_response['Customer'] ?? [];

    if (!empty($sumitCustomer)) {
        $customerMergeService = app(CustomerMergeService::class);
        $customerMergeService->syncFromSumit($sumitCustomer);
    }
}
```

---

## Best Practices

### ✅ DO

1. **Add SUMIT ID field to customer model**
   ```php
   $table->unsignedBigInteger('sumit_customer_id')->nullable()->unique();
   ```

2. **Configure field mapping in Admin Settings**
   - Don't hardcode field names
   - Use Admin Panel for configuration

3. **Always add sumit_customer_id to fillable**
   ```php
   protected $fillable = ['sumit_customer_id', ...];
   ```

4. **Use SUMIT ID as primary identifier**
   - More reliable than email
   - Never changes

5. **Handle graceful degradation**
   ```php
   $customer = $customerMergeService->syncFromSumit($data);
   if (!$customer) {
       // Sync disabled or failed, continue without customer link
   }
   ```

### ❌ DON'T

1. **Don't modify email during updates**
   - Service preserves email automatically
   - Email should be immutable identifier

2. **Don't skip migration**
   ```php
   // ❌ BAD - No sumit_customer_id field
   // Sync will fail silently

   // ✅ GOOD - Add field first
   $table->unsignedBigInteger('sumit_customer_id')->nullable();
   ```

3. **Don't assume sync is enabled**
   ```php
   // ❌ BAD
   $customer = $customerMergeService->syncFromSumit($data);
   $customer->update(...);  // NullPointerException if sync disabled!

   // ✅ GOOD
   $customer = $customerMergeService->syncFromSumit($data);
   if ($customer) {
       $customer->update(...);
   }
   ```

4. **Don't hardcode field names in code**
   ```php
   // ❌ BAD
   $user->sumit_customer_id = $sumitId;

   // ✅ GOOD
   $customerMergeService->syncFromSumit(['ID' => $sumitId]);
   ```

---

## Testing Recommendations

### 1. Unit Tests

```php
/** @test */
public function it_syncs_customer_from_sumit_data()
{
    $this->setupCustomerSync();

    $customerMergeService = app(CustomerMergeService::class);

    $customer = $customerMergeService->syncFromSumit([
        'ID' => 123456,
        'Email' => 'yossi@example.com',
        'FirstName' => 'יוסי',
        'LastName' => 'כהן',
        'Phone' => '0501234567',
    ]);

    $this->assertNotNull($customer);
    $this->assertEquals('yossi@example.com', $customer->email);
    $this->assertEquals(123456, $customer->sumit_customer_id);
}

protected function setupCustomerSync()
{
    OfficeGuySetting::set('customer_sync_enabled', true);
    OfficeGuySetting::set('customer_model', User::class);
    OfficeGuySetting::set('customer_field_email', 'email');
    OfficeGuySetting::set('customer_field_sumit_id', 'sumit_customer_id');
}
```

### 2. Update Existing Customer Test

```php
/** @test */
public function it_updates_existing_customer_by_sumit_id()
{
    $user = User::factory()->create([
        'email' => 'yossi@example.com',
        'sumit_customer_id' => 123456,
        'phone' => '0501111111',
    ]);

    $customerMergeService = app(CustomerMergeService::class);

    $customer = $customerMergeService->syncFromSumit([
        'ID' => 123456,
        'Email' => 'yossi@example.com',
        'Phone' => '0502222222',  // ← Updated
    ]);

    $user->refresh();
    $this->assertEquals('0502222222', $user->phone);  // Updated
    $this->assertEquals('yossi@example.com', $user->email);  // Preserved
}
```

---

## Summary

### Service Purpose
CustomerMergeService provides **zero-code customer synchronization** between SUMIT and local customer models using configurable field mapping.

### Key Strengths
- ✅ No code changes required (configure via Admin Panel)
- ✅ Works with any customer model (User, Customer, Account, etc.)
- ✅ Flexible field mapping (any field names)
- ✅ SUMIT ID priority (most reliable identifier)
- ✅ Graceful degradation (returns null on failure)
- ✅ Email preservation (never overwrites unique identifier)

### Architecture Pattern
- **Instance Service** (uses dependency injection)
- **Configuration-Driven** (no hardcoded field names)
- **Bidirectional Sync** (SUMIT → Local implemented, Local → SUMIT partially)

### Critical Implementation Notes
1. **SUMIT ID field MUST exist** in customer model (`sumit_customer_id`)
2. **Field must be in fillable** array for mass assignment
3. **Configure field mapping** in Admin Settings Page
4. **Always handle null returns** (sync disabled or failed)
5. **SUMIT ID is primary identifier** (email is fallback only)

### Future Enhancements
- Complete `syncToSumit()` implementation (call SUMIT API)
- Add batch sync command for existing customers
- Support custom field transformations (callbacks)
- Add sync logs for debugging

---

**Lines Analyzed**: 347
**Methods Documented**: 12
**Dependencies**: SettingsService
**Configuration Fields**: 10 (customer_sync_enabled + 9 field mappings)
**Architecture**: Instance service with flexible field mapping
