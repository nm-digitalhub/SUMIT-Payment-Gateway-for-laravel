# Phase 1 — Data Model Refinement (Executed Changes)

## Scope & Constraints (Applied)
- ✅ DTO updates only
- ✅ `CheckoutIntent::fromRequest()` updated to collect data (no logic)
- ❌ No Controller changes
- ❌ No PaymentService changes
- ❌ No Resolver changes
- ❌ No UI/Blade changes
- ❌ No Validation changes
- ❌ No `config()` usage
- ❌ No `RequestHelpers` usage

---

## Summary of Changes
Phase 1 completed by **expanding `PaymentPreferences` DTO** to capture all raw payment inputs coming from UI, and **updating `CheckoutIntent::fromRequest()`** to explicitly collect DTO instances without adding any logic.

---

## Change Log (File + Exact Edits)

### 1) Expanded `PaymentPreferences` DTO
**File:** `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/PaymentPreferences.php`

**Added properties to constructor** (raw fields + alternates):
- `paymentTokenChoice`
- `singleUseToken`
- `cvv`, `ogCvv`
- `citizenId`, `ogCitizenId`
- `cardNumber`, `ogCardNumber`
- `expMonth`, `ogExpMonth`
- `expYear`, `ogExpYear`
- `paymentsCountRaw`, `ogPaymentsCount`

**Updated `fromRequest()`** to read these fields directly from Request:
- No conditions
- No config calls
- No token/PCI logic

**Updated `fromArray()`** to support serialization/deserialization of the new fields.

**Updated `toArray()`** to persist the new fields.

---

### 2) Updated `CheckoutIntent::fromRequest()`
**File:** `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/CheckoutIntent.php`

- Now creates `$customer = CustomerData::fromRequest($request)`
- Now creates `$payment = PaymentPreferences::fromRequest($request)`
- Returns DTOs directly, no logic added.

---

## Fields Now Captured in `PaymentPreferences`

### Existing (unchanged)
- `method`
- `installments`
- `tokenId`
- `saveCard`

### New (raw inputs captured as-is)
- `paymentTokenChoice`
- `singleUseToken`
- `cvv`, `ogCvv`
- `citizenId`, `ogCitizenId`
- `cardNumber`, `ogCardNumber`
- `expMonth`, `ogExpMonth`
- `expYear`, `ogExpYear`
- `paymentsCountRaw`, `ogPaymentsCount`

---

## Compliance Checklist (Phase 1)
- [x] No `if (pci …)` logic added
- [x] No `config()` usage added
- [x] No `RequestHelpers` usage added
- [x] No Controller edits
- [x] No PaymentService edits
- [x] No Resolver edits
- [x] No UI/Blade edits
- [x] No Validation edits

---

## Formatting / QA
- `vendor/bin/pint --dirty` executed and passed.

---

## Notes
- All modifications are backward compatible (pure data collection only).
- No behavior changes introduced.

---

**Status:** Phase 1 complete. Awaiting approval for Phase 2.
