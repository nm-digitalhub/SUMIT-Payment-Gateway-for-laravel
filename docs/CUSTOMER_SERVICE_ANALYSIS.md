# ניתוח מעמיק: CustomerService.php

**מיקום:** `src/Services/CustomerService.php`
**גודל:** 162 שורות
**סוג:** Static Service Class (כל המתודות static)

---

## 🎯 תפקיד ראשי

**CustomerService הוא הגשר בין המערכת המקומית ל-SUMIT CRM** - מטפל בסנכרון לקוחות דו-כיווני:
- יצירה ועדכון לקוחות ב-SUMIT API
- משיכת פרטי לקוח עדכניים מ-SUMIT
- סנכרון אוטומטי בין CrmEntity ↔ Client ↔ SUMIT
- מניעת כפילויות לקוחות
- הפעלת סנכרון מסמכים אוטומטי

---

## 📋 קטגוריות מתודות

### 1. Core Sync Methods (2 מתודות ציבוריות)

#### `syncFromEntity(CrmEntity $entity)` ⭐⭐⭐
```php
public static function syncFromEntity(CrmEntity $entity): array
{
    $payload = self::buildPayloadFromEntity($entity);

    // Decide endpoint: create if no sumit_entity_id, else update
    $endpoint = $entity->sumit_entity_id
        ? '/accounting/customers/update/'
        : '/accounting/customers/create/';

    $response = OfficeGuyApi::post($payload, $endpoint, ...);

    if (success) {
        // Update local entity with SUMIT ID
        $entity->updateQuietly(['sumit_entity_id' => $sumitId]);

        // Pull fresh details from SUMIT
        self::pullCustomerDetails($sumitId, $entity);

        // Trigger document sync for client
        if ($entity->client) {
            DocumentService::syncForClient($entity->client);
        }
    }
}
```

**תפקיד:** הסנכרון הראשי מהמערכת המקומית ל-SUMIT

**Logic Flow:**
```
1. Build payload from local entity
2. Determine endpoint (create vs update):
   - No sumit_entity_id → Create new customer
   - Has sumit_entity_id → Update existing customer
3. Send to SUMIT API
4. On success:
   a. Save SUMIT customer ID locally
   b. Pull fresh details from SUMIT (2-way sync)
   c. Trigger document sync if client exists
5. Return result with success flag
```

**Return Format:**
```php
// Success
[
    'success' => true,
    'sumit_customer_id' => 123456789,
]

// Failure
[
    'success' => false,
    'error' => 'Failed to sync customer with SUMIT',
]
```

**🚨 Critical Features:**
- **Silent Update:** Uses `updateQuietly()` to avoid triggering observers
- **2-Way Sync:** Not just push → also pulls back fresh data
- **Auto Document Sync:** Triggers `DocumentService::syncForClient()` automatically
- **Safe Failure:** Non-blocking - pull failure doesn't fail entire sync

#### `pullCustomerDetails(int $sumitCustomerId, ?CrmEntity $entity = null)` ⭐⭐
**Endpoint:** `/accounting/customers/getdetailsurl/`

**תפקיד:** משיכת פרטי לקוח עדכניים מ-SUMIT ועדכון המודלים המקומיים

**Parameters:**
- `$sumitCustomerId` - SUMIT customer ID (חובה)
- `$entity` - CrmEntity local model (אופציונלי - אם null, רק מחזיר data)

**Payload Structure:**
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'Customer' => [
        'ID' => $sumitCustomerId,  // ← Just ID!
    ],
]
```

**Response Data:**
```php
$details = [
    'ID' => 123456789,
    'Name' => 'Company Ltd.',
    'EmailAddress' => 'info@company.com',
    'Phone' => '03-1234567',
    'Address' => 'Main St 123',
    'City' => 'Tel Aviv',
    'ZipCode' => '1234567',
    'CompanyNumber' => '123456789',  // Tax ID / VAT
];
```

**Critical Update Logic:**
```php
// Update CrmEntity (only if field is empty locally!)
$entity->updateQuietly([
    'name' => $entity->name ?: ($details['Name'] ?? null),
    'email' => $entity->email ?: ($details['EmailAddress'] ?? null),
    'phone' => $entity->phone ?: ($details['Phone'] ?? null),
    'address' => $entity->address ?: ($details['Address'] ?? null),
    'city' => $entity->city ?: ($details['City'] ?? null),
    'postal_code' => $entity->postal_code ?: ($details['ZipCode'] ?? null),
    'tax_id' => $entity->tax_id ?: ($details['CompanyNumber'] ?? null),
    'sumit_entity_id' => $entity->sumit_entity_id ?: ($details['ID'] ?? null),
]);

