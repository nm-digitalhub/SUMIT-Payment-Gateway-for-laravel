# ניתוח מעמיק: SettingsService.php - Configuration Management Layer

**תאריך:** 2025-01-13
**קובץ:** `src/Services/SettingsService.php`
**שורות:** 335
**תפקיד:** Hybrid configuration system עם 3-layer priority (Database → Config → .env)

---

## 📋 סיכום מהיר

**SettingsService** הוא ה-Service שמנהל את כל הגדרות החבילה במערכת **3 שכבות priority**:

### מערכת ה-3 שכבות:

```
1️⃣ Database (officeguy_settings table) ← HIGHEST PRIORITY
           ↓ (if not found)
2️⃣ Config File (config/officeguy.php)
           ↓ (if not found)
3️⃣ .env Variables (OFFICEGUY_*)
```

### מאפיינים עיקריים:
- ✅ **74 Editable Settings** - רשימה מפורשת של כל ההגדרות הניתנות לעריכה
- ✅ **Database-First** - הגדרות מה-Admin Panel גוברות על הכל
- ✅ **Graceful Fallback** - אם הטבלה לא קיימת, משתמש בconfig בלבד
- ✅ **Cached Table Check** - מניעת N+1 queries בעת טעינת 74 הגדרות
- ✅ **Batch Operations** - `setMany()` לשמירה קבוצתית
- ✅ **Nested Array Support** - `Arr::dot()` לפירוק מבנים מקוננים
- ✅ **Reset to Defaults** - אפשרות לאיפוס להגדרות ברירת מחדל

---

## 🔧 מתודות (11 Methods)

### 1. `tableExists()` - Cached Table Existence Check (שורות 36-49) ⭐

**תפקיד:** בודק אם טבלת `officeguy_settings` קיימת, **עם caching**

```php
protected static ?bool $tableExistsCache = null;

protected function tableExists(): bool
{
    if (self::$tableExistsCache !== null) {
        return self::$tableExistsCache;  // ← Return cached result
    }

    try {
        self::$tableExistsCache = Schema::hasTable('officeguy_settings');
        return self::$tableExistsCache;
    } catch (\Exception $e) {
        self::$tableExistsCache = false;
        return false;
    }
}
```

**למה Caching?**

ללא caching:
```php
// Loading 74 settings = 74 x Schema::hasTable() calls!
$setting1 = $service->get('company_id');      // Schema::hasTable() #1
$setting2 = $service->get('private_key');     // Schema::hasTable() #2
// ... 72 more calls!
```

עם caching:
```php
// Loading 74 settings = 1 x Schema::hasTable() call!
$setting1 = $service->get('company_id');      // Schema::hasTable() #1
$setting2 = $service->get('private_key');     // Cached! ✅
// ... all others use cache!
```

**⚡ Performance:**
- **ללא cache:** 74 Schema::hasTable() queries
- **עם cache:** 1 Schema::hasTable() query

**🛡️ Safety:**
- Catch block מונע crash אם יש בעיה עם DB connection
- מחזיר `false` ב-fallback → משתמש בconfig בלבד

---

### 2. `get()` - Get Setting with Priority System (שורות 58-73) ⭐⭐⭐

**תפקיד:** המתודה הכי חשובה - מחזירה ערך הגדרה לפי **3-layer priority**

```php
public function get(string $key, mixed $default = null): mixed
{
    // 1️⃣ Try database first (if table exists)
    if ($this->tableExists()) {
        try {
            if (OfficeGuySetting::has($key)) {
                return OfficeGuySetting::get($key);  // ← HIGHEST PRIORITY!
            }
        } catch (\Exception $e) {
            // Table exists but query failed - continue to config
        }
    }

    // 2️⃣ Fallback to config (which includes .env defaults)
    return config("officeguy.{$key}", $default);
}
```

#### תהליך ההחלטה:

