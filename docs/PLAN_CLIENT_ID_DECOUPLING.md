# Plan: Client ID Decoupling (Package Agnostic to Host Domain)

## Executive Summary

Remove the package’s dependency on the host application’s customer PK (`client_id`). Store only SUMIT identifiers (`customer_id` / `sumit_customer_id`) in package tables and make `client_id` deprecated/optional so the package remains agnostic to host domain structure. Implementation follows the existing pattern used by `OfficeGuyDocument` and `WebhookEvent`.

## Objectives

- Stop writing host customer PK into package tables (`officeguy_sumit_webhooks.client_id`, `officeguy_transactions.client_id`, `officeguy_crm_entities.client_id`).
- Rely only on SUMIT customer ID (`customer_id` / `sumit_customer_id_used`) for linking; keep `client_id` column for backward compatibility but stop populating it from package code.
- Make `AutoCreateUserListener` stop updating the host Order’s `client_id` unless explicitly enabled via config.
- Preserve Policy and SuccessAccessValidator behavior via existing `customer_id` / `sumit_customer_id` fallback.
- Update tests and documentation (CHANGELOG, CLIENT_ID_COUPLING_ANALYSIS.md) to reflect the new behavior.

---

## Verification (Code + OpenAPI)

Validated against package source and `sumit-openapi.json`:

### Customer.ExternalIdentifier – SUMIT API only

| Source | Finding |
|--------|---------|
| **sumit-openapi.json** | `ExternalIdentifier` appears under `Details` (customer create/update) and under `Details.Customer` (documents, payments). It is a **request field** sent to SUMIT for customer matching; the API returns `CustomerID` (SUMIT’s ID), not the host’s identifier. |
| **PaymentService.php** (509–512) | Package **forwards** to SUMIT: `$customer['ExternalIdentifier'] = (string) $order->getCustomerId();` – used when building the Customer object for the charge request. Comment: “This helps SUMIT match existing customers”. Correct: ExternalIdentifier is for SUMIT only. |
| **OfficeGuyTransaction.php** (242–244, 263) | **Current bug:** After the API call, the package reads `data_get($request, 'Customer.ExternalIdentifier')` and stores it as `'client_id' => $clientId` in `create()`. So the value sent to SUMIT is **currently** persisted as `client_id`. **Design rule:** ExternalIdentifier must **not** be stored as `client_id`; package persistence must use only `customer_id` / `sumit_customer_id_used`. Phase 2 removes this. |

### Package persistence – only customer_id / sumit_customer_id

| Table / model | Persisted today | After plan |
|---------------|------------------|------------|
| officeguy_transactions | `customer_id` (SUMIT), `sumit_customer_id_used`, **and** `client_id` (from ExternalIdentifier or resolved client) | Keep only `customer_id` and `sumit_customer_id_used`; stop writing `client_id`. |
| officeguy_sumit_webhooks | `customer_id` (from payload), **and** `client_id` (from matchClientIdFromPayload) | Keep only `customer_id`; stop writing `client_id`. |
| officeguy_crm_entities | **and** `client_id` (from matchClientId) | Stop writing `client_id`; link by SUMIT entity/customer ID only. |

**Design statement (to be true after implementation):**

- **Customer.ExternalIdentifier** is forwarded to SUMIT when creating or updating a customer / payment request; it is **not** stored as `client_id` in package tables.
- **Package persistence** relies only on `customer_id` / `sumit_customer_id` (and related SUMIT IDs); `client_id` column is deprecated for new writes.

---

## Phases

### Phase 1: SumitWebhook – Store Only customer_id (SUMIT)

**Dependencies**: None  
**Parallel OK**: No

#### Tasks

- [ ] **1.1** Stop passing `client_id` in `SumitWebhook::createFromRequest()` (~10 min)
  - **Command**: Edit `src/Models/SumitWebhook.php`: remove the call to `matchClientIdFromPayload($payload)` and remove `'client_id' => $clientId` from the `create([...])` array in `createFromRequest()`. Keep `customer_id` from payload as-is.
  - **Verify**: `grep -n "client_id" src/Models/SumitWebhook.php` shows no assignment in `createFromRequest` (only fillable/casts/relationship).
  - **Rollback**: `git checkout -- src/Models/SumitWebhook.php`