// Update related Client model (if exists)
if ($entity->client) {
    $entity->client->updateQuietly([
        'name' => $entity->client->name ?: ($details['Name'] ?? null),
        'email' => $entity->client->email ?: ($details['EmailAddress'] ?? null),
        'phone' => $entity->client->phone ?: ($details['Phone'] ?? null),
        'vat_number' => $entity->client->vat_number ?: ($details['CompanyNumber'] ?? null),
        'client_address' => $entity->client->client_address ?: ($details['Address'] ?? null),
        'client_city' => $entity->client->client_city ?: ($details['City'] ?? null),
        'client_postal_code' => $entity->client->client_postal_code ?: ($details['ZipCode'] ?? null),
        'sumit_customer_id' => $entity->client->sumit_customer_id ?: ($details['ID'] ?? null),
    ]);
}
```

**🚨 Critical Strategy:** **Non-Destructive Updates**
- Uses **Elvis operator** (`?:`) to preserve local data
- **Only fills empty fields** - never overwrites existing data
- Updates both `CrmEntity` AND `Client` models (dual sync)
- `updateQuietly()` prevents infinite loops

**Return Format:**
```php
// Success
[
    'success' => true,
    'customer' => [...],  // Full SUMIT customer data
]

// Failure
[
    'success' => false,
    'error' => 'Failed to pull customer details from SUMIT',
]
```

---

### 2. Payload Builder (1 מתודה protected)

#### `buildPayloadFromEntity(CrmEntity $entity)` ⭐⭐
**תפקיד:** בניית payload מלא ל-SUMIT API מה-CrmEntity המקומי

**Structure:**
```php
[
    'Credentials' => PaymentService::getCredentials(),
    'Details' => [
        'ID' => $entity->sumit_entity_id,           // If update
        'Folder' => $entity->folder?->sumit_folder_id,  // CRM folder
        'Name' => $entity->name,
        'Phone' => $entity->phone ?? $entity->mobile,
        'EmailAddress' => $entity->email,
        'Address' => $entity->address,
        'City' => $entity->city,
        'ZipCode' => $entity->postal_code,
        'CompanyNumber' => $entity->tax_id,         // VAT / Tax ID
        'Properties' => $fields['Properties'] ?? null,  // Custom fields
    ],
    'ResponseLanguage' => null,
]
```

**Critical Fallback Chain:**
```php
// Priority 1: CrmEntity data
$details['Name'] = $entity->name;
$details['EmailAddress'] = $entity->email;
$details['Phone'] = $entity->phone ?? $entity->mobile;

// Priority 2: Related Client data (if CrmEntity empty)
if ($entity->client) {
    $details['Name'] = $details['Name']
        ?? $entity->client->company
        ?? $entity->client->name
        ?? $entity->client->client_name;

    $details['EmailAddress'] = $details['EmailAddress']
        ?? $entity->client->email
        ?? $entity->client->client_email;

    $details['Phone'] = $details['Phone']
        ?? $entity->client->phone
        ?? $entity->client->client_phone
        ?? $entity->client->mobile_phone;

    $details['CompanyNumber'] = $details['CompanyNumber']
        ?? $entity->client->vat_number;

    $details['Address'] = $details['Address']
        ?? $entity->client->client_address;

    $details['City'] = $details['City']
        ?? $entity->client->client_city;

    $details['ZipCode'] = $details['ZipCode']
        ?? $entity->client->client_postal_code;
}
```

**🚨 Critical Features:**
1. **Comprehensive Fallback:** CrmEntity → Client (multiple field variations)
2. **Folder Association:** Links to SUMIT CRM folder if exists
3. **Custom Properties:** Supports SUMIT custom fields via `raw_data`
4. **Phone Fallback:** Uses `mobile` if `phone` is empty

---

## 🔄 תלויות קריטיות

### Services שנקראים
```php
// API Communication
OfficeGuyApi::post()  // All API calls

// Authentication
PaymentService::getCredentials()  // For Credentials payload

// Document Sync
DocumentService::syncForClient($client)  // After successful sync