**תרחיש 1: טבלה קיימת + ערך בDB**
```php
$companyId = $service->get('company_id');

// Step 1: tableExists() → true
// Step 2: OfficeGuySetting::has('company_id') → true
// Step 3: OfficeGuySetting::get('company_id') → "1082100759"
// Result: "1082100759" ← מהDB!
```

**תרחיש 2: טבלה קיימת + אין ערך בDB**
```php
$companyId = $service->get('company_id');

// Step 1: tableExists() → true
// Step 2: OfficeGuySetting::has('company_id') → false
// Step 3: config('officeguy.company_id') → env('OFFICEGUY_COMPANY_ID', '')
// Result: ערך מה-.env (אם קיים)
```

**תרחיש 3: טבלה לא קיימת (migrations טרם רצו)**
```php
$companyId = $service->get('company_id');

// Step 1: tableExists() → false (cached)
// Step 2: config('officeguy.company_id') → env('OFFICEGUY_COMPANY_ID', '')
// Result: ערך מה-.env (אם קיים)
```

**תרחיש 4: Query נכשל (DB connection down)**
```php
$companyId = $service->get('company_id');

// Step 1: tableExists() → true
// Step 2: OfficeGuySetting::has('company_id') → throws Exception
// Step 3: catch → continue to config
// Step 4: config('officeguy.company_id') → env('OFFICEGUY_COMPANY_ID', '')
// Result: graceful fallback ל-config ✅
```

#### שימושים בפועל:

**בכל ה-Services:**
```php
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

class PaymentService
{
    public static function getCredentials(): array
    {
        $settings = app(SettingsService::class);

        return [
            'CompanyID' => (int) $settings->get('company_id'),      // DB → Config → .env
            'APIKey' => $settings->get('private_key'),              // DB → Config → .env
        ];
    }
}
```

**ב-Filament Resources:**
```php
// In Filament, config already loaded with DB overrides by ServiceProvider
$companyId = config('officeguy.company_id');  // Already merged!

// But you can still use SettingsService for clarity:
$settings = app(SettingsService::class);
$companyId = $settings->get('company_id');
```

---

### 3. `set()` - Save Setting to Database (שורות 82-89)

**תפקיד:** שומר ערך הגדרה ל-DB (overrides config/env)

```php
public function set(string $key, mixed $value): void
{
    if (!$this->tableExists()) {
        throw new \RuntimeException('Settings table does not exist. Run migrations first.');
    }

    OfficeGuySetting::set($key, $value);
}
```

**⚠️ Critical:**
- זורק exception אם הטבלה לא קיימת
- לא ניתן לשמור אם migrations טרם רצו
- פעולה זו **מיידית** - לא צריך cache clear

**דוגמה:**
```php
$settings = app(SettingsService::class);

// User changes company_id via Admin Panel:
$settings->set('company_id', '1234567890');

// From this point on:
$settings->get('company_id');  // → "1234567890" (from DB)
config('officeguy.company_id'); // → Still old value! (not auto-updated)
```

**🔄 Synchronization:**
```php
// In ServiceProvider::boot():
$this->loadDatabaseSettings();  // ← Runs on every request

// This method updates config() with DB values:
foreach ($dbSettings as $key => $value) {
    config(["officeguy.{$key}" => $value]);  // ← DB overrides config
}
```

---

### 4. `setMany()` - Batch Save Settings (שורות 97-105)

**תפקיד:** שומר מספר הגדרות בבת אחת (עם תמיכה במבנים מקוננים)

```php
public function setMany(array $settings): void
{
    // Flatten nested arrays (e.g., collection.email) before saving
    $settings = Arr::dot($settings);

    foreach ($settings as $key => $value) {
        $this->set($key, $value);
    }
}
```

#### שימוש עיקרי: Filament Settings Page Save Action

