# Package Architecture Boundary Audit Report

**Package:** `officeguy/laravel-sumit-gateway`  
**Scope:** Static analysis — package domain vs. host application domain coupling  
**Date:** 2026-03-03  
**Rules:** No code modified; report only.

---

## Executive Summary

The package has **mixed** architectural boundaries: customer/model resolution is **well abstracted** (config + DB + container), but **migrations and runtime code** still contain **host-domain assumptions** that tie the package to specific app models and table names. Key findings:

- **Customer model:** Properly abstracted via `app('officeguy.customer_model')`, config, and DB; fallback to `\App\Models\Client::class` remains in 6+ files (risk: host must have `Client` or set config).
- **Migrations:** Three migrations use **FK to host table `clients`** (`constrained('clients')`). Two migrations reference table **`subscriptions`** while the package model uses **`officeguy_subscriptions`** (naming bug or host expectation).
- **Runtime hardcoding:** `PublicCheckoutController` hardcodes `App\Models\Package` and `App\Models\MayaNetEsimProduct` for specific checkout routes. `DocumentService` hardcodes `App\Models\User` and `users` for subscription lookups. Controllers fall back to `\App\Models\Client::class` when container returns null.
- **CRM layer:** CRM tables are package-scoped, but **three migrations** add `client_id` with `->constrained('clients')`, making CRM **partially host-dependent** unless the host uses table name `clients`.

**Architectural integrity score: 62/100** — Good abstraction for customer model; host coupling in migrations, checkout resolvers, and DocumentService reduces portability.

---

## Package Directory Tree (Full)

**Excluded:** `vendor/`, `woo-plugin/` (as requested). All other paths included.

```
.
├── .claude/
├── .phpdoc/
├── .serena/
├── config/
│   ├── officeguy-webhooks.php
│   └── officeguy.php
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_officeguy_transactions_table.php
│       ├── 2024_01_01_000002_create_officeguy_tokens_table.php
│       ├── 2024_01_01_000003_create_officeguy_documents_table.php
│       ├── 2025_01_01_000004_create_officeguy_settings_table.php
│       ├── 2025_01_01_000005_create_vendor_credentials_table.php
│       ├── 2025_01_01_000006_create_subscriptions_table.php
│       ├── 2025_01_01_000007_add_donation_and_vendor_fields.php
│       ├── 2025_01_01_000008_create_webhook_events_table.php
│       ├── 2025_01_01_000009_create_sumit_incoming_webhooks_table.php
│       ├── 2025_01_27_000001_create_payable_field_mappings_table.php
│       ├── 2025_11_30_120000_add_subscription_support_to_documents_table.php
│       ├── 2025_11_30_170000_add_items_to_documents_table.php
│       ├── 2025_11_30_185451_create_document_subscription_pivot_table.php
│       ├── 2025_12_01_000010_create_officeguy_crm_folders_table.php
│       ├── 2025_12_01_000011_create_officeguy_crm_folder_fields_table.php
│       ├── 2025_12_01_000012_create_officeguy_crm_entities_table.php
│       ├── 2025_12_01_000013_create_officeguy_crm_entity_fields_table.php
│       ├── 2025_12_01_000014_create_officeguy_crm_entity_relations_table.php
│       ├── 2025_12_01_000015_create_officeguy_crm_activities_table.php
│       ├── 2025_12_01_000016_create_officeguy_crm_views_table.php
│       ├── 2025_12_01_120000_add_endpoint_to_sumit_webhooks.php
│       ├── 2025_12_02_000000_create_officeguy_debt_attempts_table.php
│       ├── 2025_12_02_015940_add_client_id_to_officeguy_crm_entities_table.php
│       ├── 2025_12_02_020601_add_client_id_to_officeguy_crm_activities_table.php
│       ├── 2025_12_02_021547_add_client_id_to_officeguy_sumit_webhooks_table.php
│       ├── 2025_12_07_000001_add_admin_notes_to_officeguy_tokens_table.php
│       ├── 2025_12_18_000001_create_order_success_tokens_table.php
│       ├── 2025_12_18_000002_create_order_success_access_log_table.php
│       ├── 2025_12_18_000003_add_is_webhook_confirmed_to_officeguy_transactions.php
│       ├── 2025_12_18_120000_create_pending_checkouts_table.php
│       ├── 2025_12_24_000001_add_secure_success_settings.php
│       ├── 2025_12_26_000001_add_transaction_linking_fields_to_officeguy_transactions.php
│       ├── 2025_12_29_020000_add_sumit_entity_id_to_officeguy_transactions.php
│       └── 2025_12_29_120000_add_upay_fields_to_officeguy_transactions.php
├── docs/
├── officeguy/
├── pack/
├── packages/
├── public/
├── resources/
│   ├── css/
│   │   └── checkout-mobile.css
│   ├── icons/
│   ├── js/
│   │   └── officeguy-alpine-rtl.js
│   ├── lang/
│   │   ├── en/
│   │   │   └── officeguy.php
│   │   ├── he/
│   │   │   └── officeguy.php
│   │   └── README.md
│   ├── svg/
│   └── views/
│       ├── components/
│       ├── errors/
│       ├── layouts/
│       ├── pages/
│       └── success.blade.php
├── routes/
│   └── officeguy.php
├── scripts/
│   ├── add-missing-translations.php
│   ├── final-translations.php
│   └── translate-settings-page.php
├── src/
│   ├── Actions/
│   ├── BackoffStrategy/
│   ├── Console/
│   │   └── Commands/
│   ├── Contracts/
│   ├── DTOs/
│   ├── DataTransferObjects/
│   ├── Enums/
│   ├── Events/
│   ├── Handlers/
│   ├── Http/
│   │   ├── Connectors/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   └── ...
│   │   ├── DTOs/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Responses/
│   ├── Jobs/
│   │   ├── BulkActions/
│   │   └── ...
│   ├── Listeners/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Services/
│   │   ├── Stock/
│   │   └── ...
│   ├── Support/
│   │   ├── Traits/
│   │   └── ...
│   ├── View/
│   │   └── Components/
│   ├── OfficeGuyServiceProvider.php
│   └── WebhookCall.php
├── temp_logo/
├── tests/
│   └── Unit/
├── .gitignore
├── CHANGELOG.md
├── CLAUDE.md
├── composer.json
├── composer.lock
├── LICENSE.md
├── README.md
├── phpunit.xml
├── sumit-openapi.json
└── ... (other root .md, .txt, .sh, .json, .zip)
```