// Configuration
config('officeguy.environment')  // API environment
```

### Models משומשים
```php
// Input Models
CrmEntity  // Primary entity model (with client relationship)

// Related Models (via relationships)
Client     // $entity->client (hasOne)
CrmFolder  // $entity->folder (belongsTo)

// No Creation - Only Updates
```

### Events (אין ישירות, אבל מופעלים עקיפות)
```php
// Via DocumentService::syncForClient()
// → May trigger document-related events
```

---

## 🔁 תהליך סנכרון מלא (Flow Diagram)

### Scenario 1: New Customer Creation
```
User creates CrmEntity (sumit_entity_id = null)
       ↓
syncFromEntity($entity)
       ↓
buildPayloadFromEntity($entity)
       ↓
POST /accounting/customers/create/
       ↓
SUMIT creates customer → returns CustomerID
       ↓
$entity->updateQuietly(['sumit_entity_id' => CustomerID])
       ↓
pullCustomerDetails(CustomerID, $entity)
       ↓
GET /accounting/customers/getdetailsurl/
       ↓
Update CrmEntity with SUMIT data (non-destructive)
       ↓
Update Client model (if exists)
       ↓
DocumentService::syncForClient($entity->client)
       ↓
✅ Complete sync (local ↔ SUMIT ↔ documents)
```

### Scenario 2: Existing Customer Update
```
User updates CrmEntity (sumit_entity_id = 123456789)
       ↓
syncFromEntity($entity)
       ↓
buildPayloadFromEntity($entity)
       ↓
POST /accounting/customers/update/  ← Different endpoint!
       ↓
SUMIT updates customer → returns CustomerID
       ↓
pullCustomerDetails(CustomerID, $entity)
       ↓
GET /accounting/customers/getdetailsurl/
       ↓
Fill empty fields with SUMIT data (preserve local changes)
       ↓
DocumentService::syncForClient($entity->client)
       ↓
✅ Bidirectional sync complete
```

### Scenario 3: Manual Pull (No Entity)
```
pullCustomerDetails(123456789, null)
       ↓
GET /accounting/customers/getdetailsurl/
       ↓
Return customer data (no local update)
       ↓
✅ Returns ['success' => true, 'customer' => [...]]
```

---

## 🚨 נקודות קריטיות לזכור

### 1. Non-Destructive Updates
```php
// ✅ CORRECT: Preserve local data
'name' => $entity->name ?: ($details['Name'] ?? null)

// ❌ WRONG: Would overwrite local changes!
'name' => $details['Name'] ?? $entity->name
```
**Logic:** `$entity->name ?: $sumit` means "use local if exists, else use SUMIT"

### 2. Silent Updates (No Observer Triggers)
```php
// ✅ CORRECT: Prevents infinite loops
$entity->updateQuietly(['sumit_entity_id' => $sumitId]);

