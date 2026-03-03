# DocumentService Decoupling Design — Phase 2.1 (Design Only)

**Objective:** Rewrite the architectural assumptions of DocumentService so that there are no hard dependencies on `App\Models\*`, no hardcoded table names or morph types, no fallback to `App\Models\Client`, and no hidden reliance on host schema.

**Scope:** `src/Services/DocumentService.php` and its direct collaborators (read-only). No migrations, controllers, or policies modified in this phase.

**Constraints:** No additional service classes, no abstract factory, no bridge layer, no adapter hierarchy, no polymorphic registry. Only the three Integration Concepts (Customer, Subscriber, Payable). Minimalism is mandatory.

---

## SECTION 1 — Dependency Dissection

Structured breakdown by method: host model, host table, attributes, morph types; classification (Customer-bound, Subscriber-bound, Payable-bound, Schema-bound, Safe).

| Method | Host model assumed | Host table assumed | Attributes assumed | Morph types hardcoded | Classification |
|--------|--------------------|--------------------|--------------------|------------------------|-----------------|
| (file: use statements 7–8) | Client, Order | — | — | — | Customer-bound, Payable-bound |
| **createOrderDocument** | — | — | — | — | Payable-bound (Payable interface only); Safe |
| **createDocumentOnPaymentComplete** | — | — | — | — | Payable-bound; Safe |
| **createDonationReceipt** | — | — | — | — | Payable-bound; Safe |
| **getDocumentTypeName** | — | — | — | — | Safe |
| **isDonationReceiptType** | — | — | — | — | Safe |
| **syncForClient** | Client, Order | (Order model’s table) | Client: id, sumit_customer_id. Order: client_id, order_number, id. | — | Customer-bound, Payable-bound |
| **fetchFromSumit** | — | — | — | — | Safe |
| **getDocumentDetails** | — | — | — | — | Safe |
| **identifySubscriptionsInDocument** | — | users | (subquery: id, sumit_customer_id) | App\Models\User | Subscriber-bound, Schema-bound |
| **syncAllForCustomer** | — | — | — | — | Calls identifySubscriptionsInDocument → Subscriber-bound, Schema-bound |
| **syncForSubscription** | — | — | Subscriber: sumit_customer_id (attribute) | — | Subscriber-bound |
| **createCreditNote** | — | — | — | — | Customer-bound via HasSumitCustomer; Safe |
| **getDocumentPDF** | — | — | — | — | Safe |
| **sendByEmail** | — | — | — | — | Safe |
| **cancelDocument** | — | — | — | — | Safe |

**Detail for syncForClient (lines 406–474):**

- **Customer-bound:** Parameter type `Client`; uses `$client->sumit_customer_id`, `$client->id`.
- **Payable-bound:** `Order::where('client_id', $client->id)`, `->where('order_number', ...)`, `->orWhere('id', ...)`; `$document->order_type = Order::class`. Assumes Order model with table (Eloquent), columns client_id, order_number, id.
- **Schema-bound:** Implicit via Order model (table name = Order’s table).

**Detail for identifySubscriptionsInDocument (lines 650–676):**

- **Subscriber-bound:** Morph type `'App\\Models\\User'` hardcoded.
- **Schema-bound:** `->from('users')`, column `sumit_customer_id` in subquery.

**Detail for syncForSubscription (lines 845–853):**

- **Subscriber-bound:** `$subscriber = $subscription->subscriber`; `$subscriber->sumit_customer_id`. Assumes subscriber has attribute `sumit_customer_id` (no table/morph hardcode).

**Removed from dissection:** PaymentService, DonationService, OfficeGuyDocument, SumitConnector, CredentialsData — they are package-owned or use Payable/HasSumitCustomer only; no host model/table/morph in DocumentService’s use of them.

---

## SECTION 2 — Contract Replacement Map

For every hard assumption, what replaces it (concept), how it is resolved, and what is explicitly removed.

