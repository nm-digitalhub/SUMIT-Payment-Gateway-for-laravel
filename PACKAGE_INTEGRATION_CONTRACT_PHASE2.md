# Package Integration Contract — Phase 2 (Design Only, No Implementation)

**Objective:** Design a minimal, strict, and unified Integration Contract that removes all hard dependencies on host models and tables, without adding architectural layers or rewriting the package.

**Inputs:** Phase 1 findings (`PACKAGE_COUPLING_INVENTORY_PHASE1.md`), existing contracts (`Payable`, `HasSumitCustomer`).

**Rule:** Do NOT modify code. Do NOT refactor. Design document only.

---

## SECTION 1 — Define External Concepts

Based on Phase 1, the package’s external surface is reduced to **exactly three integration concepts**. No extra interfaces are introduced here; behavior and identifiers are specified so the host can satisfy them with existing or minimal types.

---

### 1. Customer

**Definition:** The entity that is the SUMIT customer — the one who owns transactions, documents, tokens, and (optionally) CRM linkage. This is the “who” for payment and document operations.

| Aspect | Contract |
|--------|----------|
| **Required behavior** | Implement existing `HasSumitCustomer`: `getSumitCustomerId()`, `getSumitCustomerEmail()`, `getSumitCustomerName()`, `getSumitCustomerPhone()`, `getSumitCustomerBusinessId()`. Package may also need: primary key (`getKey()`), `email` (or equivalent) for lookups, and optionally `sumit_customer_id` as attribute for queries. |
| **Required identifiers** | At least: stable `id` (PK), `sumit_customer_id` (nullable int for SUMIT CustomerID). For CrmDataService matching: at least one of `vat_number`/`id_number`, `email` or `client_email`, and one of `phone`/`client_phone`/`mobile_phone` (or equivalent attribute names resolved via config/mapping). |
| **Eloquent-based?** | Yes. Package uses Eloquent relations (`belongsTo`), `where()`, and `getKey()`. The customer must be an Eloquent model. |
| **Polymorphism** | No. A single customer model class is configured for the app. All package tables that reference “customer” use one foreign key type (e.g. `client_id` or configurable column name) pointing to that model’s table. |

**Validation:** Matches Phase 1 “Customer” and existing `officeguy.customer_model` / `HasSumitCustomer` usage. CrmDataService, DocumentService `syncForClient`, Transaction/Document/Webhook/Token/CRM models all need this single concept.

---

### 2. Subscriber

**Definition:** The entity that “holds” a subscription — the morph target of `officeguy_subscriptions.subscriber_type` / `subscriber_id`. Used for recurring billing and for subscription–document matching.

| Aspect | Contract |
|--------|----------|
| **Required behavior** | Must provide a primary key and a way to resolve SUMIT customer ID. Package needs to find “all subscribers with `sumit_customer_id = X`” for document–subscription matching. No interface required: the package uses morph type + ID and, when needed, a **resolver** (see Section 2) to get “subscriber model class → table + column for sumit_customer_id”. |
| **Required identifiers** | Subscriber type (morph class string), subscriber ID (morph key). For subscription–document logic: the subscriber’s table must expose a queryable `sumit_customer_id` (or equivalent) so the package can build a subquery without hardcoding table name or morph type. |
| **Eloquent-based?** | Yes. Subscriptions use Eloquent morphs; subscriber is an Eloquent model. |
| **Polymorphism** | Yes. Multiple subscriber types (e.g. User, Client) can exist. Package must not hardcode one morph type or one table name; resolution must be config- or resolver-driven. |

**Validation:** Phase 1 POLY-001: DocumentService hardcodes `App\Models\User` and `users` table. The contract replaces that with “subscriber types and their table/column for sumit_customer_id are supplied by config or a single resolver.”

---

### 3. Payable

**Definition:** The entity being paid for (order, invoice, package, etc.). Already defined by the package’s `Payable` interface.