- [ ] **1.2** (Optional) Deprecate or keep `matchClientIdFromPayload` for internal use only (~5 min)
  - **Command**: Either remove `matchClientIdFromPayload()` or add a PHPDoc `@deprecated` and do not call it from `createFromRequest()`. Prefer removal of call only (1.1); method can remain for backward compatibility if needed elsewhere.
  - **Verify**: `grep -n "matchClientIdFromPayload" src/Models/SumitWebhook.php` shows no call from `createFromRequest`.
  - **Rollback**: `git checkout -- src/Models/SumitWebhook.php`

- [ ] **1.3** Run SumitWebhook-related tests (~2 min)
  - **Command**: `cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test --filter=SumitWebhook 2>&1`
  - **Verify**: Exit code 0; no failures.
  - **Rollback**: N/A (tests only)

#### Phase 1 Error Handling

If any task fails:

1. Run rollback for 1.1/1.2 if code was changed.
2. Document: `echo "Phase 1 failed: $(date)" >> .claude/context/memory/issues.md` (if that path exists in repo).
3. Do NOT proceed to Phase 2.

#### Phase 1 Verification Gate

```bash
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test --filter=SumitWebhook 2>&1 | tail -5
# Expect: "PASS" or "OK" and exit code 0
```

---

### Phase 2: OfficeGuyTransaction – Stop Writing client_id

**Dependencies**: Phase 1  
**Parallel OK**: No

**Context:** `Customer.ExternalIdentifier` is sent to SUMIT only (PaymentService builds the request; see sumit-openapi.json). It must **not** be stored as `client_id` in package tables. Persist only `customer_id` / `sumit_customer_id_used` from the API response.

#### Tasks

- [ ] **2.1** In `createFromApiResponse()`, remove use of `Customer.ExternalIdentifier` as `clientId` and remove resolution of `$client` from customer model (~15 min)
  - **Command**: Edit `src/Models/OfficeGuyTransaction.php`: in `createFromApiResponse()`, remove the block that sets `$clientId` from `data_get($request, 'Customer.ExternalIdentifier')` and the block that does `$customerModel::where('sumit_customer_id', $sumitCustomerIdUsed)->first()` and `$clientId = $client?->id`. Set `$clientId = null` (or omit `client_id` from the `create()` array). Keep **only** `customer_id` and `sumit_customer_id_used` from response data in `create()`; do not persist ExternalIdentifier as client_id.
  - **Verify**: `grep -A2 "client_id" src/Models/OfficeGuyTransaction.php` in the create array shows `'client_id' => null` or key absent.
  - **Rollback**: `git checkout -- src/Models/OfficeGuyTransaction.php`

- [ ] **2.2** Run OfficeGuyTransaction-related tests (~2 min)
  - **Command**: `cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test --filter=OfficeGuyTransaction 2>&1`
  - **Verify**: Exit code 0.
  - **Rollback**: N/A

#### Phase 2 Error Handling

If any task fails:

1. Run rollback for 2.1.
2. Do NOT proceed to Phase 3.

#### Phase 2 Verification Gate

```bash
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test --filter=OfficeGuyTransaction 2>&1 | tail -5
```

---

### Phase 3: CrmDataService / CrmEntity – Stop Writing client_id

**Dependencies**: Phase 2  
**Parallel OK**: No

#### Tasks

- [ ] **3.1** In `CrmDataService::syncEntityFromSumit` (or equivalent), stop calling `matchClientId` and remove `client_id` from `CrmEntity::updateOrCreate()` (~15 min)
  - **Command**: Edit `src/Services/CrmDataService.php`: where `$clientId = self::matchClientId($entityData, $sumitEntityId)` and `'client_id' => $clientId` are used in `CrmEntity::updateOrCreate()`, remove the `matchClientId` call and remove the `'client_id' => $clientId` key from the attributes array. Ensure `sumit_entity_id` (or existing SUMIT identifier field) remains for linking.
  - **Verify**: `grep -n "client_id" src/Services/CrmDataService.php` shows no assignment to `client_id` in the updateOrCreate array.
  - **Rollback**: `git checkout -- src/Services/CrmDataService.php`