| Assumption | Location | Integration concept that replaces it | How resolved | Explicitly removed |
|------------|----------|--------------------------------------|--------------|--------------------|
| `use App\Models\Client;` | 7 | Customer | N/A (remove use) | use statement |
| `use App\Models\Order;` | 8 | Payable | N/A (remove use) | use statement |
| Parameter `Client $client` | 406 | Customer | Caller passes customer instance; customer class from container (caller resolves). | Type hint Client; replace with type hint for object implementing HasSumitCustomer or accept customer instance from caller. |
| `$client->sumit_customer_id`, `$client->id` | 408, 439, 449, 455, 456 | Customer | Customer instance supplied by caller; attributes are on that instance (HasSumitCustomer + getKey()). | Dependency on Client type. |
| `Order::where('client_id', $client->id)` etc. | 439–454 | Payable | Resolved payable model class string from config/container; static call becomes `$payableClass::where($customerIdColumn, $client->id)->...` with configurable column names for “customer FK” and “external reference”. | Static calls to Order; hardcoded client_id, order_number. |
| `$document->order_type = Order::class` | 457 | Payable | Same payable class string from config/container; assign that string to order_type. | Hardcoded Order::class. |
| `syncForClient(Client $client, ...)` signature | 406 | Customer | Signature: accept `HasSumitCustomer $customer` (or object with getSumitCustomerId() and getKey()). No reference to Client. | Client type. |
| Morph type `'App\\Models\\User'` | 667 | Subscriber | Subscriber resolver (see Section 3). | Hardcoded morph string. |
| `->from('users')` | 670 | Subscriber | Subscriber resolver returns table name; query uses that. | Hardcoded table name. |
| Subquery column `sumit_customer_id` | 671 | Subscriber | Resolver returns column name for SUMIT customer ID on that table. | Hardcoded column name. |
| `$subscriber->sumit_customer_id` in syncForSubscription | 853 | Subscriber | Keep attribute access: subscriber is morph-loaded; resolver or contract doc states “subscriber must expose sumit_customer_id (or resolver supplies getter)”. No table/morph in DocumentService. | None to remove; behavior stays, no hardcoded type/table. |

**Resolution mechanisms (only these three):**

1. **Container class string (Customer):** `app('officeguy.customer_model')` — no fallback. Used when DocumentService needs to *instantiate or query* the customer model (e.g. “find customer by sumit_customer_id” for a future helper). For `syncForClient`, the *caller* passes the customer instance; the caller obtains it using the same container class string elsewhere. DocumentService does not call the container inside syncForClient for the customer type; it only uses the passed instance. So: **replacement** = syncForClient accepts HasSumitCustomer (or object with getSumitCustomerId() and getKey()); **resolution** = caller uses container; DocumentService is agnostic.
2. **Config/container (Payable for sync order-linking):** Config key e.g. `officeguy.models.payable_for_document_sync` or single “order model” class string from container. Used to get the class and optional column map (customer FK column, external reference column) for “find payable by customer + external ref”. **Removed:** all `Order::` static calls and `Order::class`.
3. **Subscriber resolver (config/container):** Single callable or small resolver: input = morph type (string); output = associative array with `table` and `sumit_customer_id_column` (and optionally `primary_key`). Used only in `identifySubscriptionsInDocument` to build the subquery. **Removed:** `'App\\Models\\User'`, `->from('users')`, hardcoded column name.

No event, no extra interfaces. Only: (1) container class string for customer, (2) config/container for payable model + optional column map for sync, (3) subscriber resolver for subscription–document query.

---

## SECTION 3 — Subscriber Query Reconstruction

**Current behavior:** Find subscriptions whose subscriber has `sumit_customer_id = X`; morph type hardcoded `App\Models\User`; table hardcoded `users`.

**Design requirement:** Support multiple subscriber types; no hardcoded table or morph strings; single resolver mechanism.

### 3.1 Resolver input

- **Input:** One argument, `string $morphType` — the value of `subscriber_type` (e.g. `App\Models\User`, `App\Models\Client`).

### 3.2 Resolver output

- **Output:** Either:
  - `array{table: string, sumit_customer_id_column: string}` for that morph type, or
  - `null` if the morph type is not registered (or not supported for this query).

No primary key needed if the subquery is `SELECT id FROM {table} WHERE {sumit_customer_id_column} = ?` and subscriptions use `subscriber_id` as the FK to that table’s `id`. If the resolver returns primary key column name (e.g. for non-standard PK), it can be `array{table: string, sumit_customer_id_column: string, primary_key?: string}` with default `primary_key = 'id'`.

### 3.3 How the query is built dynamically

1. Obtain the list of distinct `subscriber_type` values that exist in `officeguy_subscriptions` (e.g. `Subscription::query()->distinct()->pluck('subscriber_type')`), or use a config list of “subscriber types to consider for document matching” (config array of morph class strings).
2. For each such type, call the resolver: `$resolver($morphType)`. If the resolver returns `null`, skip that type (do not add it to the where clause).
3. For each type that returns `{ table, sumit_customer_id_column }`, add an OR branch to the subscription query:
   - `Subscription::query()->where(function ($q) use ($sumitCustomerId, $resolver, $types): void { foreach ($types as $morphType) { $info = $resolver($morphType); if (!$info) continue; $q->orWhere(function ($q2) use ($sumitCustomerId, $info): void { $q2->where('subscriber_type', $morphType)->whereIn('subscriber_id', subquery: SELECT {primary_key} FROM {$info['table']} WHERE {$info['sumit_customer_id_column']} = $sumitCustomerId); }); } })`.