// ❌ WRONG: Could trigger observers → infinite sync loop!
$entity->update(['sumit_entity_id' => $sumitId]);
```

### 3. Client Sync is Automatic
```php
// After every successful sync:
if ($entity->client) {
    DocumentService::syncForClient($entity->client);
}
```
**Impact:** Customer sync → triggers document sync → updates all invoices/receipts

### 4. Dual Model Updates
```php
// Updates BOTH models:
$entity->updateQuietly([...]);           // CrmEntity
$entity->client->updateQuietly([...]);   // Client (if exists)
```
**Why:** Same customer data exists in 2 tables (normalized structure)

### 5. Phone Field Fallback
```php
// Multiple phone field variations:
$entity->phone ?? $entity->mobile                    // CrmEntity
$entity->client->phone ?? $entity->client->client_phone ?? $entity->client->mobile_phone  // Client
```
**Why:** Different models use different field names (legacy compatibility)

---

## 📊 Field Mapping Reference

### CrmEntity ↔ SUMIT API Mapping
| CrmEntity Field | SUMIT API Field | Notes |
|----------------|----------------|-------|
| `sumit_entity_id` | `ID` | Primary identifier |
| `folder->sumit_folder_id` | `Folder` | CRM folder association |
| `name` | `Name` | Company/person name |
| `phone` / `mobile` | `Phone` | Phone with fallback |
| `email` | `EmailAddress` | Email address |
| `address` | `Address` | Street address |
| `city` | `City` | City name |
| `postal_code` | `ZipCode` | Postal/ZIP code |
| `tax_id` | `CompanyNumber` | VAT/Tax ID |
| `raw_data->Properties` | `Properties` | Custom fields (JSON) |

### Client Model ↔ SUMIT API Mapping
| Client Field | SUMIT API Field | Notes |
|-------------|----------------|-------|
| `sumit_customer_id` | `ID` | Alternative ID field |
| `name` / `company` / `client_name` | `Name` | Multiple name variations |
| `email` / `client_email` | `EmailAddress` | Email variations |
| `phone` / `client_phone` / `mobile_phone` | `Phone` | Phone variations |
| `vat_number` | `CompanyNumber` | VAT number |
| `client_address` | `Address` | Address field |
| `client_city` | `City` | City field |
| `client_postal_code` | `ZipCode` | Postal code field |

---

## 🎯 נקודות חוזק

✅ **Bidirectional Sync:** Push AND pull in single operation
✅ **Non-Destructive:** Preserves local changes, only fills gaps
✅ **Dual Model Sync:** Updates both CrmEntity and Client automatically
✅ **Auto Document Sync:** Triggers document sync after customer update
✅ **Silent Updates:** Uses `updateQuietly()` to prevent infinite loops
✅ **Comprehensive Fallbacks:** Multiple field variations covered
✅ **Safe Failures:** Pull errors don't fail entire sync
✅ **Flexible Usage:** Can pull without entity (data-only mode)

---

## ⚠️ נקודות לשיפור

### 1. Error Handling
**Current:**
```php
if ($response === null || ($response['Status'] ?? 1) !== 0) {
    return [
        'success' => false,
        'error' => $response['UserErrorMessage'] ?? 'Failed to sync customer with SUMIT',
    ];
}
```

**Suggestion:** Add error logging
```php
if ($response === null || ($response['Status'] ?? 1) !== 0) {
    $error = $response['UserErrorMessage'] ?? 'Failed to sync customer with SUMIT';

    \Log::error('CustomerService sync failed', [
        'entity_id' => $entity->id,
        'sumit_entity_id' => $entity->sumit_entity_id,
        'error' => $error,
        'response' => $response,
    ]);

    return ['success' => false, 'error' => $error];
}
```

### 2. Retry Logic
**Current:** Single API call, no retry on failure

**Suggestion:** Add retry for transient failures
```php
use Illuminate\Support\Facades\Retry;

$response = Retry::times(3)
    ->sleep(1000)  // 1 second between retries
    ->when(fn($e) => $e instanceof ConnectionException)
    ->attempt(fn() => OfficeGuyApi::post($payload, $endpoint));
```

### 3. Batch Sync Support
**Current:** Only single entity sync

**Suggestion:** Add batch sync method
```php
public static function syncMultipleEntities(Collection $entities): array
{
    $results = [];

    foreach ($entities as $entity) {
        $results[$entity->id] = self::syncFromEntity($entity);
    }

    return [
        'total' => $entities->count(),
        'success' => collect($results)->where('success', true)->count(),
        'failed' => collect($results)->where('success', false)->count(),
        'results' => $results,
    ];
}
```

### 4. Event Dispatching
**Current:** No events dispatched

**Suggestion:** Add events for better integration
```php
use OfficeGuy\LaravelSumitGateway\Events\CustomerSynced;
use OfficeGuy\LaravelSumitGateway\Events\CustomerSyncFailed;

// In syncFromEntity():
if ($success) {
    event(new CustomerSynced($entity, $sumitId));
} else {
    event(new CustomerSyncFailed($entity, $error));
}
```

### 5. Validation Layer
**Current:** No validation before sending to SUMIT

**Suggestion:** Add validation
```php
protected static function validateEntity(CrmEntity $entity): bool
{
    $requiredFields = ['name', 'email'];

    foreach ($requiredFields as $field) {
        if (empty($entity->$field) && empty($entity->client?->$field)) {
            throw new \InvalidArgumentException("Missing required field: {$field}");
        }
    }

    return true;
}

// In syncFromEntity():
self::validateEntity($entity);  // Before building payload
```

---

## 🧪 Testing Recommendations

### Unit Tests
```php
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Services\CustomerService;