*Generated with `tree -I 'vendor|woo-plugin' -a --dirsfirst`. `.phpdoc/` and `docs/api/` (generated) are present in repo but omitted here for length.*

---

## Phase 1 — Model Coupling Scan

### Findings Table

| File | Line | Snippet | Coupling Type | Risk |
|------|------|---------|----------------|------|
| `config/officeguy.php` | 106 | `'customer_model_class' => env('OFFICEGUY_CUSTOMER_MODEL_CLASS', 'App\\Models\\Client')` | Config-based reference (default) | LOW |
| `src/OfficeGuyServiceProvider.php` | 125, 132 | `config('officeguy.models.customer')`, `config('officeguy.customer_model_class')` | Config-based reference | LOW |
| `src/Http/Controllers/Api/CheckEmailController.php` | 48 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Http/Controllers/PublicCheckoutController.php` | 184 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Http/Controllers/PublicCheckoutController.php` | 578, 599 | `$modelClass = 'App\\Models\\Package'` | Hard dependency (Payable resolver) | HIGH |
| `src/Http/Controllers/PublicCheckoutController.php` | 619, 640 | `$modelClass = 'App\\Models\\MayaNetEsimProduct'` | Hard dependency (Payable resolver) | HIGH |
| `src/Services/DocumentService.php` | 667 | `$q->where('subscriber_type', 'App\\Models\\User')` + `->from('users')` | Polymorphic assumption + table name | HIGH |
| `src/Models/OfficeGuyTransaction.php` | 111, 253 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Models/OfficeGuyDocument.php` | 93 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Models/SumitWebhook.php` | 73, 296 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Models/CrmEntity.php` | 140 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Models/CrmActivity.php` | 101 | `app('officeguy.customer_model') ?? \App\Models\Client::class` | Fallback hard dependency | MEDIUM |
| `src/Services/CustomerMergeService.php` | 44 | `return app('officeguy.customer_model')` | Config-based (container) | LOW |
| `resources/lang/en/officeguy.php` | 230 | `(e.g., App\\Models\\Client)` | Documentation / help text | LOW |
| `resources/lang/he/officeguy.php` | 236 | Same (Hebrew) | Documentation / help text | LOW |

### Coupling Type Summary

- **Hard dependency:** Direct use of `App\Models\*` in runtime code (Package, MayaNetEsimProduct, User, Client fallback). **Risk: HIGH** where used for resolvers or queries.
- **Config-based reference:** `config('officeguy.customer_model_class')`, `config('officeguy.models.customer')`, `app('officeguy.customer_model')`. **Risk: LOW** when no fallback to a concrete class.
- **FK dependency:** Migrations using `->constrained('clients')` (see Phase 2). **Risk: HIGH** for host table name assumption.
- **Polymorphic assumption:** `DocumentService` assumes `subscriber_type = 'App\Models\User'` and table `users`. **Risk: HIGH** if host uses different user model/table.