- [ ] **3.2** Run CrmEntity/CrmDataService-related tests (~2 min)
  - **Command**: `cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test --filter=CrmEntity 2>&1`
  - **Verify**: Exit code 0.
  - **Rollback**: N/A

#### Phase 3 Error Handling

If any task fails:

1. Run rollback for 3.1.
2. Do NOT proceed to Phase 4.

#### Phase 3 Verification Gate

```bash
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test --filter=CrmEntity 2>&1 | tail -5
```

---

### Phase 4: AutoCreateUserListener – Optional Order client_id Update

**Dependencies**: Phase 3  
**Parallel OK**: No

#### Tasks

- [ ] **4.1** Make updating the host Order’s `client_id` config-driven (~20 min)
  - **Command**: Edit `src/Listeners/AutoCreateUserListener.php`: add a config key (e.g. `officeguy.auto_create_guest_user.update_order_client_id` default `false`). In `handle()` and `linkOrderToExistingUser()`, only call `$order->update([..., 'client_id' => $client->id ?? null])` when that config is `true`. If the Order model may not have a `client_id` attribute, use `fillable`/attribute check or try/catch to avoid breaking hosts that don’t have the column.
  - **Verify**: `grep -n "client_id\|update_order_client_id" src/Listeners/AutoCreateUserListener.php` shows conditional update.
  - **Rollback**: `git checkout -- src/Listeners/AutoCreateUserListener.php`

- [ ] **4.2** Document the new config in `config/officeguy.php` (or package config) (~5 min)
  - **Command**: Add a comment and default for `update_order_client_id` (or chosen key) in the package config file so hosts know they can opt in.
  - **Verify**: `grep -n "update_order_client_id\|client_id" config/officeguy.php` shows the key and comment.
  - **Rollback**: `git checkout -- config/officeguy.php`

- [ ] **4.3** Run full test suite for the package (~3 min)
  - **Command**: `cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test 2>&1`
  - **Verify**: Exit code 0.
  - **Rollback**: N/A

#### Phase 4 Error Handling

If any task fails:

1. Run rollbacks for 4.1 and 4.2.
2. Do NOT proceed to Phase 5.

#### Phase 4 Verification Gate

```bash
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test 2>&1 | tail -15
```

---

### Phase 5: Policy & SuccessAccessValidator – Rely on customer_id Only (Optional Cleanup)

**Dependencies**: Phase 4  
**Parallel OK**: No

#### Tasks

- [ ] **5.1** (Optional) Simplify `OfficeGuyTransactionPolicy::view()` to use only `customer_id` / `sumit_customer_id` (~10 min)
  - **Command**: Edit `src/Policies/OfficeGuyTransactionPolicy.php`: in `view()`, remove or de-prioritize the branch that checks `$transaction->client_id === $user->client_id`; keep the fallback that uses `$transaction->customer_id` and `$user->sumit_customer_id`. Ensure staff/client/reseller logic still works.
  - **Verify**: `php artisan test --filter=OfficeGuyTransaction 2>&1` passes; manual check that policy allows view by sumit_customer_id.
  - **Rollback**: `git checkout -- src/Policies/OfficeGuyTransactionPolicy.php`

- [ ] **5.2** (Optional) In `SuccessAccessValidator`, treat `client_id` as optional and prefer `customer_id` / `sumit_customer_id` (~10 min)
  - **Command**: Edit `src/Services/SuccessAccessValidator.php`: where ownership is checked via `client_id`, add or prefer check by `customer_id` / `sumit_customer_id` so access works when `client_id` is null.
  - **Verify**: `php artisan test 2>&1` passes; no regression on success page access.
  - **Rollback**: `git checkout -- src/Services/SuccessAccessValidator.php`

#### Phase 5 Error Handling

If any task fails, run rollbacks for 5.1 and 5.2. Phase 5 is optional; Phase 6 can proceed even if Phase 5 is skipped.

#### Phase 5 Verification Gate

```bash
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && php artisan test 2>&1 | tail -5
```

---

### Phase 6: Documentation & CHANGELOG