| Aspect | Contract |
|--------|----------|
| **Required behavior** | Implement existing `Payable` interface (getPayableId, getPayableAmount, getPayableCurrency, customer getters, getLineItems, getOrderKey, getPayableType, etc.). Optionally: `markAsPaid()` for post-payment updates. |
| **Required identifiers** | Payable type (enum), payable ID. For document linking: `order_number` or equivalent external reference, and link to customer (e.g. `client_id`) when syncing documents. |
| **Eloquent-based?** | Yes in practice (polymorphic `order_type`/`order_id`). Payable is resolved at runtime from the transaction’s morph. |
| **Polymorphism** | Yes. Multiple payable types (Order, Package, MayaNetEsimProduct, etc.). Resolution is per-request (route/checkout) or from stored `order_type`/`order_id`. |

**Validation:** Phase 1 HMR-003, HMR-006, HMR-007, HMR-011: DocumentService and PublicCheckoutController hardcode Order/Package/MayaNetEsimProduct. GenericFulfillmentHandler hardcodes Order + job. The contract: payable is always resolved via config or route binding; no hardcoded payable class or host job.

---

### Summary Table

| Concept    | Behavior (existing or implied)   | Identifiers                          | Eloquent | Polymorphic |
|-----------|----------------------------------|-------------------------------------|----------|-------------|
| Customer  | `HasSumitCustomer` (+ PK, email) | id, sumit_customer_id, email, vat… | Yes      | No (one class) |
| Subscriber| Morph + sumit_customer_id query  | subscriber_type, subscriber_id       | Yes      | Yes         |
| Payable   | `Payable` interface             | order_type, order_id, order_number  | Yes      | Yes         |

---

## SECTION 2 — Contract Resolution Strategy

One consistent mechanism for all three concepts: **config-backed model class string + single container binding per concept**. No fallback to `App\Models\*`. No extra service classes; resolution stays in the existing provider.

---

### Mechanism (unified)

1. **Config**  
   - One key per concept: e.g. `officeguy.models.customer`, `officeguy.models.subscriber_resolver` (or list of subscriber morph types + table/column), `officeguy.models.payable` (optional; payables are often resolved per-route).  
   - Values: class string (customer, optional default payable) or callable/resolver (subscriber → table + column for sumit_customer_id).  
   - No default to `App\Models\Client` or any host class. If not set, the package behaves defensively (skip, log, or throw a clear exception depending on the operation).

2. **Container**  
   - Same as today for customer: `app('officeguy.customer_model')` returns the **string** class name (no fallback).  
   - New (optional): `app('officeguy.subscriber_resolver')` returns a callable `(morphClass) => ['table' => ..., 'sumit_customer_id_column' => ...]` or a small resolver object.  
   - Payable: no global binding; resolved per checkout route (route parameter → payable) or from `payable_model_class` (Admin) for generic checkout. Route-to-model mapping (e.g. `checkout/package/{id}` → Package) comes from config (map of route name or segment to class), not hardcoded methods.

3. **No interface proliferation**  
   - Customer: already has `HasSumitCustomer`; package depends on that + Eloquent. No new interface.  
   - Subscriber: no interface; package only needs a way to “resolve subscriber type → table + sumit_customer_id column” for queries.  
   - Payable: keep existing `Payable` interface.

4. **Avoid fallback to App\Models\***  
   - Remove all `?? \App\Models\Client::class` (and similar). If `officeguy.customer_model` is null, code that needs a customer either: does not run (e.g. sync), or returns null/empty, or throws a dedicated exception (“Customer model not configured”).  
   - Config file: `customer_model_class` default is `null` or empty; docs/examples suggest setting it to e.g. `App\Models\Client` in the host app.

