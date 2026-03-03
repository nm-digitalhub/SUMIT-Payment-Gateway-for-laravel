# DocumentService Architectural Stress Test — Phase 2.2 (Data-Backed Validation)

**Objective:** Factual, code-based validation answering three architectural questions. No code changes. Design validation only.

**Scope:** `src/Services/DocumentService.php`, `src/Contracts/Payable.php`, `src/Contracts/HasSumitCustomer.php`, `src/Models/Subscription.php`, `src/Models/OfficeGuyDocument.php`, and relationships referenced by them. No speculative design changes.

---

## SECTION 1 — Customer Resolution Validation

**Question:** Can DocumentService operate entirely on a passed Customer instance without ever resolving the customer model class from the container?

### 1.1 Every location in DocumentService: Customer model class, static query, container

| Location | Code | Customer model class referenced? | Static query on Customer? | Container used? |
|----------|------|----------------------------------|---------------------------|-----------------|
| Lines 7–8 | `use App\Models\Client;` `use App\Models\Order;` | Yes (Client as type) | No | No |
| Line 406 | `public static function syncForClient(Client $client, ...)` | Yes (parameter type) | No | No |
| Line 408 | `$client->sumit_customer_id` | No (instance attribute) | No | No |
| Line 412 | `(int) $client->sumit_customer_id` | No | No | No |
| Lines 439–440, 449–450 | `Order::where('client_id', $client->id)` | No (Order, not Customer) | No (query is on Order) | No |
| Line 455–458 | `$document->order_id = $order->id;` `$document->order_type = Order::class;` | No | No | No |
| Lines 1035–1041 | `createCreditNote(HasSumitCustomer $customer, ...)` `$customer->getSumitCustomerId()` | No (interface only) | No | No |

**Container usage in DocumentService:** Grep for `app(`, `container`, `resolve` (excluding Saloon `resolveEndpoint`) shows **no** calls to the Laravel container in DocumentService. Customer/Client is used only as a type hint and via the passed instance (`$client->sumit_customer_id`, `$client->id`). `createCreditNote` already uses `HasSumitCustomer` and only instance methods.

**Static query on Customer model:** There is **no** `Client::where(...)` or any static query on the Customer model in DocumentService. The only static queries are on `Order` (lines 440, 450) for order-linking.

### 1.2 Per-location: instance available? class string required? static query? operable on instance only?

| Location | Instance already available? | Class string required? | Static query performed? | Can use only passed instance + HasSumitCustomer + getKey() + getSumitCustomerId()? |
|----------|----------------------------|------------------------|---------------------------|-----------------------------------------------------------------------------------|
| syncForClient parameter | Caller supplies instance | No | No | Yes: type as HasSumitCustomer; use `$customer->getSumitCustomerId()` and `$customer->getKey()` (Eloquent) for id. |
| syncForClient body (408, 412) | Yes (`$client`) | No | No | Yes: getSumitCustomerId() and getKey() cover sumit_customer_id and id. |
| syncForClient body (439, 450) | N/A (finding Order) | N/A | On Order, not Customer | Yes for Customer: no class/query needed for Customer here. |
| createCreditNote | Caller supplies instance | No | No | Yes: already uses HasSumitCustomer and getSumitCustomerId(). |

### 1.3 Conclusion table

| Location | Requires model class? | Requires static query? | Can operate on instance only? | Conclusion |
|----------|------------------------|--------------------------|-------------------------------|------------|
| syncForClient (Customer usage) | No | No | Yes | Instance-only sufficient |
| createCreditNote | No | No | Yes | Instance-only sufficient |
| (No other Customer usage in DocumentService) | — | — | — | — |

### Required output (binary decision)

**YES — DocumentService never needs container-based Customer resolution.**