**Input מ-Filament Form:**
```php
[
    'company_id' => '1082100759',
    'private_key' => 'sk_live_abc123',
    'collection' => [
        'email' => 'collect@example.com',
        'sms' => true,
        'schedule_time' => '09:00',
    ],
]
```

**אחרי `Arr::dot()`:**
```php
[
    'company_id' => '1082100759',
    'private_key' => 'sk_live_abc123',
    'collection.email' => 'collect@example.com',      // ← Flattened!
    'collection.sms' => true,                         // ← Flattened!
    'collection.schedule_time' => '09:00',            // ← Flattened!
]
```

**נשמר כ-5 רשומות בטבלה:**
```sql
INSERT INTO officeguy_settings (key, value) VALUES
  ('company_id', '1082100759'),
  ('private_key', 'sk_live_abc123'),
  ('collection.email', 'collect@example.com'),
  ('collection.sms', '1'),
  ('collection.schedule_time', '09:00');
```

#### קוד מ-OfficeGuySettings.php (Filament Page):

```php
public function save(): void
{
    try {
        // Get form data (includes nested arrays)
        $formData = $this->form->getState();

        // Save all at once (Arr::dot handles nested keys)
        $this->settingsService->setMany($formData);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    } catch (\Exception $e) {
        // Error handling
    }
}
```

---

### 5. `has()` - Check if Setting Exists in DB (שורות 113-124)

**תפקיד:** בודק אם הגדרה קיימת **בDB** (לא בconfig!)

```php
public function has(string $key): bool
{
    if (!$this->tableExists()) {
        return false;  // No table = no DB settings
    }

    try {
        return OfficeGuySetting::has($key);
    } catch (\Exception $e) {
        return false;
    }
}
```

**⚠️ שים לב:**
```php
$settings = app(SettingsService::class);

// Setting exists in .env but NOT in DB:
$settings->has('company_id');  // → false (not in DB)
$settings->get('company_id');  // → "1082100759" (from .env) ✅

// Setting exists in DB:
$settings->set('company_id', '9999999999');
$settings->has('company_id');  // → true (in DB) ✅
```

**שימוש:**
- בדיקה אם user ערך הגדרה מסוימת
- הבחנה בין ערך ברירת מחדל לערך ממותאם

---

### 6. `remove()` - Remove Setting from DB (שורות 132-137)

**תפקיד:** מוחק הגדרה מה-DB (→ revert to config default)

```php
public function remove(string $key): void
{
    if ($this->tableExists()) {
        OfficeGuySetting::remove($key);
    }
}
```

**תרחיש שימוש:**
```php
// User changed company_id via Admin Panel:
$settings->set('company_id', '9999999999');
$settings->get('company_id');  // → "9999999999" (from DB)

// Reset to default:
$settings->remove('company_id');
$settings->get('company_id');  // → "1082100759" (from .env) ✅
```

---

### 7. `all()` - Get All Settings (Merged) (שורות 144-160)

**תפקיד:** מחזיר **כל** ההגדרות (config defaults + DB overrides)

```php
public function all(): array
{
    // Start with config defaults
    $settings = config('officeguy', []);

    // Override with database values (if table exists)
    if ($this->tableExists()) {
        try {
            $dbSettings = OfficeGuySetting::getAllSettings();
            $settings = array_merge($settings, $dbSettings);
        } catch (\Exception $e) {
            // Failed to query - return config only
        }
    }

    return $settings;
}
```

**תהליך Merge:**
```php
// config/officeguy.php (defaults):
[
    'company_id' => '',                    // From .env
    'private_key' => '',                   // From .env
    'pci_mode' => 'no',                   // Hardcoded default
    'max_payments' => 12,                 // Hardcoded default
]

// Database overrides:
[
    'company_id' => '1082100759',         // User edited
    'pci_mode' => 'redirect',             // User edited
]

// Result of all():
[
    'company_id' => '1082100759',         // ← From DB (override)
    'private_key' => '',                  // ← From config (no override)
    'pci_mode' => 'redirect',             // ← From DB (override)
    'max_payments' => 12,                 // ← From config (no override)
]
```

