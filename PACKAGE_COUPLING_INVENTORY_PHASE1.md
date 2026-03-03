# Package Coupling Inventory — Phase 1 (Report Only)

**Objective:** Complete, verifiable inventory of every package-to-host coupling point with exact file paths and line numbers. No code changes.

**Scope:** `src/`, `config/`, `database/migrations/`, `routes/`, `resources/` (production code only; tests excluded from coupling findings).

---

## 1. Executive Summary

- **Hard model references** to `App\Models\*` (Client, Order, User, Package, MayaNetEsimProduct) and `App\Enums\UserRole`, `App\Jobs\ProcessPaidOrderJob` appear in 12+ production files; the most critical are in `DocumentService`, `CrmDataService`, `PublicCheckoutController`, `GenericFulfillmentHandler`, `OfficeGuyTransactionPolicy`, and `CrmEntity`.
- **Fallback model references** (`?? \App\Models\Client::class`) appear in 6 models and 2 controllers whenever `app('officeguy.customer_model')` is null; this ties the package to the host when customer model is not configured.
- **Migrations** contain 3 HOST-COUPLED files (FK to `clients` table) and 2 files using ambiguous table name `subscriptions` (package table is `officeguy_subscriptions`); the latter can cause migration failure or wrong-table linkage.
- **Polymorphic / table assumptions**: `DocumentService` hardcodes `subscriber_type = 'App\Models\User'` and `->from('users')` for subscription-document matching; this assumes the host uses a `users` table and User as subscriber.
- **Config and container** already abstract the customer model (DB → config → container); the contract is bypassed wherever code uses `?? \App\Models\Client::class` or direct `App\Models\*` references.
- **Must-fix list** (max 7) focuses on: host FKs in migrations, ambiguous `subscriptions` table, hardcoded DocumentService/PublicCheckoutController/GenericFulfillmentHandler/CrmDataService/CrmEntity/Policy references, and fallback to `App\Models\Client`.

---

## 2. Task 1 — Hard References Scan (Host Models)

Search patterns: `App\\Models\\`, `\\App\\Models\\`, `App\\` (host namespace), and class names `Client`, `Order`, `User`, `Package`, `MayaNetEsimProduct` as type/class references.