class CustomerServiceTest extends TestCase
{
    /** @test */
    public function it_creates_new_customer_in_sumit()
    {
        Http::fake([
            '*/accounting/customers/create/' => Http::response([
                'Status' => 0,
                'Data' => ['CustomerID' => 123456789],
            ], 200),
            '*/accounting/customers/getdetailsurl/' => Http::response([
                'Status' => 0,
                'Data' => [
                    'Customer' => [
                        'ID' => 123456789,
                        'Name' => 'Test Company',
                        'EmailAddress' => 'test@company.com',
                    ],
                ],
            ], 200),
        ]);

        $entity = CrmEntity::factory()->create([
            'sumit_entity_id' => null,  // New customer
            'name' => 'Test Company',
            'email' => 'test@company.com',
        ]);

        $result = CustomerService::syncFromEntity($entity);

        $this->assertTrue($result['success']);
        $this->assertEquals(123456789, $result['sumit_customer_id']);
        $this->assertEquals(123456789, $entity->fresh()->sumit_entity_id);
    }

    /** @test */
    public function it_updates_existing_customer_in_sumit()
    {
        Http::fake([
            '*/accounting/customers/update/' => Http::response([
                'Status' => 0,
                'Data' => ['CustomerID' => 123456789],
            ], 200),
            '*/accounting/customers/getdetailsurl/' => Http::response([
                'Status' => 0,
                'Data' => [
                    'Customer' => [
                        'ID' => 123456789,
                        'Name' => 'Updated Company',
                    ],
                ],
            ], 200),
        ]);

        $entity = CrmEntity::factory()->create([
            'sumit_entity_id' => 123456789,  // Existing customer
            'name' => 'Updated Company',
        ]);

        $result = CustomerService::syncFromEntity($entity);

        $this->assertTrue($result['success']);
        Http::assertSent(fn($req) => str_contains($req->url(), '/update/'));
    }

    /** @test */
    public function it_preserves_local_data_when_pulling_details()
    {
        Http::fake([
            '*/accounting/customers/getdetailsurl/' => Http::response([
                'Status' => 0,
                'Data' => [
                    'Customer' => [
                        'ID' => 123456789,
                        'Name' => 'SUMIT Name',
                        'EmailAddress' => 'sumit@company.com',
                        'Phone' => '03-9999999',
                    ],
                ],
            ], 200),
        ]);

        $entity = CrmEntity::factory()->create([
            'sumit_entity_id' => 123456789,
            'name' => 'Local Name',      // Has local value
            'email' => null,             // Empty - should fill
            'phone' => null,             // Empty - should fill
        ]);

        CustomerService::pullCustomerDetails(123456789, $entity);

        $entity->refresh();

        // Should preserve local name
        $this->assertEquals('Local Name', $entity->name);

        // Should fill empty fields
        $this->assertEquals('sumit@company.com', $entity->email);
        $this->assertEquals('03-9999999', $entity->phone);
    }

    /** @test */
    public function it_syncs_both_entity_and_client_models()
    {
        Http::fake([
            '*/accounting/customers/getdetailsurl/' => Http::response([
                'Status' => 0,
                'Data' => [
                    'Customer' => [
                        'ID' => 123456789,
                        'Name' => 'Test Company',
                        'EmailAddress' => 'test@company.com',
                    ],
                ],
            ], 200),
        ]);

        $client = \App\Models\Client::factory()->create();
        $entity = CrmEntity::factory()->create([
            'sumit_entity_id' => 123456789,
            'client_id' => $client->id,
            'name' => null,
            'email' => null,
        ]);

        CustomerService::pullCustomerDetails(123456789, $entity);

        $entity->refresh();
        $client->refresh();

        // Both models should be updated
        $this->assertEquals('Test Company', $entity->name);
        $this->assertEquals('Test Company', $client->name);
        $this->assertEquals('test@company.com', $entity->email);
        $this->assertEquals('test@company.com', $client->email);
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        Http::fake([
            '*/accounting/customers/create/' => Http::response([
                'Status' => 1,
                'UserErrorMessage' => 'Customer already exists',
            ], 400),
        ]);

        $entity = CrmEntity::factory()->create([
            'sumit_entity_id' => null,
        ]);

        $result = CustomerService::syncFromEntity($entity);

        $this->assertFalse($result['success']);
        $this->assertEquals('Customer already exists', $result['error']);
    }
}
```

### Integration Tests
```php
/** @test */
public function it_triggers_document_sync_after_customer_sync()
{
    DocumentService::shouldReceive('syncForClient')
        ->once()
        ->with(\Mockery::on(fn($client) => $client->id === $this->client->id));

    $entity = CrmEntity::factory()->create([
        'client_id' => $this->client->id,
        'sumit_entity_id' => null,
    ]);

    CustomerService::syncFromEntity($entity);
}
```

---

## 📚 Complete Usage Examples

### Example 1: Sync New Customer to SUMIT
```php
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Services\CustomerService;

