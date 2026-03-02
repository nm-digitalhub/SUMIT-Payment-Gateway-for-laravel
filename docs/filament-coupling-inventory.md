# Filament Coupling Inventory

**Purpose:** Map all Filament coupling points for decoupling core package from Filament.  
**Branch:** `chore/filament-decouple`  
**Date:** 2026-02-27  

---

## Classification

- **A) CORE** — Keep in core; no Filament code (comments/docblocks referencing "Admin Panel" or "Filament" are documentation-only).
- **B) FILAMENT UI** — Move to adapter package `officeguy/laravel-sumit-gateway-filament`.
- **C) MIXED** — Refactor: extract Filament-specific behavior to adapter or make configurable; leave pure core in main package.

---

## 1. composer.json

| Location     | Line(s) | Category | Notes |
|-------------|---------|----------|--------|
| composer.json | 3, 12–13, 31–32 | C) MIXED | Remove `filament/filament`, `bezhansalleh/filament-plugin-essentials` from require. Fix typo `optimize-autuloader` → `optimize-autoloader`. Set `minimum-stability`: `stable`. |

---

## 2. Service provider (Filament registrations)

| Location | Line(s) | Category | Notes |
|----------|---------|----------|--------|
| src/OfficeGuyServiceProvider.php | 269, 304, 334–347 | C) MIXED | **registerLivewireComponents()**: Registers `PayableMappingsTableWidget` with Livewire for Filament. Move to adapter; core must not register any Filament widget. |
| src/OfficeGuyServiceProvider.php | 352–384 | C) MIXED | **registerFilamentClusters()**: Uses `Filament::serving()`, `getPanel('admin')`, `getPanel('client')`, registers Clusters. Not called in `boot()` currently (dead code). Remove from core entirely; adapter will register clusters. |

---

## 3. Controllers (Filament / route references)

| File | Line(s) | Category | Notes |
|------|---------|----------|--------|
| src/Http/Controllers/PublicCheckoutController.php | 75–76 | C) MIXED | `class_exists(\Filament\Facades\Filament::class)` and `Filament::auth()->user()` fallback. **Refactor:** Use only `auth()->user()` in core; remove Filament. Adapter or app can resolve user elsewhere if needed. |
| src/Http/Controllers/Api/CheckEmailController.php | 76–77 | C) MIXED | `route('filament.client.auth.login', ...)`. **Refactor:** Make login URL configurable (e.g. `config('officeguy.login_url')` or callback); adapter provides Filament route. |

---

## 4. Notifications (Filament admin routes)

| File | Line(s) | Category | Notes |
|------|---------|----------|--------|
| src/Notifications/PaymentCompletedNotification.php | 62 | C) MIXED | `route('filament.admin.resources.office-guy-transactions.view', ...)`. **Refactor:** Admin view URL via config or callback so adapter can supply Filament route. |
| src/Notifications/DocumentCreatedNotification.php | 57 | C) MIXED | `route('filament.admin.resources.documents.view', ...)`. Same as above. |
| src/Notifications/SubscriptionCreatedNotification.php | 59 | C) MIXED | `route('filament.admin.resources.subscriptions.view', ...)`. Same as above. |

---

## 5. Jobs (documentation only)

| File | Category | Notes |
|------|----------|--------|
| src/Jobs/BulkActions/BulkTokenSyncJob.php | A) CORE | Docblocks reference "Filament v5" and "Used in TokenResource"; no Filament imports. Keep in core. |
| src/Jobs/BulkActions/BulkSubscriptionChargeJob.php | A) CORE | Same. |
| src/Jobs/BulkActions/BulkPayableMappingDeactivateJob.php | A) CORE | Same. |
| src/Jobs/BulkActions/BulkPayableMappingActivateJob.php | A) CORE | Same. |
| src/Jobs/BulkActions/BulkDocumentEmailJob.php | A) CORE | Same. |
| src/Jobs/BulkActions/BulkSubscriptionCancelJob.php | A) CORE | Same. |
| src/Jobs/BulkActions/BaseBulkActionJob.php | A) CORE | Same. |