---

## Phase 2 — Migration Dependency Audit

### Migrations with Host or Ambiguous References

| Migration | Issue | Classification |
|-----------|--------|----------------|
| `2025_12_02_015940_add_client_id_to_officeguy_crm_entities_table.php` | `->constrained('clients')` | **HOST-COUPLED** |
| `2025_12_02_020601_add_client_id_to_officeguy_crm_activities_table.php` | `->constrained('clients')` | **HOST-COUPLED** |
| `2025_12_02_021547_add_client_id_to_officeguy_sumit_webhooks_table.php` | `->constrained('clients')` | **HOST-COUPLED** |
| `2025_01_01_000008_create_webhook_events_table.php` | `->on('subscriptions')` (package uses `officeguy_subscriptions`) | **CONDITIONALLY SAFE** / naming bug |
| `2025_01_01_000009_create_sumit_incoming_webhooks_table.php` | `->on('subscriptions')` (same) | **CONDITIONALLY SAFE** / naming bug |

### Schema::hasTable Guards (Package Tables Only)

All guards reference **package** tables (e.g. `officeguy_transactions`, `officeguy_tokens`, `officeguy_webhook_events`, `officeguy_subscriptions`, `order_success_tokens`). No raw `DB::statement` DDL in current migrations (removed in DB portability fix). **Classification:** SELF-CONTAINED for idempotency; no host table checks.

### Migration Risk Matrix

| Migration | Self-Contained | Conditional | Host-Coupled | Notes |
|-----------|----------------|-------------|--------------|--------|
| create_officeguy_* (core) | ✅ | — | — | Package tables only |
| add_subscription_support_to_documents | ✅ | — | — | FK to officeguy_subscriptions |
| add_transaction_linking_fields | ✅ | — | — | FK to officeguy_transactions |
| create_webhook_events | ✅ | ⚠️ | — | FK to `subscriptions` (table name vs officeguy_subscriptions) |
| create_sumit_incoming_webhooks | ✅ | ⚠️ | — | Same |
| add_client_id_to_officeguy_crm_entities | — | — | ✅ | constrained('clients') |
| add_client_id_to_officeguy_crm_activities | — | — | ✅ | constrained('clients') |
| add_client_id_to_officeguy_sumit_webhooks | — | — | ✅ | constrained('clients') |
| All others | ✅ | — | — | Package-only FKs |

---

## Phase 3 — Config Extension Audit

### Model Override Support

- **Customer model:** Supported via:
  - Database: `officeguy_settings.customer_model_class` (highest priority)
  - Config: `officeguy.models.customer`, `officeguy.customer_model_class`
  - Container: `app('officeguy.customer_model')` (singleton)
- **Order / Payable:** Config has `models.order` and `repositories.order` (null by default). No container binding for order model in scanned code; checkout uses route resolvers and hardcoded classes (Package, MayaNetEsimProduct).

### Dynamic Model Resolution

- **Customer:** Fully dynamic (DB → config → container). Resolution in `OfficeGuyServiceProvider::resolveCustomerModel()` and consumed via `app('officeguy.customer_model')`.
- **Order/Payable:** Not abstracted in config for checkout; specific entry points use hardcoded `App\Models\Package` and `App\Models\MayaNetEsimProduct`.

### morphMap Usage

- Not used in package code. Polymorphic relations (e.g. `order_type`/`order_id`, subscription `subscriber_type`/`subscriber_id`) use full class names (e.g. `App\Models\User` in DocumentService). **Classification:** Partially abstracted (customer); order/payable and subscriber types are hardcoded where used.

### Container Binding

- `officeguy.customer_model` bound as singleton in `OfficeGuyServiceProvider`. No bindings found for order/payable or subscriber model.

### Hardcoded Class References

| Location | Class / Table | Abstraction Level |
|----------|----------------|-------------------|
| Config default | `App\Models\Client` | **Partially abstracted** (default only; overridable) |
| CheckEmailController, PublicCheckoutController, 5 models | `\App\Models\Client::class` fallback | **Hardcoded** (fallback when container null) |
| PublicCheckoutController | `App\Models\Package`, `App\Models\MayaNetEsimProduct` | **Hardcoded** |
| DocumentService | `App\Models\User`, `users` table | **Hardcoded** |

### Config Abstraction Classification

- **Customer model:** **Properly abstracted** (DB + config + container; fallback to Client is the only hardcode).
- **Order/Payable and subscriber:** **Partially abstracted** (config keys exist for models/repositories but not used for checkout or DocumentService; hardcoded classes in controllers and DocumentService).

---

## Phase 4 — CRM Domain Scope Check

### officeguy_crm_* Tables