**Dependencies**: Phase 4 (Phase 5 optional)  
**Parallel OK**: No

#### Tasks

- [ ] **6.1** Update CHANGELOG with breaking-change / deprecation note (~10 min)
  - **Command**: Add an entry under a new version (e.g. 5.1.0 or next) describing: package no longer writes `client_id` to `officeguy_sumit_webhooks`, `officeguy_transactions`, `officeguy_crm_entities`; Order `client_id` update is opt-in via config; host should use `customer_id` / `sumit_customer_id` for linking. Mention CLIENT_ID_COUPLING_ANALYSIS.md and host integration guidelines.
  - **Verify**: `grep -n "client_id\|decoupling\|CHANGELOG" CHANGELOG.md` shows the new entry.
  - **Rollback**: `git checkout -- CHANGELOG.md`

- [ ] **6.2** Add a short “Decoupling applied” note to `docs/CLIENT_ID_COUPLING_ANALYSIS.md` (~5 min)
  - **Command**: At the top or in a new subsection of `docs/CLIENT_ID_COUPLING_ANALYSIS.md`, add a note that this plan was executed: SumitWebhook, OfficeGuyTransaction, CrmDataService no longer write `client_id`; AutoCreateUserListener is opt-in; column remains for backward compatibility.
  - **Verify**: `grep -n "Decoupling applied\|no longer write" docs/CLIENT_ID_COUPLING_ANALYSIS.md` shows the update.
  - **Rollback**: `git checkout -- docs/CLIENT_ID_COUPLING_ANALYSIS.md`

- [ ] **6.3** Run Pint and full test suite (~5 min)
  - **Command**: `cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && vendor/bin/pint --dirty && php artisan test 2>&1`
  - **Verify**: Pint exits 0; tests pass.
  - **Rollback**: `git checkout -- .` for code; revert CHANGELOG/docs if needed.

#### Phase 6 Error Handling

If any task fails, revert the modified docs/CHANGELOG.

#### Phase 6 Verification Gate

```bash
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel && vendor/bin/pint --dirty 2>&1; php artisan test 2>&1 | tail -10
```

---

## Risks

| Risk | Impact | Mitigation | Rollback |
|------|--------|------------|----------|
| Hosts rely on `client_id` for authorization or reporting | High | Document in CHANGELOG and CLIENT_ID_COUPLING_ANALYSIS; Policy already has fallback by `customer_id`/`sumit_customer_id` | Keep `client_id` column; document that package no longer fills it; hosts can backfill from customer model if needed |
| Order model has no `client_id` and listener calls `update()` with it | Medium | In AutoCreateUserListener, only add `client_id` to update array when config is true and optionally check attribute exists | Config default `false`; catch AttributeException if needed |
| Existing tests assert on `client_id` being set | Medium | Run tests after each phase; fix or relax assertions to allow null `client_id` | Revert phase and adjust tests |
| CRM/Webhook consumers expect `client_id` in payload or DB | Low | Document migration path: use `customer_id` (SUMIT) and resolve customer in host via `sumit_customer_id` | N/A (documentation only) |

## Timeline Summary

| Phase | Tasks | Est. Time | Parallel? |
|-------|--------|-----------|-----------|
| 1 – SumitWebhook | 3 | ~17 min | No |
| 2 – OfficeGuyTransaction | 2 | ~17 min | No |
| 3 – CrmDataService/CrmEntity | 2 | ~17 min | No |
| 4 – AutoCreateUserListener | 3 | ~28 min | No |
| 5 – Policy/Validator (optional) | 2 | ~20 min | No |
| 6 – Docs & CHANGELOG | 3 | ~20 min | No |
| **Total** | **15** | **~119 min** | No |

## Success Criteria

- No package code path writes `$client->id` (or host PK) into `officeguy_sumit_webhooks.client_id`, `officeguy_transactions.client_id`, or `officeguy_crm_entities.client_id`.
- Order `client_id` is updated by the package only when the host opts in via config.
- All existing tests pass (or are updated to allow null `client_id` where appropriate).
- CHANGELOG and CLIENT_ID_COUPLING_ANALYSIS.md updated; host integration guidelines remain in place.