4. Execute the query and get subscriptions. No table or morph string in code except from resolver/config.

### 3.4 Failure mode if subscriber type not registered

- If the resolver returns `null` for a morph type: **skip that type** (do not add an OR branch for it). No exception, no log required for “not registered” (optional: log at debug level). If *all* types return null, the subscription query has no OR branches for “subscriber has sumit_customer_id = X” — then the where clause must still be valid: e.g. add an impossible condition for that branch so the query returns no rows for that part, or build the query only from types that returned non-null. Result: **skip operation** for unregistered types (no subscriptions found for them).

---

## SECTION 4 — Failure Modes

Each case: one of throw explicit exception, skip operation, log warning, return null.

| Scenario | Choice | Notes |
|----------|--------|--------|
| **1. customer_model is not configured** (container returns null) | **Skip operation** | For `syncForClient`: if the *caller* cannot resolve a customer (e.g. no config), the caller must not call syncForClient or must pass a customer instance from its own resolution. DocumentService does not resolve customer model internally for syncForClient. For any future method that *does* resolve customer model from container (e.g. “sync by sumit_customer_id only”): if container returns null, **skip operation** and return 0 or empty; optional log warning. |
| **2. Subscriber resolver is missing** (container/config returns null or callable not set) | **Skip operation** | In `identifySubscriptionsInDocument`: if resolver is not available, do not run the subscription subquery; return [] from identifySubscriptionsInDocument. **Log warning** once (e.g. “Subscriber resolver not configured; subscription–document matching disabled”). |
| **3. Payable model cannot be resolved** (for order-linking in syncForClient: config/container returns null) | **Skip operation** | In syncForClient, when linking document to “order”: if payable model class (or column map) is not configured, do not attempt order lookup; leave document order_id/order_type null. **Log warning** (e.g. “Payable model not configured for document sync; order linking skipped”). |
| **4. Required attribute missing** (e.g. customer has no sumit_customer_id, or subscriber has no sumit_customer_id) | **Skip operation** | syncForClient: if passed customer has no getSumitCustomerId() or it is null/empty — **return 0** (no sync). syncForSubscription: if subscriber has no sumit_customer_id — **return 0**. createCreditNote: already returns `['success' => false, 'error' => '...']` if getSumitCustomerId() is null — keep that. No throw; **skip** and return 0 or failure array. |

**Summary:** No explicit exceptions for “not configured” or “missing attribute”; **skip operation** and return 0/empty/failure array; **log warning** only for missing resolver and missing payable model for sync.

---

## SECTION 5 — Boundary Confirmation

**DocumentService must only depend on:**

- **Customer:** Customer model class string from container when it needs to resolve customer by ID/sumit_customer_id in the future; for syncForClient, only the **Payable interface** and the **HasSumitCustomer** contract (passed instance). No class name from DocumentService for Customer; caller passes customer instance.
- **Subscriber resolver:** One callable or resolver from config/container: morph type → { table, sumit_customer_id_column }.
- **Payable:** Payable interface for createOrderDocument, createDocumentOnPaymentComplete, createDonationReceipt; for syncForClient order-linking, payable model class string (and optional column map) from config/container.
- **Package tables:** officeguy_documents, officeguy_subscriptions, and package models (OfficeGuyDocument, Subscription). No host table names.

**DocumentService must not depend on:**

- Any `App\Models\*` namespace.
- Any host table name (e.g. users, clients, orders).
- Any host enum.
- Any host job.
- Any fallback (e.g. `?? \App\Models\Client::class`).

**Explicit removals:**

- Remove `use App\Models\Client;` and `use App\Models\Order;`.
- Remove parameter type `Client` in syncForClient; replace with interface or object (HasSumitCustomer) or documented “customer instance with getSumitCustomerId() and getKey()”.
- Remove all `Order::where(...)` and `Order::class`; replace with container/config payable class and optional column map; build query via that class string.
- Remove hardcoded `'App\\Models\\User'`, `->from('users')`, and hardcoded `sumit_customer_id` in the subquery; replace with subscriber resolver.

**If any dependency remains:**

- PaymentService, DonationService, OfficeGuyDocument, SumitConnector, CredentialsData, OfficeGuyApi, Carbon — **allowed** (package or Payable/HasSumitCustomer only).
- Subscription model (package) — **allowed**.
- No other host dependency may remain. If a collaborator (e.g. PaymentService) itself depended on a host model, that is out of scope for this document; this design assumes DocumentService’s direct surface is decoupled as above.

---

**Design validation:** Only three resolution mechanisms are used — (1) container for customer model class, (2) config/container for payable model + columns for sync, (3) subscriber resolver. No new interfaces beyond the three concepts. No implementation code; design only.