| Finding ID | Location | Snippet | Coupling type | Runtime dependency | Severity |
|------------|----------|---------|----------------|--------------------|----------|
| **HMR-001** | `src/Services/DocumentService.php:7-8` | `use App\Models\Client;` `use App\Models\Order;` | HARD MODEL REFERENCE | Host must have `App\Models\Client` and `App\Models\Order` | HIGH |
| **HMR-002** | `src/Services/DocumentService.php:406` | `public static function syncForClient(Client $client, ...)` | HARD MODEL REFERENCE | Host must have `App\Models\Client` | HIGH |
| **HMR-003** | `src/Services/DocumentService.php:439-440, 449-450, 458` | `$order = Order::where('client_id', ...)`; `$document->order_type = Order::class;` | HARD MODEL REFERENCE | Host must have `App\Models\Order` with `client_id`, `order_number` | HIGH |
| **HMR-004** | `src/Services/DocumentService.php:667` | `$q->where('subscriber_type', 'App\\Models\\User')` | HARD MODEL REFERENCE | Host must use `App\Models\User` as subscriber type | HIGH |
| **HMR-005** | `src/Services/DocumentService.php:669-670` | `->from('users')` in subquery | HOST TABLE NAME | Host must have `users` table with `sumit_customer_id` | HIGH |
| **HMR-006** | `src/Http/Controllers/PublicCheckoutController.php:578, 599` | `$modelClass = 'App\\Models\\Package';` | HARD MODEL REFERENCE | Host must have `App\Models\Package` | HIGH |
| **HMR-007** | `src/Http/Controllers/PublicCheckoutController.php:619, 640` | `$modelClass = 'App\\Models\\MayaNetEsimProduct';` | HARD MODEL REFERENCE | Host must have `App\Models\MayaNetEsimProduct` | HIGH |
| **HMR-008** | `src/Http/Controllers/PublicCheckoutController.php:184` | `$userModel = app('officeguy.customer_model') ?? \App\Models\Client::class;` | FALLBACK MODEL REFERENCE | If not configured, host must have `App\Models\Client` | MEDIUM |
| **HMR-009** | `src/Http/Controllers/Api/CheckEmailController.php:48` | `$userModel = app('officeguy.customer_model') ?? \App\Models\Client::class;` | FALLBACK MODEL REFERENCE | If not configured, host must have `App\Models\Client` | MEDIUM |
| **HMR-010** | `src/Services/CrmDataService.php:916, 925, 935, 947` | `$client = \App\Models\Client::where(...)->first();` (4 occurrences) | HARD MODEL REFERENCE | Host must have `App\Models\Client` with `sumit_customer_id`, `vat_number`, `id_number`, `email`/`client_email`, `phone`/`client_phone`/`mobile_phone` | HIGH |
| **HMR-011** | `src/Handlers/GenericFulfillmentHandler.php:45-46` | `if ($payable instanceof \App\Models\Order) { \App\Jobs\ProcessPaidOrderJob::dispatch(...); }` | HARD MODEL REFERENCE + HOST JOB | Host must have `App\Models\Order` and `App\Jobs\ProcessPaidOrderJob` | HIGH |
| **HMR-012** | `src/Policies/OfficeGuyTransactionPolicy.php:7-8` | `use App\Enums\UserRole;` `use App\Models\User;` | HARD MODEL REFERENCE | Host must have `App\Models\User` and `App\Enums\UserRole` with `isStaff()`, `isClient()`, `role`, `isAdmin()`, `isSuperAdmin()` | HIGH |
| **HMR-013** | `src/Policies/OfficeGuyTransactionPolicy.php:17-89` | All methods type-hint `User $user` | HARD MODEL REFERENCE | Same as HMR-012 | HIGH |
| **HMR-014** | `src/Models/CrmEntity.php:169, 179` | `return $this->belongsTo(\App\Models\User::class, 'owner_user_id');` and `assigned_to_user_id` | HARD MODEL REFERENCE | Host must have `App\Models\User` and tables with `owner_user_id`/`assigned_to_user_id` pointing to users | HIGH |
| **HMR-015** | `src/Models/CrmEntity.php:140` | `$customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;` | FALLBACK MODEL REFERENCE | If not configured, host must have `App\Models\Client` | MEDIUM |
| **HMR-016** | `src/Models/OfficeGuyTransaction.php:111` | `$customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;` | FALLBACK MODEL REFERENCE | If not configured, host must have `App\Models\Client` | MEDIUM |
| **HMR-017** | `src/Models/OfficeGuyTransaction.php:253` | Same as HMR-016 in `createFromApiResponse` | FALLBACK MODEL REFERENCE | Same | MEDIUM |
| **HMR-018** | `src/Models/OfficeGuyDocument.php:92` | `$customerModel = app('officeguy.customer_model') ?? \App\Models\Client::class;` | FALLBACK MODEL REFERENCE | Same | MEDIUM |
| **HMR-019** | `src/Models/SumitWebhook.php:73, 296` | Same fallback in `customer()` and `matchClientIdFromPayload` | FALLBACK MODEL REFERENCE | Same | MEDIUM |
| **HMR-020** | `config/officeguy.php:106` | `'customer_model_class' => env('OFFICEGUY_CUSTOMER_MODEL_CLASS', 'App\\Models\\Client')` | FALLBACK MODEL REFERENCE (default) | Default assumes host has `App\Models\Client` | LOW |
| **HMR-021** | `resources/lang/en/officeguy.php:230` | `'customer_model_class_help' => '... (e.g., App\\Models\\Client)'` | Documentation example only | None (help text) | LOW |
| **HMR-022** | `resources/lang/he/officeguy.php:236` | Same help text in Hebrew | Documentation example only | None | LOW |

---

## 3. Task 2 — Host Table / FK Coupling Scan (Migrations)