---

### 8. `getEditableKeys()` - List of 74 Editable Settings (שורות 167-274) ⭐

**תפקיד:** מחזיר **רשימה מפורשת** של כל ההגדרות הניתנות לעריכה

```php
public function getEditableKeys(): array
{
    return [
        // Credentials (3)
        'company_id',
        'private_key',
        'public_key',

        // Environment (3)
        'environment',      // www, dev, test
        'pci',
        'pci_mode',        // no, redirect, yes

        // Payment Settings (6)
        'testing',
        'max_payments',
        'min_amount_for_payments',
        'min_amount_per_payment',
        'authorize_only',
        'authorize_added_percent',
        'authorize_minimum_addition',
        'merchant_number',
        'subscriptions_merchant_number',

        // Document Settings (4)
        'draft_document',
        'email_document',
        'create_order_document',
        'automatic_languages',

        // Customer Settings (2)
        'merge_customers',
        'citizen_id',

        // Token Settings (4)
        'support_tokens',
        'token_param',      // J2 or J5
        'cvv',
        'four_digits_year',
        'single_column_layout',

        // Bit Settings (1)
        'bit_enabled',

        // Logging (3)
        'logging',
        'log_channel',
        'ssl_verify',

        // Stock Settings (2)
        'stock_sync_freq',
        'checkout_stock_sync',

        // Receipt Settings (3)
        'paypal_receipts',
        'bluesnap_receipts',
        'other_receipts',

        // Public Checkout (3)
        'enable_public_checkout',
        'public_checkout_path',
        'payable_model',

        // Field Mapping (6)
        'field_map_amount',
        'field_map_currency',
        'field_map_customer_name',
        'field_map_customer_email',
        'field_map_customer_phone',
        'field_map_description',

        // Collection/Debt Settings (5)
        'collection.email',
        'collection.sms',
        'collection.schedule_time',
        'collection.reminder_days',
        'collection.max_attempts',

        // Webhook Settings (9)
        'webhook_payment_completed',
        'webhook_payment_failed',
        'webhook_document_created',
        'webhook_subscription_created',
        'webhook_subscription_charged',
        'webhook_bit_payment_completed',
        'webhook_stock_synced',
        'webhook_secret',

        // Customer Management (12)
        'customer_merging_enabled',
        'customer_local_sync_enabled',
        'customer_model_class',
        'customer_field_email',
        'customer_field_name',
        'customer_field_phone',
        'customer_field_first_name',
        'customer_field_last_name',
        'customer_field_company',
        'customer_field_address',
        'customer_field_city',
        'customer_field_sumit_id',

        // Route Configuration (9)
        'routes_prefix',
        'routes_card_callback',
        'routes_bit_webhook',
        'routes_sumit_webhook',
        'routes_enable_checkout_endpoint',
        'routes_checkout_charge',
        'routes_document_download',
        'routes_success',
        'routes_failed',

        // Subscriptions (6)
        'subscriptions_enabled',
        'subscriptions_default_interval',
        'subscriptions_default_cycles',
        'subscriptions_allow_pause',
        'subscriptions_retry_failed',
        'subscriptions_max_retries',

        // Donations (3)
        'donations_enabled',
        'donations_allow_mixed',
        'donations_default_document_type',

        // Multi-Vendor (3)
        'multivendor_enabled',
        'multivendor_validate_credentials',
        'multivendor_allow_authorize',

        // Upsell/CartFlows (3)
        'upsell_enabled',
        'upsell_require_token',
        'upsell_max_per_order',
    ];
}
```

**סה"כ: 74 הגדרות!**