DocumentService does not call `app('officeguy.customer_model')` or the container. It uses (1) a typed parameter `Client` and instance properties `$client->sumit_customer_id`, `$client->id` in `syncForClient`, and (2) `HasSumitCustomer` and `getSumitCustomerId()` in `createCreditNote`. It performs no static query on the Customer model. All Customer usage can be satisfied by a passed instance that implements `HasSumitCustomer` and provides `getKey()` (e.g. Eloquent model). No container access is required inside DocumentService for Customer.

---

## SECTION 2 — Payable Column Map Necessity Test

**Question:** Does order-linking in syncForClient require knowledge of column names (client_id, order_number, etc.), or can it be implemented using only the Payable interface?

### 2.1 Fields used to match a document to an Order and to set order_type / order_id

**From DocumentService (lines 435–458):**

- **Match criteria:**  
  - Customer linkage: `$client->id` (customer PK) → query uses `Order::where('client_id', $client->id)`.  
  - External reference: `$doc['ExternalReference']` or `$doc['DocumentNumber']` → query uses `->where('order_number', $ext)` or `->orWhere('id', is_numeric($ext) ? (int) $ext : 0)` and `->where('order_number', $doc['DocumentNumber'])`.  
- **Set on document:**  
  - `$document->order_id = $order->id` (payable PK).  
  - `$document->order_type = Order::class` (morph type).

So the code **finds** a payable by: customer PK, and external reference (order_number or id). It does **not** receive a Payable instance; it runs `Order::where(...)` and needs the Order **model class** and the **column names** `client_id`, `order_number`, and `id`.

### 2.2 Comparison with Payable interface

**Payable interface (src/Contracts/Payable.php):**

- `getPayableId()` — unique identifier of the payable (e.g. order id).  
- `getCustomerId()` — customer ID from the system (string|int|null).  
- No method: “find payable by customer id and external reference.”  
- All methods are **instance** methods; they operate on an existing Payable instance.

**Order-linking flow:** Inputs are (customer id, external reference string). We need to **obtain** an order such that its customer matches and its external reference (e.g. order_number) matches. That is a **query by criteria**, not an operation on an existing instance. The interface does not define how to run that query (no static finder, no repository).

### 2.3 Required data vs Payable and column map

| Required data | Currently in Payable? | Derivable from Payable (on instance)? | Requires column map? |
|---------------|------------------------|----------------------------------------|------------------------|
| Customer linkage for query | No (no finder) | getCustomerId() exists but only on instance; we do not have the instance yet | Yes: need column name for “customer FK” (e.g. client_id) to run Model::where($col, $customerId) |
| External reference for query | No (no finder) | getPayableId() on instance; we need to query by “external ref” (e.g. order_number) | Yes: need column name for “external reference” (e.g. order_number) |
| Unique identifier (for order_id) | getPayableId() / PK on instance | Yes once we have instance | No for value; we still need model class + query to get the instance |
| Morph type (order_type) | No (class name) | Yes as `$order::class` once we have instance | No for value; we need model class to run the query and to set order_type |

So: **finding** the order requires running an Eloquent query. That requires (1) the **model class** (e.g. Order) and (2) the **column names** used in the query: customer FK column (`client_id`), external reference column (`order_number`), and primary key (`id`). The Payable interface does not expose column names or a static “find by customer + reference” method.

### 2.4 Conclusion

**Column map IS required.**

Reason: Order-linking must **find** a Payable by (customer id, external reference). Payable only provides **instance** methods (getPayableId, getCustomerId, etc.). There is no interface method or package API to “find payable by customer and reference.” Therefore the package must run an Eloquent query (e.g. `Model::where($customerFkColumn, $customerId)->where($externalRefColumn, $ref)->first()`). That requires the **model class** and the **column names** for customer FK and external reference (and PK). So a column map (or equivalent: config/container supplying model class and those column names) is required. The Payable interface alone is not sufficient for this “find by criteria” use case.

---

## SECTION 3 — Subscriber Resolver Necessity Test