| Finding ID | Location | Snippet | Coupling type | Break mode | Severity |
|------------|----------|---------|----------------|------------|----------|
| **MFK-001** | `database/migrations/2025_12_02_015940_add_client_id_to_officeguy_crm_entities_table.php:18` | `->constrained('clients')` | HOST FK | Migration fails if `clients` table does not exist | HIGH |
| **MFK-002** | `database/migrations/2025_12_02_021547_add_client_id_to_officeguy_sumit_webhooks_table.php:18` | `->constrained('clients')` | HOST FK | Same | HIGH |
| **MFK-003** | `database/migrations/2025_12_02_020601_add_client_id_to_officeguy_crm_activities_table.php:18` | `->constrained('clients')` | HOST FK | Same | HIGH |
| **MFK-004** | `database/migrations/2025_01_01_000008_create_webhook_events_table.php:70` | `->on('subscriptions')` | AMBIGUOUS TABLE NAME | Package model uses `officeguy_subscriptions`; FK points to `subscriptions` — migration may fail or link to host table | HIGH |
| **MFK-005** | `database/migrations/2025_01_01_000009_create_sumit_incoming_webhooks_table.php:81` | `->on('subscriptions')` | AMBIGUOUS TABLE NAME | Same as MFK-004 | HIGH |

No direct references to `users` or `orders` as table names in migrations (only `clients` and `subscriptions`).

---

### Migration Classification Matrix (Task 2)

| Migration file | Classification | Notes |
|----------------|----------------|-------|
| `2024_01_01_000001_create_officeguy_transactions_table.php` | SELF-CONTAINED | Package table only |
| `2024_01_01_000002_create_officeguy_tokens_table.php` | SELF-CONTAINED | Package table only |
| `2024_01_01_000003_create_officeguy_documents_table.php` | SELF-CONTAINED | Package table only |
| `2025_01_01_000004_create_officeguy_settings_table.php` | SELF-CONTAINED | Package table only |
| `2025_01_01_000005_create_vendor_credentials_table.php` | SELF-CONTAINED | Package table only |
| `2025_01_01_000006_create_subscriptions_table.php` | SELF-CONTAINED | Creates `officeguy_subscriptions` |
| `2025_01_01_000007_add_donation_and_vendor_fields.php` | SELF-CONTAINED | Package tables only |
| `2025_01_01_000008_create_webhook_events_table.php` | HOST-COUPLED / AMBIGUOUS | FK `->on('subscriptions')` — should be `officeguy_subscriptions` (MFK-004) |
| `2025_01_01_000009_create_sumit_incoming_webhooks_table.php` | HOST-COUPLED / AMBIGUOUS | FK `->on('subscriptions')` — same (MFK-005) |
| `2025_12_02_015940_add_client_id_to_officeguy_crm_entities_table.php` | HOST-COUPLED | `constrained('clients')` (MFK-001) |
| `2025_12_02_020601_add_client_id_to_officeguy_crm_activities_table.php` | HOST-COUPLED | `constrained('clients')` (MFK-003) |
| `2025_12_02_021547_add_client_id_to_officeguy_sumit_webhooks_table.php` | HOST-COUPLED | `constrained('clients')` (MFK-002) |
| All other migrations in `database/migrations/` | SELF-CONTAINED or CONDITIONALLY SAFE | No host table/FK references; some use `Schema::hasTable`/`hasColumn` where applicable |

---

## 4. Task 3 — Polymorphic / Subscriber Assumption Scan

| Finding ID | Location | Snippet | Assumption type | Severity |
|------------|----------|---------|-----------------|----------|
| **POLY-001** | `src/Services/DocumentService.php:666-671` | `$q->where('subscriber_type', 'App\\Models\\User')` and `->whereIn('subscriber_id', function ($subQ) { ... ->from('users')->where('sumit_customer_id', ...) })` | MORPH TYPE HARDCODE + TABLE HARDCODE | HIGH |
| **POLY-002** | `src/Services/SubscriptionService.php:58-59, 498-499` | `'subscriber_type' => $subscriber::class`, `'subscriber_id' => $subscriber->getKey()` | Properly dynamic (no hardcode) | — |
| **POLY-003** | `src/Models/Subscription.php:40-41` | `'subscriber_type', 'subscriber_id'` in `$fillable` | Schema only; no assumption | — |

Only **POLY-001** is a coupling finding: DocumentService assumes subscriber type is `App\Models\User` and that subscriber IDs come from table `users` with column `sumit_customer_id`.

---

## 5. Task 4 — Config & Container Contract Map

### What is configurable today