**קטגוריות:**
1. **Credentials** (3) - Company ID, Private Key, Public Key
2. **Environment** (3) - Environment, PCI, PCI Mode
3. **Payment** (8) - Max payments, min amounts, authorize settings
4. **Documents** (4) - Draft, email, auto-creation
5. **Customers** (14) - Merging, local sync, field mapping
6. **Tokens** (4) - Support, param (J2/J5), validation
7. **Bit** (1) - Enable Bit payments
8. **Logging** (3) - Logging, channel, SSL verify
9. **Stock** (2) - Sync frequency, checkout sync
10. **Receipts** (3) - PayPal, BlueSnap, other
11. **Public Checkout** (3) - Enable, path, model
12. **Field Mapping** (6) - Amount, currency, customer fields
13. **Collection/Debt** (5) - Email, SMS, schedule, reminders
14. **Webhooks** (9) - Event URLs, secret
15. **Routes** (9) - Prefix, callback paths, endpoints
16. **Subscriptions** (6) - Enable, intervals, retries
17. **Donations** (3) - Enable, mixed, document type
18. **Multi-Vendor** (3) - Enable, validation, authorize
19. **Upsell** (3) - Enable, token requirement, max per order

---

### 9. `getEditableSettings()` - Get All Editable with Values (שורות 283-311) ⭐

**תפקיד:** מחזיר **כל** ה-74 הגדרות הניתנות לעריכה **עם הערכים שלהן** (optimized!)

```php
public function getEditableSettings(): array
{
    // Start with config defaults for all editable keys
    $settings = [];
    $editableKeys = $this->getEditableKeys();

    foreach ($editableKeys as $key) {
        Arr::set($settings, $key, config("officeguy.{$key}"));
    }

    // Override with database values in one query (if table exists)
    if ($this->tableExists()) {
        try {
            // ⚡ Fetch all DB settings at once instead of one-by-one
            $dbSettings = OfficeGuySetting::getAllSettings();

            // Only override editable keys
            foreach ($editableKeys as $key) {
                if (isset($dbSettings[$key])) {
                    Arr::set($settings, $key, $dbSettings[$key]);
                }
            }
        } catch (\Exception $e) {
            // Failed to query - return config only
        }
    }

    return $settings;
}
```

#### Performance Optimization:

**ללא Optimization (N+1):**
```php
// 74 separate queries!
foreach ($editableKeys as $key) {
    $value = OfficeGuySetting::get($key);  // Query #1, #2, #3... #74
}
```

**עם Optimization:**
```php
// 1 query!
$dbSettings = OfficeGuySetting::getAllSettings();  // Query #1 - fetch all

// Then lookup in memory:
foreach ($editableKeys as $key) {
    if (isset($dbSettings[$key])) {  // Memory lookup ✅
        // Use DB value
    }
}
```

**⚡ Performance:**
- **ללא optimization:** 74 DB queries
- **עם optimization:** 1 DB query

#### שימוש: Filament Settings Page

```php
// src/Filament/Pages/OfficeGuySettings.php

public function mount(): void
{
    // Load all 74 settings efficiently (1 query!)
    $settings = $this->settingsService->getEditableSettings();

    // Populate Filament form
    $this->form->fill($settings);
}
```

---

### 10. `resetToDefault()` - Reset Single Setting (שורות 319-322)

**תפקיד:** Alias ל-`remove()` - מאפס הגדרה אחת

```php
public function resetToDefault(string $key): void
{
    $this->remove($key);  // Remove from DB = revert to config
}
```

**דוגמה:**
```php
// User changed max_payments:
$settings->set('max_payments', 36);
$settings->get('max_payments');  // → 36 (from DB)

// Reset to default:
$settings->resetToDefault('max_payments');
$settings->get('max_payments');  // → 12 (from config default)
```

---

### 11. `resetAllToDefaults()` - Reset All Settings (שורות 329-334)

**תפקיד:** **מוחק את כל הטבלה!** (revert all to config defaults)