5. **Optional: event-driven hook**  
   - For fulfillment (GenericFulfillmentHandler): instead of dispatching a hardcoded job, the package can fire a package event (e.g. `PayablePaid`) with the transaction and payable. The host listens and dispatches `ProcessPaidOrderJob` (or any job). No package reference to host job. Event-driven is optional and keeps the “one mechanism” for model resolution; events are for side effects only.

---

### Resolution by concept

| Concept    | How resolved                          | No fallback behavior                    |
|-----------|----------------------------------------|-----------------------------------------|
| Customer  | `app('officeguy.customer_model')` (string) from DB → config. | If null: no customer relation, sync/CRM matching skip or fail explicitly. |
| Subscriber| Config/callable: morph type → table + `sumit_customer_id` column. | If unknown type: skip that type in subscription–document query or use resolver. |
| Payable   | Per-route (route param) or config `payable_model_class` / route→model map. | If unknown route: 404 or “payable type not configured”. |

---

### Trade-offs

| Approach              | Pros                                      | Cons                                      |
|-----------------------|-------------------------------------------|-------------------------------------------|
| **Config + container only** | Simple, one place, no new services.        | Subscriber needs a small “resolver” (table/column per morph type). |
| **Event for fulfillment**  | Zero host job reference; host owns all jobs. | One more extension point; docs needed.     |
| **No fallback**       | Clear contract; no silent dependency on Client. | Existing installs must set customer model or get explicit failure. |

**Recommended:** Config + container for Customer and Subscriber resolution; event (e.g. `PayablePaid`) for post-payment fulfillment; no fallback to any `App\Models\*`.

---

## SECTION 3 — Migration Decoupling Strategy

Goal: remove `constrained('clients')` (MFK-001–003) and fix FK to ambiguous `subscriptions` (MFK-004–005) without breaking existing installs.

---

### 3.1. Replace FK with indexed scalar (e.g. `client_id` only, no FK)

**Description:** Keep column `client_id` (or `customer_id`) on officeguy_crm_entities, officeguy_crm_activities, officeguy_sumit_webhooks but drop the foreign key constraint. Add an index for lookups. The package never declares a DB-level FK to the host table.

| Pros | Cons | BC impact | Recommendation |
|------|------|-----------|----------------|
| Migrations never depend on host table existence. Package works with any table name. | Referential integrity not enforced by DB. Orphaned IDs possible if host deletes customer. | **Breaking for new installs:** none. **Existing installs:** need a migration that drops the FK and adds index (same column). Existing data unchanged. | **Recommended** for the three “add client_id” migrations. New installs: create column + index only. Existing: one-time migration to drop FK, keep column + index. |

---

### 3.2. Make customer table name configurable

**Description:** Migrations read something like `config('officeguy.customer_table', 'clients')` and use `->constrained($table)`. Package still creates an FK, but to a host-chosen table.

| Pros | Cons | BC impact | Recommendation |
|------|------|-----------|----------------|
| DB enforces referential integrity. | Migration runs at install time; config might not be set yet. Table name might change later. Ties package migrations to host config. | Default `clients` preserves current behavior; hosts with different table name must set config before migrating. | **Optional.** If kept, use only when config is set; otherwise create column without FK (same as 3.1). Prefer 3.1 for simplicity. |

---

### 3.3. Move FK responsibility to host migration

**Description:** Package migrations only add nullable `client_id` (or `customer_id`) + index. Host is responsible for a separate migration that adds the FK to its customer table.

| Pros | Cons | BC impact | Recommendation |
|------|------|-----------|----------------|
| Package never touches host schema. Clear ownership. | Host must run an extra migration; docs and possibly a stub migration. | New installs: package no longer fails if `clients` missing. Existing: package migration drops FK (or we provide a “drop FK” migration). | **Good complement to 3.1.** Package does 3.1; docs (or optional stub) show host how to add FK if they want. |

---

### 3.4. Ambiguous `subscriptions` table (MFK-004, MFK-005)

**Description:** Webhook events and sumit_incoming_webhooks use `->on('subscriptions')` for `subscription_id`. Package model uses `officeguy_subscriptions`.

