# OfficeGuy SUMIT Settings – DB vs Editable Keys (Tinker Verified)

## Summary
בדיקה בפועל מול טבלת `officeguy_settings` באמצעות `tinker` הראתה פערים אמיתיים בין מה שקיים ב־DB לבין מה שמוגדר כ־`editable` דרך `SettingsService::getEditableKeys()`.

## Keys שקיימים ב־DB בלבד (לא ב־getEditableKeys)
- `collection` (נשמר כ־JSON מלא בטבלה)
- `customer_model`
- `customer_sync_enabled`
- `donations_document_type`
- `invoice_currency_code`
- `invoice_due_days`
- `invoice_tax_rate`
- `routes.failed`
- `routes.success`
- `webhook_async`
- `webhook_max_tries`
- `webhook_queue`
- `webhook_timeout`
- `webhook_verify_ssl`

## Keys שקיימים ב־getEditableKeys בלבד (לא ב־DB)
- `pci_mode`
- `min_amount_for_payments`
- `min_amount_per_payment`
- `merchant_number`
- `subscriptions_merchant_number`
- `automatic_languages`
- `four_digits_year`
- `ssl_verify`
- `paypal_receipts`
- `bluesnap_receipts`
- `other_receipts`
- `donations_default_document_type`

## הסברים מרכזיים לפערים
- **שמות שונים בין DB לקוד**
  - DB: `customer_model` / `customer_sync_enabled`
  - קוד: `customer_model_class` / `customer_local_sync_enabled`

- **Routes**
  - DB שומר `routes.success` / `routes.failed`
  - הקוד מצפה ל־`routes_success` / `routes_failed`

- **Donations**
  - DB: `donations_document_type`
  - קוד: `donations_default_document_type`

- **collection**
  - DB שומר אובייקט אחד תחת `collection`
  - הקוד מצפה למפתחות מנוקדים: `collection.email`, `collection.sms`, וכו׳

- **Webhook config**
  - `webhook_async` / `webhook_queue` / `webhook_max_tries` / `webhook_timeout` / `webhook_verify_ssl` קיימים ב־DB וממופים לקונפיג דרך `OfficeGuyServiceProvider::loadDatabaseSettings()`, אבל אינם חלק מ־`getEditableKeys()`.

## Source of Truth
כל המסקנות נבדקו בפועל מול הטבלה בעזרת `php artisan tinker` במערכת הראשית.

## Action Plan (Comprehensive)

### 1) Baseline Verification (DB + Code)
- Run `tinker` to re-confirm current DB keys and values:
  - `OfficeGuySetting::pluck('key')->toArray()`
  - `OfficeGuySetting::pluck('value','key')->toArray()`
- Confirm which `SettingsService` class is loaded at runtime (package vs. local override) using `ReflectionClass`.
- Verify config defaults for each key in `config/officeguy.php` and `config/officeguy-webhooks.php`.
- Identify any keys stored as JSON blobs (e.g., `collection`) vs. dotted keys.

### 2) Decide the Source of Truth and Naming Strategy
- Choose one canonical naming scheme for each domain:
  - Customer settings: pick `customer_model_class` + `customer_local_sync_enabled` **or** keep legacy `customer_model` + `customer_sync_enabled` and add mapping.
  - Routes: choose flat keys (`routes_success`) **or** nested keys (`routes.success`), then map/normalize.
  - Donations: standardize on `donations_default_document_type` or legacy `donations_document_type`.
  - Collection: standardize on dotted keys (`collection.email`) or a single JSON record (`collection`).
- Document the mapping and ensure it is enforced in both `SettingsService` and Filament form.

### 3) Align Editable Keys with Actual Stored Keys
- Update `getEditableKeys()` to include all settings that should be editable in the UI.
- If a key is used internally but not intended to be editable, document it and exclude it consistently.
- For webhook system keys (`webhook_async`, `webhook_queue`, etc.) decide whether to include them in `getEditableKeys()` or keep them as special DB-to-config mappings.

