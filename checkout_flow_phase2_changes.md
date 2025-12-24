# Phase 2 — CheckoutIntentResolver Decisions (Executed Changes)

## Scope
- ✅ Resolver extended (decision data prepared)
- ✅ ResolvedPaymentIntent expanded
- ❌ No Controller changes
- ❌ No PaymentService changes
- ❌ No UI/Blade changes
- ❌ No Validation changes

---

## Summary
Phase 2 adds **execution-ready decision data** to `ResolvedPaymentIntent` and **computes it in `CheckoutIntentResolver`** without changing controller or service behavior. Existing runtime behavior remains unchanged because the new fields are not yet consumed by `PaymentService`.

---

## Files Changed

### 1) ResolvedPaymentIntent — new execution fields
**File:** `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/ResolvedPaymentIntent.php`

**Added properties:**
- `pciMode` (string)
- `paymentMethodPayload` (?array)
- `redirectUrls` (?array)

These fields are **pure data** that will be used by `PaymentService` in Phase 3.

---

### 2) CheckoutIntentResolver — compute execution data
**File:** `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutIntentResolver.php`

**Added imports:**
- `TokenService`

**New computed values:**
- `pciMode` from config (`officeguy.pci`)
- `paymentMethodPayload` (built from PaymentPreferences + token)
- `redirectUrls` (success/cancel routes when `pciMode === 'redirect'`)

**New helper methods:**
- `buildPaymentMethodPayload()`
  - Bit → `null`
  - Saved token → `TokenService::getPaymentMethodFromToken()` using CVV from DTO
  - PCI mode (`yes`) → builds payload from DTO fields (`og_*` preferred)
  - PaymentsJS → `SingleUseToken` payload
- `buildRedirectUrls()`
  - Only returns URLs when `pciMode === 'redirect'`

**Note:** `redirectMode` remains **unchanged** (still based on Bit only) to avoid behavior change before Phase 3/4.

---

## Data Mapping Used by Resolver
The resolver now uses **only DTO data** (no RequestHelpers) to create payloads:
- `payment.singleUseToken` → `SingleUseToken`
- `payment.ogCardNumber` / `payment.cardNumber`
- `payment.ogExpMonth` / `payment.expMonth`
- `payment.ogExpYear` / `payment.expYear`
- `payment.ogCvv` / `payment.cvv`
- `payment.ogCitizenId` / `payment.citizenId`

---

## Compliance Checklist (Phase 2)
- [x] No Controller edits
- [x] No PaymentService edits
- [x] No UI/Blade edits
- [x] No Validation edits
- [x] No RequestHelpers usage added in Resolver

---

## Formatting / QA
- `vendor/bin/pint --dirty` executed and passed.

---

## Status
Phase 2 complete. Awaiting approval to move to Phase 3 (PaymentService execution-only refactor).