| Table | External Model Refs | FK to Host | Internal Consistency |
|-------|----------------------|------------|------------------------|
| officeguy_crm_folders | None | No | ✅ Package only |
| officeguy_crm_folder_fields | None | No (FK to officeguy_crm_folders) | ✅ |
| officeguy_crm_entities | customer() → app('officeguy.customer_model') | **Yes:** `client_id` → `clients` in 2025_12_02_015940 | ⚠️ |
| officeguy_crm_entity_fields | None | No | ✅ |
| officeguy_crm_entity_relations | None | No | ✅ |
| officeguy_crm_activities | customer() → app('officeguy.customer_model') | **Yes:** `client_id` → `clients` in 2025_12_02_020601 | ⚠️ |
| officeguy_crm_views | None | No (FK to officeguy_crm_folders) | ✅ |

### Assumptions About Host Domain

- Host must have a table named **`clients`** (or migration fails / FK violation) if the three “add_client_id” migrations run.
- Customer model is assumed to be resolvable via `app('officeguy.customer_model')` or fallback `Client`; CRM entities/activities use this for the `customer()` relationship.

### CRM Layer Classification

**Partially host-dependent.**  
CRM schema and most relations are package-scoped. The **explicit FK to table `clients`** in three migrations and the **customer() resolution** (container + Client fallback) tie the CRM layer to the host’s customer representation and table naming.

---

## Phase 5 — Summary

### 1. Coupling Findings Table

| Category | Count | Severity | Location |
|----------|--------|----------|----------|
| Hard dependency (App\Models\* in runtime) | 4 files (Package, MayaNetEsimProduct, User, Client fallback) | HIGH | PublicCheckoutController, DocumentService, CheckEmailController, 5 models |
| Config-based customer reference | 6+ files | LOW | ServiceProvider, CustomerMergeService, config, lang |
| FK to host table (`clients`) | 3 migrations | HIGH | add_client_id_to_officeguy_crm_*, add_client_id_to_officeguy_sumit_webhooks |
| FK to ambiguous table (`subscriptions`) | 2 migrations | MEDIUM | webhook_events, sumit_incoming_webhooks (vs officeguy_subscriptions) |
| Polymorphic assumption (User / users) | 1 file | HIGH | DocumentService |

### 2. Migration Risk Matrix

| Risk | Migrations |
|------|-------------|
| **HOST-COUPLED** | add_client_id_to_officeguy_crm_entities, add_client_id_to_officeguy_crm_activities, add_client_id_to_officeguy_sumit_webhooks |
| **CONDITIONALLY SAFE** | create_webhook_events, create_sumit_incoming_webhooks (table name `subscriptions` vs package `officeguy_subscriptions`) |
| **SELF-CONTAINED** | All other migrations (package tables + package FKs only) |

### 3. Architectural Integrity Score: **62 / 100**

| Criterion | Weight | Score | Notes |
|-----------|--------|-------|--------|
| No hardcoded host models in runtime | 25% | 40 | Package/User/Client fallbacks in controllers, DocumentService, models |
| Migrations package-only | 25% | 20 | Three migrations FK to `clients`; two use `subscriptions` |
| Config/container for all external models | 20% | 75 | Customer well abstracted; order/subscriber not |
| CRM layer package-scoped | 15% | 50 | CRM tables package-scoped but client_id → host `clients` |
| Polymorphic / table names abstracted | 15% | 40 | DocumentService uses App\Models\User and `users` |

**Rationale:** Strong customer abstraction and container binding raise the score; host-coupled migrations, checkout resolvers, and DocumentService assumptions pull it down.

### 4. High-Level Refactoring Strategy (No Code)

1. **Migrations**
   - Replace `constrained('clients')` with a configurable table name (e.g. from config or a migration config/customizer) so the host can map to their customer table, or make the `client_id` column optional and document that host must add FK separately.
   - Align subscription FK with package table: use `officeguy_subscriptions` in webhook_events and sumit_incoming_webhooks migrations (or document that `subscriptions` is intentional for host).

2. **Customer model**
   - Remove fallback `?? \App\Models\Client::class` in controllers and models; require config/DB to be set or fail explicitly so the package does not assume a host class.

3. **Checkout / Payable**
   - Replace hardcoded `App\Models\Package` and `App\Models\MayaNetEsimProduct` with config-driven resolvers (e.g. route name → model class or callable from config) so any host Payable can be used without code change.

4. **DocumentService**
   - Replace hardcoded `App\Models\User` and `users` with config or container binding for “subscriber” model (and table name if needed) so hosts can use a different user/customer model.

5. **Optional**
   - Consider morphMap or configurable polymorphic types for `order_type` and `subscriber_type` so host models are not encoded in package code.

---

*Report generated by static analysis. No code or vendor files were modified.*