### 4) Data Normalization / Migration (Safe, Backward Compatible)
- If you choose new canonical keys, add a migration or one-time sync script to:
  - Copy/move legacy keys to new keys.
  - Optionally keep legacy keys for backward compatibility.
  - Normalize `collection` JSON into dotted keys (or vice versa).
- Include idempotent behavior so the migration can be run multiple times safely.

### 5) UI & Admin Panel Consistency
- Ensure Filament form fields use the same keys as `SettingsService` expects.
- Add missing form fields if they are intended to be user-editable.
- Remove fields if they are legacy or not supported anymore.

### 6) Runtime Config Mapping
- Confirm `OfficeGuyServiceProvider::loadDatabaseSettings()` maps DB keys to config correctly.
- Ensure `routes.*` config is aligned with the expected keys used by `RouteConfig` and controllers.
- Ensure webhook config (`officeguy.webhooks.*`) uses consistent casting and DB keys.

### 7) Backward Compatibility & Deprecation Strategy
- If renaming keys, add temporary fallback reads (e.g., try new key, then legacy key).
- Log or warn when legacy keys are still in use, and set a deprecation timeline.

### 8) Testing / Validation
- Add or update tests (if available) to cover:
  - Settings read priority (DB overrides config).
  - UI edit → DB update → config override.
  - Route config resolution and webhook config mapping.
  - Customer merge/sync configuration paths.
- Validate by re-running tinker diff after updates to ensure no unexpected gaps remain.

### 9) Rollout Checklist
- Backup `officeguy_settings` table before any migration.
- Run migration/sync in staging first.
- Verify critical flows: payments, webhooks, customer sync, documents.
- Communicate any breaking changes in release notes.

---

If you want, I can implement the plan, starting with a minimal migration + SettingsService alignment, then re-run tinker to prove the gaps are resolved.

## PCI Mode – How the Code Checks It (Payment Pages + Payment Logic)

### Runtime Check (Canonical)
- The code consistently checks PCI mode using:
  - `config('officeguy.pci', config('officeguy.pci_mode', 'no'))`
- This means **`officeguy.pci` is the canonical key**, and `pci_mode` is only a **fallback**.

### Where It’s Used
- **Checkout flow (redirect vs PaymentsJS)**
  - `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/CheckoutController.php`
- **Public checkout validation + view settings**
  - `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/PublicCheckoutController.php`
- **Payment request building (PCI vs PaymentsJS token)**
  - `vendor/officeguy/laravel-sumit-gateway/src/Services/PaymentService.php`
- **Multi-vendor payments**
  - `vendor/officeguy/laravel-sumit-gateway/src/Services/MultiVendorPaymentService.php`
- **Payment form component**
  - `vendor/officeguy/laravel-sumit-gateway/src/View/Components/PaymentForm.php`
- **Blade views (UI toggles for PaymentsJS)**
  - `resources/views/vendor/officeguy/pages/checkout.blade.php`
  - `resources/views/vendor/officeguy/pages/digital.blade.php`
  - `resources/views/vendor/officeguy/pages/infrastructure.blade.php`
  - `resources/views/vendor/officeguy/pages/partials/payment-section.blade.php`

### Current DB Reality
- DB contains **`pci`** only (e.g., `pci = no`).
- DB does **not** contain `pci_mode`.

### What’s Correct To Do (Recommended)
1. **Keep `pci` as the only canonical DB key.**
2. **Remove `pci_mode` from `getEditableKeys()`** to avoid false gaps.
3. **Keep the fallback in code** (`pci_mode`) for backward compatibility.
4. **Optional migration**: if any `pci_mode` exists in DB, copy to `pci` and remove `pci_mode`.

### Why This Is Correct
- The payment logic already uses `pci` as primary.
- The admin UI uses `pci` (not `pci_mode`).
- Keeping `pci_mode` in editable keys creates misleading diffs without functional value.