```php
public function resetAllToDefaults(): void
{
    if ($this->tableExists()) {
        OfficeGuySetting::query()->delete();  // ← DELETE FROM officeguy_settings
    }
}
```

**⚠️ DANGEROUS!**
- מוחק **כל** ההגדרות שהמשתמש ערך
- לא ניתן לשחזר (אלא אם יש backup)
- צריך confirmation מהמשתמש!

**שימוש:**
```php
// In Filament Settings Page:
Action::make('reset_all')
    ->requiresConfirmation()
    ->modalHeading('Reset All Settings?')
    ->modalDescription('This will delete all custom settings and revert to defaults. This action cannot be undone!')
    ->action(function () {
        $this->settingsService->resetAllToDefaults();
    });
```

---

## 🔗 תלויות (Dependencies)

### Models:
```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting;
```
- `OfficeGuySetting::has($key)` - Check existence
- `OfficeGuySetting::get($key)` - Get value
- `OfficeGuySetting::set($key, $value)` - Set value
- `OfficeGuySetting::remove($key)` - Delete key
- `OfficeGuySetting::getAllSettings()` - Fetch all (1 query)

### Laravel Facades:
```php
use Illuminate\Support\Facades\Schema;
```
- `Schema::hasTable('officeguy_settings')` - Table existence check

### Laravel Helpers:
```php
use Illuminate\Support\Arr;
```
- `Arr::dot($array)` - Flatten nested arrays
- `Arr::set($array, $key, $value)` - Set value with dot notation support

### Configuration:
```php
config('officeguy.company_id')          // Get from config
config(['officeguy.company_id' => 123]) // Set config at runtime
```

---

## 🚀 מי משתמש ב-SettingsService?

### 1. OfficeGuyServiceProvider (Bootstrap)

**קובץ:** `src/OfficeGuyServiceProvider.php:95-114`

```php
protected function loadDatabaseSettings(): void
{
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('officeguy_settings')) {
            return;
        }

        $dbSettings = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuySetting::getAllSettings();

        // Override config with database values
        foreach ($dbSettings as $key => $value) {
            config(["officeguy.{$key}" => $value]);  // ← DB overrides config!
        }
    } catch (\Exception $e) {
        // Silently fail - config defaults will be used
    }
}

public function boot(): void
{
    $this->loadDatabaseSettings();  // ← Runs on EVERY REQUEST!
    // ... rest of boot logic
}
```

**תהליך:**
1. ServiceProvider boots
2. `loadDatabaseSettings()` runs
3. Fetches all DB settings in 1 query
4. Overrides `config('officeguy.*')` with DB values
5. From now on: `config('officeguy.company_id')` returns DB value ✅

### 2. PaymentService

```php
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

public static function getCredentials(): array
{
    $settings = app(SettingsService::class);

    return [
        'CompanyID' => (int) $settings->get('company_id'),
        'APIKey' => $settings->get('private_key'),
    ];
}
```

### 3. TokenService

```php
public static function getTokenRequest(string $pciMode = 'no'): array
{
    $settings = app(SettingsService::class);

    return [
        'ParamJ' => $settings->get('token_param', '5'),  // J2 or J5
        'Credentials' => PaymentService::getCredentials(),
    ];
}
```

### 4. Filament Settings Page (Admin)

**קובץ:** `src/Filament/Pages/OfficeGuySettings.php`

```php
protected SettingsService $settingsService;

public function mount(): void
{
    // Load all 74 settings (1 query!)
    $settings = $this->settingsService->getEditableSettings();
    $this->form->fill($settings);
}

public function save(): void
{
    // Get form data
    $formData = $this->form->getState();

    // Save all at once
    $this->settingsService->setMany($formData);

    Notification::make()
        ->title('Settings saved')
        ->success()
        ->send();
}
```

### 5. כל ה-Services האחרים