| Contract | Where defined | Where resolved | Bypass / fallback |
|----------|----------------|----------------|-------------------|
| **Customer model** | `config/officeguy.php`: `customer_model_class` (env), `models.customer` (null) | `OfficeGuyServiceProvider::resolveCustomerModel()`: 1) DB `officeguy_settings.customer_model_class`, 2) `config('officeguy.models.customer')`, 3) `config('officeguy.customer_model_class')`, 4) returns `null` | Bypass: code uses `app('officeguy.customer_model') ?? \App\Models\Client::class` in 6 models + 2 controllers, so when resolved value is null, host `App\Models\Client` is required. Config default `'App\\Models\\Client'` in `config/officeguy.php:106` also assumes host. |
| **Order model** | `config/officeguy.php`: `models.order` => null, `repositories.order` => null | Not resolved in provider; no container binding for order model | N/A — order/payable is passed at runtime; DocumentService and GenericFulfillmentHandler hardcode `Order`/`Package`/`MayaNetEsimProduct`. |
| **Repositories** | `config/officeguy.php`: `repositories.customer`, `repositories.order` => null | Not wired in provider | N/A |

### Where the contract is bypassed

- **Fallback to `App\Models\Client`:** Whenever `app('officeguy.customer_model')` is null, call sites use `?? \App\Models\Client::class` (see HMR-008, HMR-009, HMR-015, HMR-016, HMR-017, HMR-018, HMR-019). So the “configurable customer model” contract is bypassed by hardcoded fallback in production code.
- **Direct host types:** DocumentService uses `Client`, `Order`, and `App\Models\User`/`users` (HMR-001–005); PublicCheckoutController uses `Package`, `MayaNetEsimProduct` (HMR-006–007); GenericFulfillmentHandler uses `App\Models\Order` and `App\Jobs\ProcessPaidOrderJob` (HMR-011); CrmDataService uses `\App\Models\Client` (HMR-010); CrmEntity uses `\App\Models\User` (HMR-014); OfficeGuyTransactionPolicy uses `App\Models\User` and `App\Enums\UserRole` (HMR-012, HMR-013). None of these go through config or container.

---

## 6. Must-Fix List (Max 7)

Only the highest-severity couplings that directly break portability or host independence:

1. **Migrations: host FK to `clients`** — **MFK-001, MFK-002, MFK-003.** Migrations require host `clients` table. Fix: make customer table name (and optionally FK) configurable or document that package expects a `clients` table; or use a package-scoped link (e.g. generic `customer_id` + config for table name).
2. **Migrations: ambiguous `subscriptions` table** — **MFK-004, MFK-005.** FKs use `->on('subscriptions')` while package model uses `officeguy_subscriptions`. Fix: use `->on('officeguy_subscriptions')` in both migrations so FKs target the package table.
3. **DocumentService: host models and table** — **HMR-001, HMR-002, HMR-003, HMR-004, HMR-005, POLY-001.** Hardcoded `Client`, `Order`, `App\Models\User`, and `users` table. Fix: use config/container for customer and order model; subscriber type and table for subscription-document matching must be configurable or derived from subscription morph.
4. **PublicCheckoutController: hardcoded Package / MayaNetEsimProduct** — **HMR-006, HMR-007.** Fix: resolve payable model (e.g. route or config) instead of hardcoding `App\Models\Package` and `App\Models\MayaNetEsimProduct`.
5. **GenericFulfillmentHandler: Order + host job** — **HMR-011.** Fix: do not type-check `\App\Models\Order`; dispatch via configurable job class or event so host registers handler/job.
6. **CrmDataService: direct Client usage** — **HMR-010.** Fix: use `app('officeguy.customer_model')` (no fallback to `App\Models\Client`) or inject customer model/resolver.
7. **CrmEntity & OfficeGuyTransactionPolicy: User / UserRole** — **HMR-012, HMR-013, HMR-014.** Fix: make “staff/user” model and role enum configurable or interface-based so the package does not depend on `App\Models\User` and `App\Enums\UserRole`.

**Not in must-fix (but recommended later):** Fallback `?? \App\Models\Client::class` at all call sites (HMR-008, HMR-009, HMR-015–HMR-019) — remove or replace with configurable default; config default in `officeguy.php` (HMR-020) and lang help text (HMR-021, HMR-022) are documentation/low impact.

---

**Phase 1 completion:** All findings above include `@path:line` and snippet; must-fix list references only Finding IDs from this phase. No code was modified; no migrations or tests were run.