// Create new CrmEntity (from user registration, etc.)
$entity = CrmEntity::create([
    'name' => 'Acme Corporation',
    'email' => 'contact@acme.com',
    'phone' => '03-1234567',
    'address' => 'Main St 123',
    'city' => 'Tel Aviv',
    'postal_code' => '1234567',
    'tax_id' => '123456789',
]);

// Sync to SUMIT (creates customer + pulls details + syncs documents)
$result = CustomerService::syncFromEntity($entity);

if ($result['success']) {
    echo "Customer synced! SUMIT ID: {$result['sumit_customer_id']}";
    // Customer is now linked: $entity->sumit_entity_id = 123456789
    // Documents automatically synced via DocumentService
} else {
    echo "Sync failed: {$result['error']}";
}
```

### Example 2: Update Existing Customer
```php
// Entity already has sumit_entity_id from previous sync
$entity = CrmEntity::where('sumit_entity_id', 123456789)->first();

// Update local data
$entity->update([
    'phone' => '03-9876543',  // New phone number
    'address' => 'New St 456',  // New address
]);

// Sync changes to SUMIT (updates customer + pulls fresh data)
$result = CustomerService::syncFromEntity($entity);

if ($result['success']) {
    // Local entity now has latest SUMIT data (bidirectional sync)
    echo "Customer updated in SUMIT and local data refreshed";
}
```

### Example 3: Pull Fresh Customer Details from SUMIT
```php
// Manually pull latest data from SUMIT (without local changes)
$entity = CrmEntity::where('sumit_entity_id', 123456789)->first();

$result = CustomerService::pullCustomerDetails(123456789, $entity);

if ($result['success']) {
    // Entity updated with latest SUMIT data (non-destructive)
    echo "Fresh data: {$result['customer']['Name']}";
    // Local non-empty fields preserved
    // Empty local fields filled with SUMIT data
}
```

### Example 4: Fetch Customer Data Only (No Local Update)
```php
// Get SUMIT customer data without updating local entity
$result = CustomerService::pullCustomerDetails(123456789, null);  // ← No entity

if ($result['success']) {
    $customer = $result['customer'];

    echo "Customer Name: {$customer['Name']}";
    echo "Email: {$customer['EmailAddress']}";
    echo "Phone: {$customer['Phone']}";
    echo "Tax ID: {$customer['CompanyNumber']}";

    // No local database updates performed
}
```

### Example 5: Sync Customer with Related Client
```php
use App\Models\Client;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;

// Create client (from app registration)
$client = Client::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '054-1234567',
]);

// Create related CrmEntity
$entity = CrmEntity::create([
    'client_id' => $client->id,
    'name' => null,  // Empty - will use client data
    'email' => null,  // Empty - will use client data
]);

// Sync to SUMIT (uses client data as fallback)
$result = CustomerService::syncFromEntity($entity);

if ($result['success']) {
    // Both models updated:
    // - $entity->sumit_entity_id = 123456789
    // - $client->sumit_customer_id = 123456789
    // - Both have fresh SUMIT data in empty fields

    echo "Customer synced from client data!";
}
```

### Example 6: Bulk Sync (Custom Implementation)
```php
// Sync multiple entities (recommended batch approach)
$entities = CrmEntity::whereNull('sumit_entity_id')->take(100)->get();

$results = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'errors' => [],
];

foreach ($entities as $entity) {
    $results['total']++;

    $result = CustomerService::syncFromEntity($entity);

    if ($result['success']) {
        $results['success']++;
    } else {
        $results['failed']++;
        $results['errors'][] = [
            'entity_id' => $entity->id,
            'error' => $result['error'],
        ];
    }

    // Rate limiting (5 per second)
    usleep(200000);  // 200ms delay
}

echo "Synced {$results['success']}/{$results['total']} customers";
```

### Example 7: Listener Integration (Auto-Sync on Model Save)
```php
// In EventServiceProvider.php
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;