```php
// Instead of:
$companyId = config('officeguy.company_id');  // Might not have DB override

// Use:
$settings = app(SettingsService::class);
$companyId = $settings->get('company_id');    // Always has DB override ✅
```

---

## 🛡️ Security & Safety Features

### 1. Graceful Fallback
```php
try {
    if (OfficeGuySetting::has($key)) {
        return OfficeGuySetting::get($key);
    }
} catch (\Exception $e) {
    // Continue to config fallback
}
```

**מה זה מונע:**
- ✅ Crash אם DB connection down
- ✅ Crash אם טבלה לא קיימת
- ✅ Crash אם query נכשל

**תוצאה:** האפליקציה ממשיכה לעבוד עם config defaults

### 2. Table Existence Caching
```php
protected static ?bool $tableExistsCache = null;
```

**מה זה מונע:**
- ✅ N+1 `Schema::hasTable()` queries
- ✅ Performance degradation
- ✅ Unnecessary DB load

### 3. Explicit Editable Keys List
```php
public function getEditableKeys(): array
{
    return [ /* 74 keys */ ];
}
```

**מה זה מונע:**
- ✅ Arbitrary key injection
- ✅ Editing internal/system settings
- ✅ Security vulnerabilities

### 4. Exception Throwing on Invalid Operations
```php
if (!$this->tableExists()) {
    throw new \RuntimeException('Settings table does not exist. Run migrations first.');
}
```

**מה זה מונע:**
- ✅ Silent failures
- ✅ Data loss
- ✅ Unexpected behavior

---

## ⚙️ Configuration Flow

### הגדרת ערך מתחילה לסוף:

```
1. Developer sets .env:
   OFFICEGUY_COMPANY_ID=1082100759

2. Config file reads .env:
   'company_id' => env('OFFICEGUY_COMPANY_ID', '')

3. ServiceProvider loads DB overrides:
   $dbSettings = OfficeGuySetting::getAllSettings();
   config(['officeguy.company_id' => '9999999999']);  ← DB override!

4. Application code reads setting:
   $settings->get('company_id')  → "9999999999" (DB)
   config('officeguy.company_id') → "9999999999" (already merged)
```

### שינוי ערך דרך Admin Panel:

```
1. User opens /admin/office-guy-settings

2. Filament loads current values:
   $settings = $settingsService->getEditableSettings();  // 1 query

3. User changes company_id to "1234567890"

4. User clicks "Save":
   $settingsService->setMany(['company_id' => '1234567890']);

5. Next request:
   ServiceProvider::boot() loads DB settings
   config(['officeguy.company_id' => '1234567890'])

6. All code now uses new value:
   $settings->get('company_id')  → "1234567890" ✅
   config('officeguy.company_id') → "1234567890" ✅
```

---

## 🎯 Best Practices

### ✅ DO:

1. **Use SettingsService in Services:**
```php
// ✅ GOOD
$settings = app(SettingsService::class);
$companyId = $settings->get('company_id');
```

2. **Use config() in Filament Resources:**
```php
// ✅ GOOD (after ServiceProvider boot)
$companyId = config('officeguy.company_id');
```

3. **Add new settings to getEditableKeys():**
```php
// ✅ GOOD
public function getEditableKeys(): array
{
    return [
        // ... existing keys
        'new_setting',  // ← Add here!
    ];
}
```

4. **Use setMany() for batch saves:**
```php
// ✅ GOOD
$settings->setMany([
    'company_id' => '123',
    'private_key' => 'sk_abc',
]);
```

### ❌ DON'T:

1. **Don't use env() directly:**
```php
// ❌ BAD
$companyId = env('OFFICEGUY_COMPANY_ID');

// ✅ GOOD
$companyId = $settings->get('company_id');
```

2. **Don't assume config is source of truth:**
```php
// ❌ BAD (might not have DB override)
$companyId = config('officeguy.company_id');

// ✅ GOOD (always checks DB first)
$companyId = $settings->get('company_id');
```