| Option | Action | Pros | Cons | BC impact | Recommendation |
|--------|--------|------|------|-----------|----------------|
| **A** | Change to `->on('officeguy_subscriptions')` in both migrations. | Correct target; package self-contained. | Existing installs may have already run the wrong migration (FK to `subscriptions`). | If FK was created to wrong table, fix requires a migration to drop old FK and add new one. | **Recommended.** Fix migrations for new installs. Provide a remedial migration for existing: drop FK on `subscription_id`, re-add to `officeguy_subscriptions`. |
| **B** | Make subscription table name configurable. | Flexible. | Overkill; package owns subscriptions. | Same as A for existing. | Not recommended. |

---

### 3.5. Compatibility layer

**Description:** Keep old migrations as-is; new “decoupling” migration runs only if certain columns exist and drops FKs, adds indexes. Avoids editing old migration files.

| Pros | Cons | BC impact | Recommendation |
|------|------|-----------|----------------|
| No change to historical migrations. | Two-step process; more complexity. | Safe for existing installs. | **Optional.** Prefer fixing the three client_id migrations in place (drop FK, add index) and fixing subscriptions in place (or plus remedial), plus documenting one-time steps for already-deployed apps. |

---

### Summary (migrations)

- **client_id (MFK-001–003):** Prefer **3.1** (indexed scalar, no FK). Optionally document **3.3** (host adds FK if desired). Do not default to `constrained('clients')`.
- **subscription_id (MFK-004, MFK-005):** Prefer **3.4 A** (`->on('officeguy_subscriptions')`). Provide remedial migration for existing installs that have the wrong FK.
- **No implementation in this phase;** only design.

---

## SECTION 4 — Runtime Decoupling Plan

For each critical area: what must be replaced, what the contract supplies, whether behavior changes, and risk level.

---

### 4.1. DocumentService

| Item | What must be replaced | What contract supplies | Behavior change? | Risk |
|------|------------------------|------------------------|------------------|------|
| `use App\Models\Client;` / `use App\Models\Order;` | Type hints and static calls to Client/Order. | Customer from `app('officeguy.customer_model')` (class string); no Order type — use Payable or resolve order via config/morph. | `syncForClient(Client $client)` becomes `syncForClient($customer)` where `$customer` is the resolved model instance (or accept customer id + use customer model from container). Order linking: resolve “order” model from config (class string), query by `client_id` + external ref/order_number. | **Medium.** Callers of `syncForClient` must pass a customer instance; typically the host already has it. Order linking must use configurable order model. |
| `subscriber_type = 'App\Models\User'` and `->from('users')` | Hardcoded morph type and table name. | Subscriber resolver: given morph type(s), return table name and `sumit_customer_id` column; build subquery dynamically. Config: list of subscriber morph classes or resolver callable. | Subscription–document matching works for any configured subscriber type(s). If resolver not set, that part of matching can be skipped or logged. | **Medium.** Need to add resolver and ensure all subscriber types are registered. |
| Fallback `?? \App\Models\Client::class` | Remove. | Customer from container only; no fallback. | If not configured, operations that need customer fail explicitly or no-op. | **Low.** |

---

### 4.2. PublicCheckoutController

| Item | What must be replaced | What contract supplies | Behavior change? | Risk |
|------|------------------------|------------------------|------------------|------|
| `$modelClass = 'App\Models\Package'` / `'App\Models\MayaNetEsimProduct'` in showPackage/processPackage and showEsim/processEsim. | Hardcoded class strings per route. | Config: map route name or path segment to payable model class (e.g. `officeguy.checkout_route_models.package` => Package::class, `officeguy.checkout_route_models.esim` => MayaNetEsimProduct::class). Controller reads from config; no hardcode. | None from caller perspective. Host configures which model each route uses. | **Low.** |
| `app('officeguy.customer_model') ?? \App\Models\Client::class` | Remove fallback. | Container only. | Same as 4.1. | **Low.** |