---

## 6. Services / Support / Models (comments or non-Filament)

| File | Category | Notes |
|------|----------|--------|
| src/Services/PaymentService.php | A) CORE | "Admin Panel" in comment only; `HasNextPage` is API response key, not Filament. |
| src/Services/SettingsService.php | A) CORE | "for Filament form" in comment; method is generic. |
| src/Services/PackageVersionService.php | A) CORE | "About pages (Filament)" in comment only. |
| src/Services/CustomerMergeService.php | A) CORE | "Admin Panel editable" in comment. |
| src/Services/WebhookService.php | A) CORE | "Admin Panel" in comment. |
| src/Support/Traits/HasPayableType.php | A) CORE | "Filament color name" in docblock; returns string. |
| src/Support/RouteConfig.php | A) CORE | "Admin Panel" in comment. |
| src/Support/ModelPayableWrapper.php | A) CORE | "Admin Panel settings" in comment. |
| src/Models/OfficeGuyTransaction.php | A) CORE | "Admin Panel editable" in comment. |
| src/Models/CrmEntity.php | A) CORE | Same. |
| src/Enums/PayableType.php | A) CORE | "Filament color name" in docblock. |
| src/Listeners/WebhookEventListener.php | A) CORE | "Admin Panel" in comment. |

---

## 7. Entire src/Filament/ tree — B) FILAMENT UI

All of the following move to adapter package `officeguy/laravel-sumit-gateway-filament`:

| Path | Category | Notes |
|------|----------|--------|
| src/Filament/Clusters/SumitGateway.php | B) FILAMENT UI | |
| src/Filament/Clusters/SumitClient.php | B) FILAMENT UI | |
| src/Filament/Client/ClientPanelProvider.php | B) FILAMENT UI | Panel provider. |
| src/Filament/Client/OfficeGuyClientPlugin.php | B) FILAMENT UI | |
| src/Filament/OfficeGuyPlugin.php | B) FILAMENT UI | |
| src/Filament/OfficeGuyClientPlugin.php | B) FILAMENT UI | |
| src/Filament/Pages/AboutPage.php | B) FILAMENT UI | |
| src/Filament/Pages/OfficeGuySettings.php | B) FILAMENT UI | |
| src/Filament/Pages/ClientDashboard.php | B) FILAMENT UI | |
| src/Filament/Widgets/PayableMappingsTableWidget.php | B) FILAMENT UI | |
| src/Filament/Widgets/ClientStatsOverview.php | B) FILAMENT UI | |
| src/Filament/Actions/CreatePayableMappingAction.php | B) FILAMENT UI | |
| src/Filament/RelationManagers/InvoicesRelationManager.php | B) FILAMENT UI | |
| src/Filament/Resources/* (all Resources, Pages, Schemas, Tables, RelationManagers) | B) FILAMENT UI | SubscriptionResource, TokenResource, DocumentResource, TransactionResource, SumitWebhookResource, WebhookEventResource, VendorCredentialResource, CrmFolderResource, CrmEntityResource, CrmActivityResource, and all sub-pages/schemas/tables. |
| src/Filament/Client/Resources/* (all Client resources and pages) | B) FILAMENT UI | ClientTransactionResource, ClientPaymentMethodResource, ClientDocumentResource, ClientSubscriptionResource, ClientSumitWebhookResource, ClientWebhookEventResource, etc. |
| src/Filament/Resources/WebhookEventResource/Widgets/WebhookStatsOverview.php | B) FILAMENT UI | |
| src/Filament/Resources/SumitWebhookResource/Widgets/SumitWebhookStatsOverview.php | B) FILAMENT UI | |
| src/Filament/README.md, src/Filament/Resources/README_CRM.md | B) FILAMENT UI | Documentation. |

---

## 8. Provider audit summary

**OfficeGuyServiceProvider:**

| Item | Action |
|------|--------|
| register() | Keep; no Filament. |
| boot() | Keep config, routes, migrations, views, lang, commands, events, Blade component. **Remove:** call to `registerLivewireComponents()`. **Remove or leave dead:** `registerFilamentClusters()` (currently not called). |
| registerLivewireComponents() | **Remove from core.** Adapter will register Livewire components for Filament widgets. |
| registerFilamentClusters() | **Remove from core.** Adapter will register clusters. |

---

## 9. Summary counts

| Category | Count | Action |
|----------|--------|--------|
| A) CORE | All non-Filament files + Jobs, Services, Support, Models, Enums, Listeners, Handlers, Http (after refactor) | Keep in core; no Filament code. |
| B) FILAMENT UI | Entire `src/Filament/` (84+ PHP files + READMEs) | Move to adapter package. |
| C) MIXED | composer.json, OfficeGuyServiceProvider, PublicCheckoutController, CheckEmailController, 3 Notifications | Refactor: remove Filament deps / optional use; make URLs configurable or adapter-provided. |

---

## 10. Next steps (execution order)

1. **PHASE 2:** Define package structure (monorepo with `packages/core` and `packages/filament`, or two repos).

   **STRICT VALIDATION RULE**  
   Manual inspection of partial file sections is NOT sufficient.  
   Checking the "first 100 lines" of a file is NOT acceptable.  
   All validation must be based on full recursive search results (grep across entire files).  
   A file is considered clean only if the grep result for that file is zero across its full content.  
   Any Filament reference found anywhere in `src/`, `resources/views/`, or `config/` (excluding `packages/filament`) is considered a failure.

2. **PHASE 3:** Core composer: remove `filament/filament`, `bezhansalleh/filament-plugin-essentials`; fix typo; set minimum-stability stable; `composer validate` and `composer install --no-dev`.  
3. **PHASE 4:**  
   - Move `src/Filament/*` to adapter.  
   - In core: remove `registerLivewireComponents()` and `registerFilamentClusters()` from provider; remove Filament usage from PublicCheckoutController, CheckEmailController; make notification action URLs configurable.  
   - Ensure core has zero Filament references and zero Filament in composer.json.

---

## 10b. PHASE 6 — DETERMINISTIC RUNTIME REQUIREMENT

Validation must include:

1. A clean `composer install --no-dev` in the core package.
2. A fresh Laravel test app where ONLY the core package is installed.
3. No reliance on previously cached vendor directories.
4. No reliance on manual visual inspection.

If the core package cannot boot and function without Filament installed, the split is not complete.

---

## 11. Execution summary (completed 2026-02-27)

| Phase | Done |
|-------|------|
| **PHASE 0** | Branch `chore/filament-decouple` created; tag `pre-filament-decouple-*` created. |
| **PHASE 1** | `docs/filament-coupling-inventory.md` created with full classification. |
| **PHASE 2** | Adapter package at `packages/filament/` with `composer.json` for `officeguy/laravel-sumit-gateway-filament`. Core remains at repo root. |
| **PHASE 3** | Core `composer.json`: Filament deps removed, `optimize-autoloader` typo fixed, `minimum-stability` set to `stable`. |
| **PHASE 4** | Core: `registerLivewireComponents()` and `registerFilamentClusters()` removed from `OfficeGuyServiceProvider`. `PublicCheckoutController` uses only `auth()->user()`. `CheckEmailController` uses `config('officeguy.routes.client_login_route', 'login')`. Notifications use `config('officeguy.notification_routes.*')`. `config/officeguy.php` extended with `routes.client_login_route` and `notification_routes`. Entire `src/Filament/` moved to `packages/filament/src/`. Adapter `SumitGatewayFilamentServiceProvider` added (Livewire + clusters); `ClientPanelProvider` registered in adapter `composer.json`. Core `composer update --no-dev` runs successfully without Filament. |