3. **Don't edit settings in loops:**
```php
// ❌ BAD (N queries)
foreach ($keys as $key) {
    $settings->set($key, $values[$key]);
}

// ✅ GOOD (1 batch operation)
$settings->setMany($values);
```

---

## 🔍 Known Issues & Limitations

### 1. Config not auto-refreshed after set()
**בעיה:** `config()` לא מתעדכן אוטומטית אחרי `set()`

```php
$companyId = config('officeguy.company_id');  // → "111"

$settings->set('company_id', '999');

$companyId = config('officeguy.company_id');  // → "111" (still!) ❌
$companyId = $settings->get('company_id');    // → "999" ✅
```

**פתרון:**
```php
// Manually update config:
config(['officeguy.company_id' => $newValue]);
```

### 2. No validation on set()
**בעיה:** ניתן לשמור ערכים לא תקינים

```php
// No validation!
$settings->set('company_id', 'invalid_value');  // ✅ Saved
$settings->set('max_payments', -1);             // ✅ Saved
```

**פתרון:** להוסיף validation בFilament form

### 3. No audit trail
**בעיה:** אין לוג של מי שינה מה וכשמה

**פתרון אפשרי:**
- להוסיף `spatie/laravel-activitylog`
- לתעד שינויים ב-webhook

### 4. resetAllToDefaults() is destructive
**בעיה:** מוחק הכל ללא אפשרות שחזור

**פתרון:**
- צריך confirmation modal
- יצירת backup לפני מחיקה

---

## 📝 Recommended Improvements

### Priority 1: Add Validation
```php
public function set(string $key, mixed $value): void
{
    // Validate before saving
    $this->validate($key, $value);

    OfficeGuySetting::set($key, $value);
}

protected function validate(string $key, mixed $value): void
{
    $rules = [
        'company_id' => 'numeric|digits:10',
        'max_payments' => 'integer|min:1|max:36',
        'environment' => 'in:www,dev,test',
        // ...
    ];

    if (!isset($rules[$key])) {
        return;  // No validation rule
    }

    validator([$key => $value], [$key => $rules[$key]])->validate();
}
```

### Priority 2: Auto-update config after set()
```php
public function set(string $key, mixed $value): void
{
    OfficeGuySetting::set($key, $value);

    // Auto-update config
    config(["officeguy.{$key}" => $value]);
}
```

### Priority 3: Add Activity Log
```php
public function set(string $key, mixed $value): void
{
    $oldValue = $this->get($key);

    OfficeGuySetting::set($key, $value);

    // Log change
    activity()
        ->causedBy(auth()->user())
        ->withProperties([
            'key' => $key,
            'old' => $oldValue,
            'new' => $value,
        ])
        ->log('Setting changed');
}
```

### Priority 4: Add Backup Before Reset
```php
public function resetAllToDefaults(): void
{
    if ($this->tableExists()) {
        // Backup first
        $backup = OfficeGuySetting::getAllSettings();
        Storage::put('backups/settings-' . now()->format('Y-m-d-H-i-s') . '.json', json_encode($backup));

        // Then delete
        OfficeGuySetting::query()->delete();
    }
}
```

---

## 🎓 Summary

**SettingsService** הוא ה-Service שמנהל את כל הגדרות החבילה במערכת hybrid:

**✅ Strengths:**
- 3-layer priority system (DB → Config → .env)
- 74 explicitly defined editable settings
- Graceful fallback if DB unavailable
- Performance optimization (caching, batch queries)
- Nested array support (Arr::dot)
- Reset functionality

**⚠️ Weaknesses:**
- No validation on set()
- No audit trail
- Config not auto-refreshed after set()
- resetAllToDefaults() is destructive

**🎯 Role:**
- Central configuration management
- Bridge between DB and config system
- Admin Panel backend
- Critical infrastructure component

---

**Generated:** 2025-01-13