---

### 4.3. GenericFulfillmentHandler

| Item | What must be replaced | What contract supplies | Behavior change? | Risk |
|------|------------------------|------------------------|------------------|------|
| `$payable instanceof \App\Models\Order` and `\App\Jobs\ProcessPaidOrderJob::dispatch(...)`. | Any reference to Order class and host job. | Option A: Fire package event (e.g. `PayablePaid($transaction, $payable)`). Host listens and dispatches its job. Option B: Configurable job class string (e.g. `officeguy.fulfillment_job`) and dispatch by class name; host sets their job. | No behavior change from host perspective if they listen for the event or set the job. Package no longer knows about Order or ProcessPaidOrderJob. | **Low.** Event is cleaner; configurable job is minimal. |

---

### 4.4. CrmDataService

| Item | What must be replaced | What contract supplies | Behavior change? | Risk |
|------|------------------------|------------------------|------------------|------|
| `\App\Models\Client::where(...)` (four places: sumit_customer_id, vat_number, email, phone). | Direct static calls to Client. | Resolved customer model class from container: `$class = app('officeguy.customer_model');` then `$class::where(...)`. No fallback. | None if customer model is configured. If null, matching fails (return null) or throws. | **Low.** |

---

### 4.5. OfficeGuyTransactionPolicy

| Item | What must be replaced | What contract supplies | Behavior change? | Risk |
|------|------------------------|------------------------|------------------|------|
| `use App\Models\User;` and `use App\Enums\UserRole;` and all `User $user` type hints and `$user->isStaff()`, `$user->role === UserRole::CLIENT`, etc. | Dependency on host User and UserRole. | Option A: Configurable “authorization model” class (e.g. `officeguy.authorization_model`). Policy receives `$user` as that model; host must implement methods like `isStaff()`, `isClient()`, `role`, `isAdmin()`, `isSuperAdmin()`. Option B: Policy delegates to a callable from config (e.g. `officeguy.policy.view_transaction => fn($user, $transaction) => ...`). | Policy behavior stays the same if host provides same methods or same callable results. | **Medium.** Option A requires host to implement a small contract (methods or interface). Option B is more flexible but pushes logic into config. Prefer A with a minimal interface or doc-block contract. |

---

### 4.6. CrmEntity relationships

| Item | What must be replaced | What contract supplies | Behavior change? | Risk |
|------|------------------------|------------------------|------------------|------|
| `belongsTo(\App\Models\User::class, 'owner_user_id')` and `assigned_to_user_id`. | Hardcoded User class. | Configurable “staff/user” model class (e.g. `officeguy.staff_model` or reuse `officeguy.authorization_model`): `belongsTo(app('officeguy.staff_model'), 'owner_user_id')`. No fallback. | None if model is configured. Column names stay; only the related model class is configurable. | **Low.** If not set, relationship can return null or be disabled. |

---

## SECTION 5 — Boundary Definition

Written explicitly: what the package owns vs what the host owns.

---

### Package owns

- **Tables:** All `officeguy_*` tables (transactions, tokens, documents, settings, vendor_credentials, subscriptions, webhook_events, sumit_incoming_webhooks, CRM tables, pending_checkouts, order_success_*, payable_field_mappings, debt_attempts, etc.).
- **Subscription system:** Creation, renewal, cancellation, and storage of subscription records; subscription–document linking logic (using contract-supplied subscriber resolution).
- **Webhook system:** Receiving and processing SUMIT webhooks and Bit webhooks; outgoing webhook events; storage in package tables.
- **Transactions:** Creating and storing payment transactions; linking to payable via polymorphic `order_type`/`order_id`; linking to customer via `client_id` (or configured column) where the **value** is host-owned, the **column and index** are package-owned.
- **CRM tables and sync:** officeguy_crm_* schema and SUMIT CRM sync; matching of SUMIT entities to “customer” IDs using the resolved customer model only (no hardcoded table).
- **Checkout UI and flow:** Public checkout pages, forms, and success page; orchestration of payment and token creation. Resolution of which payable model to load comes from config/route map (host supplies the classes).
- **Policies (implementation):** The package provides the policy logic; the **authorization model** (and optionally role enum) is supplied by the host via config so the package never references `App\Models\User` or `App\Enums\UserRole`.
- **Events:** Package-defined events (e.g. PaymentCompleted, PayablePaid for fulfillment). Listeners and jobs are host-owned.