CrmEntity::saved(function ($entity) {
    // Auto-sync to SUMIT after every save
    dispatch(function () use ($entity) {
        \OfficeGuy\LaravelSumitGateway\Services\CustomerService::syncFromEntity($entity);
    })->afterResponse();  // Run after HTTP response
});
```

### Example 8: Filament Resource Integration
```php
// In CrmEntityResource.php
use Filament\Actions;
use OfficeGuy\LaravelSumitGateway\Services\CustomerService;

public static function getRecordActions(): array
{
    return [
        Actions\Action::make('sync_to_sumit')
            ->label('Sync to SUMIT')
            ->icon('heroicon-o-arrow-path')
            ->action(function (CrmEntity $record) {
                $result = CustomerService::syncFromEntity($record);

                if ($result['success']) {
                    Notification::make()
                        ->title('Customer synced successfully')
                        ->body("SUMIT ID: {$result['sumit_customer_id']}")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Sync failed')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                }
            }),
    ];
}
```

---

## 🔐 Security Considerations

### 1. Data Privacy
**Current:** All customer data sent to SUMIT API
**Recommendation:** Ensure GDPR compliance
```php
// Add consent check before sync
if (!$entity->client?->has_consented_to_sync) {
    return ['success' => false, 'error' => 'Customer has not consented to data sync'];
}
```

### 2. API Credentials
**Current:** Uses `PaymentService::getCredentials()`
**Status:** ✅ Secure (from config/DB, not hardcoded)

### 3. Silent Updates
**Current:** Uses `updateQuietly()` to prevent observer loops
**Status:** ✅ Good practice, but document why

### 4. Data Validation
**Current:** No validation before sending
**Recommendation:** Add email/phone format validation
```php
if (!filter_var($entity->email, FILTER_VALIDATE_EMAIL)) {
    return ['success' => false, 'error' => 'Invalid email format'];
}
```

---

## 📊 Performance Considerations

### API Calls per Sync
```
syncFromEntity() makes 2-3 API calls:
1. POST /accounting/customers/create/ OR /update/  (always)
2. GET /accounting/customers/getdetailsurl/       (always)
3. DocumentService::syncForClient()                (if client exists)
   └─ May make additional API calls for documents
```

**Total Latency:** ~2-5 seconds per customer sync

### Optimization Suggestions
```php
// 1. Queue long-running syncs
dispatch(fn() => CustomerService::syncFromEntity($entity))
    ->afterResponse();

// 2. Batch document syncs
// Instead of: syncForClient() for each customer
// Do: Collect all clients → syncForMultipleClients()

// 3. Cache SUMIT customer data
Cache::remember("sumit_customer_{$sumitId}", 3600, fn() =>
    CustomerService::pullCustomerDetails($sumitId, null)
);
```

---

## ✅ Summary

**CustomerService = The CRM Sync Bridge**

**תפקידים ראשיים:**
- ✅ Sync local entities to SUMIT CRM
- ✅ Pull fresh customer data from SUMIT
- ✅ Maintain data consistency (bidirectional)
- ✅ Prevent data loss (non-destructive updates)
- ✅ Auto-trigger document sync

**נקודות חוזק:**
- ✅ Simple API (2 public methods)
- ✅ Bidirectional sync (push + pull)
- ✅ Non-destructive updates (preserves local data)
- ✅ Dual model support (CrmEntity + Client)
- ✅ Silent updates (no observer loops)
- ✅ Comprehensive fallbacks (multiple field variations)
- ✅ Auto document sync integration

**נקודות לשיפור:**
- ⚠️ Add error logging
- ⚠️ Add retry logic for transient failures
- ⚠️ Add batch sync support
- ⚠️ Dispatch events for better integration
- ⚠️ Add validation layer
- ⚠️ Add performance optimizations (caching, queueing)
- ⚠️ Add GDPR compliance checks

**Usage Frequency:**
- 🟢 **High:** Every customer create/update
- 🟢 **High:** Payment flow (customer sync)
- 🟡 **Medium:** Manual sync actions
- 🟡 **Medium:** Webhook processing

**Integration Points:**
- `PaymentService` → Credentials
- `DocumentService` → Auto sync after customer update
- `OfficeGuyApi` → All API communication
- `CrmEntity` model → Primary entity
- `Client` model → Related customer data

---

**Generated:** 2025-01-13
**Version:** v1.1.6
**Analyzed by:** Claude Code