**Question:** Can subscription matching be implemented using Eloquent relationships (whereHas) instead of raw table-based subqueries?

### 3.1 Subscription model: morph relationship and subscriber attribute

**From src/Models/Subscription.php:**

- **Relationship:** `public function subscriber(): MorphTo` → `return $this->morphTo();` (lines 82–85). Default morph keys: `subscriber_type`, `subscriber_id`.
- **Stored:** `subscriber_type`, `subscriber_id` in `$fillable` (40–41). So morph type and id are stored on `officeguy_subscriptions`.
- **Subscriber and sumit_customer_id:** DocumentService does not define the subscriber model. In `syncForSubscription` (851–853) it uses `$subscriber = $subscription->subscriber` and `$subscriber->sumit_customer_id`. So the **host** subscriber model (User, Client, etc.) is expected to expose a `sumit_customer_id` attribute. The package only assumes that attribute exists on the related model when loaded via the morph.

### 3.2 Is Subscription::whereHas('subscriber', fn($q) => $q->where('sumit_customer_id', X)) technically valid?

- **Relationship:** `subscriber()` is a `MorphTo`. Laravel’s `whereHas('subscriber', ...)` adds an existence constraint on that relation. The callback receives the builder for the **related** model (the subscriber).
- **Constraint:** `$q->where('sumit_customer_id', $X)` filters the related model by attribute. So “subscriptions whose subscriber has sumit_customer_id = X” is expressible as `Subscription::whereHas('subscriber', fn($q) => $q->where('sumit_customer_id', $X))`.
- **Multiple morph types:** The table for “subscriber” depends on `subscriber_type` (e.g. User → `users`, Client → `clients`). For a **single** subscriber type, one `whereHas('subscriber', ...)` is valid: Laravel resolves the related table from the morph type. For **multiple** types in the same table, Laravel’s single `whereHas('subscriber', ...)` builds an existence subquery that must apply to the polymorphic relation; the framework typically handles this by considering the morph type and the corresponding related table per row. So the pattern is valid; the only requirement is that every subscriber type used has a table with a `sumit_customer_id` column (or the host keeps the contract).

### 3.3 Constraints table

| Constraint | Exists? | Blocks whereHas approach? | Notes |
|------------|---------|----------------------------|--------|
| Morph relationship name | Yes: `subscriber()` MorphTo | No | Subscription has `subscriber()`; whereHas('subscriber', ...) is valid. |
| Morph type stored | Yes: `subscriber_type` on officeguy_subscriptions | No | Laravel uses it to resolve the related model/table. |
| Subscriber exposes sumit_customer_id | Yes (syncForSubscription uses $subscriber->sumit_customer_id) | No | Host model must have this attribute; no blocker for whereHas. |
| Eloquent support for multiple morph types in one whereHas | Yes (Laravel MorphTo builds type-aware existence query) | No | whereHas on MorphTo works with multiple types; no need to pass table name. |
| Performance (e.g. cross-type filtering) | N/A | No | whereHas uses standard existence subquery; no evidence of a blocker. |

### 3.4 Required output (binary decision)

**whereHas is viable; table-based resolver unnecessary.**

Subscription has a `subscriber()` MorphTo relation; the host subscriber model is used with a `sumit_customer_id` attribute (see syncForSubscription). The query “subscriptions whose subscriber has sumit_customer_id = X” can be implemented as `Subscription::whereHas('subscriber', fn($q) => $q->where('sumit_customer_id', $X))`. Laravel resolves the related table from `subscriber_type`; no table name or column name need be passed. So subscription matching can be implemented using this Eloquent relation only. A table-based resolver (table + column) is not required. A config list of subscriber morph type class strings is still useful if the implementation wants to restrict or iterate over allowed types (e.g. for clarity or filtering), but it is not required to avoid hardcoding table names: whereHas does that.

---

**End of stress test. All answers are based solely on the listed files and their relationships. No code was modified.**