---

### Host owns

- **Customer model:** The Eloquent model that implements `HasSumitCustomer` and is the single “customer” for the package. Table name, attributes (email, vat_number, etc.) are host’s. Package only stores `client_id` (or equivalent) and uses resolved model for queries.
- **User / staff model:** The model used for Filament/auth and for policy checks (e.g. who can view/refund transactions). Methods like `isStaff()`, `isClient()`, `isAdmin()` are host’s. Package uses this model only via config (class string).
- **Payable domain:** All Payable implementations (Order, Package, MayaNetEsimProduct, etc.). Host registers which class is used for which route or for generic checkout. Package never references a specific payable class by name.
- **Business jobs:** e.g. `ProcessPaidOrderJob`, or any job dispatched after payment. Host listens to package events or configures a job class; package does not reference host job classes.
- **Subscriber types:** Which Eloquent models can be subscription owners (e.g. User, Client). Host configures morph types and (via resolver) table + `sumit_customer_id` column for each. Package does not hardcode morph type or table.
- **Optional FK to customer:** If the host wants DB-level referential integrity from package tables to their customer table, the host adds that FK in their own migration (see Section 3).

---

### Summary

- **Package:** officeguy_* data, subscription/webhook/transaction/CRM logic, checkout orchestration, policy logic, events. Resolution of customer, subscriber, payable, and auth model is by config/container only.
- **Host:** Customer, User/staff, Payable implementations, jobs, subscriber types and resolver, optional FKs.

---

## SECTION 6 — Architectural Decision

- **Recommended integration model**  
  - **Three concepts:** Customer (HasSumitCustomer + configurable class, no fallback), Subscriber (morph + configurable resolver for table/column), Payable (existing interface + route/config-driven class resolution).  
  - **Single mechanism:** Config + container for model class strings and subscriber resolver; optional event for fulfillment; no `App\Models\*` fallbacks.  
  - **Migrations:** Indexed `client_id` (or equivalent) without FK to host; FK to `officeguy_subscriptions` for subscription_id; host may add its own FK to customer table.

- **Expected version bump**  
  - **Major (e.g. 4.0.0).** Removing fallbacks and requiring explicit configuration is a breaking change for installs that rely on default `App\Models\Client`. Changing policy and CrmEntity to use configurable models may require host changes.

- **Estimated refactor surface**  
  - **Files:** ~15–20 (DocumentService, PublicCheckoutController, GenericFulfillmentHandler, CrmDataService, OfficeGuyTransactionPolicy, CrmEntity; 4 models with customer() fallback; 2 controllers with fallback; config; 5 migrations + optional remedial).  
  - **New:** Optional subscriber resolver (config + small callable/class), optional `PayablePaid` event, config keys for route→payable map and authorization/staff model.  
  - **Removed:** All `?? \App\Models\Client::class`, all `App\Models\*` and `App\Enums\*` and `App\Jobs\*` references in package code.

- **New integrity score after refactor**  
  - Phase 1 score was **62/100**. After applying this contract: no host FKs in package migrations, no hardcoded host models or jobs, no fallbacks. **Estimated new score: 85–90/100** (remaining deductions for optional config complexity and documentation burden).

---

**Phase 2 completion:** Design only. No code or migrations have been implemented.
