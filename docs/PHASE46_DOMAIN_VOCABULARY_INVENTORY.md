# Phase 4.6 — Domain Vocabulary Elimination — Inventory

**Scope:** `src/`, `config/`, `routes/` (excluding docs/tests).

## 1. Scan: esim, package, digital (case-insensitive) + payable-type branching

| File | Line | Snippet | Classification | Action |
|------|------|---------|----------------|--------|
| `src/Handlers/DigitalProductFulfillmentHandler.php` | 128 | `'esim' => $this->handleEsim(...)` | Fulfillment/side effects | MUST REMOVE FROM CORE |
| `src/Handlers/DigitalProductFulfillmentHandler.php` | 241 | `str_contains($className, 'Esim') => 'esim'` | Fulfillment/side effects | MUST REMOVE FROM CORE |
| `src/Handlers/DigitalProductFulfillmentHandler.php` | 146, 149, 188, etc. | handleEsim, handleDigitalDownload, "eSIM", "digital" | Fulfillment/side effects | MUST REMOVE FROM CORE |
| `src/Services/CheckoutViewResolver.php` | 16-17, 32-33, 60, 119 | esim.blade.php, digital.blade.php, getPayableType()->checkoutTemplate(), 'digital' | View selection / templating | MUST REMOVE FROM CORE |
| `src/Services/CheckoutViewResolver.php` | 117-121 | getAvailableTemplates() 'digital', 'infrastructure', etc. | View selection | MUST REMOVE FROM CORE |
| `src/OfficeGuyServiceProvider.php` | 454-456 | Register DigitalProduct, Infrastructure, Subscription handlers | Fulfillment | MUST REMOVE FROM CORE |
| `src/Services/ServiceDataFactory.php` | 94 | `PayableType::DIGITAL_PRODUCT => 'digital'` | Internal mapping | MUST REMOVE / make generic |
| `src/Support/Traits/HasPayableType.php` | 28, 42, 116 | Example 'esim', 'digital' template, DIGITAL_PRODUCT message | Docs/example + type behavior | REMOVE domain examples from core |
| `src/Enums/PayableType.php` | 35, 79, 93, 110, 144, etc. | DIGITAL_PRODUCT, labels, checkoutTemplate() 'digital' | Contract (keep for BC) | Enum stays; package must not branch on type for views/fulfillment |
| `src/Support/Traits/HasEloquentLineItems.php` | 46, 98, 104 | package_id, PKG- | Line items (generic field name) | MAY LIVE – "package_id" is generic identifier name |
| `src/DataTransferObjects/PackageVersion.php` | 10, 38 | "package" (Packagist package) | N/A – package = Laravel package | IGNORE |
| `src/Services/PackageVersionService.php` | 15, 40, 99-100, etc. | "package" (Packagist) | N/A | IGNORE |
| `src/Handlers/InfrastructureFulfillmentHandler.php` | full file | Type-specific handler | Fulfillment | MUST REMOVE FROM CORE |
| `src/Handlers/SubscriptionFulfillmentHandler.php` | full file | Type-specific handler | Fulfillment | MUST REMOVE FROM CORE |

## 2. Payable-type branching (instanceof / type-name checks)

- **PublicCheckoutController:** `$payable instanceof Payable` — contract check, keep.
- **FulfillmentDispatcher:** Dispatches by `getPayableType()` to handler classes. After Step 2, all types map to GenericFulfillmentHandler; no branching on type name inside handlers.
- **CheckoutViewResolver:** Uses `getPayableType()->checkoutTemplate()` and `service_type` — REMOVE; replace with config callable + default.
- **DigitalProductFulfillmentHandler:** `getProductType()` + match on 'esim', 'software_license', 'digital_download' — REMOVE (delete handler).

## 3. Config keys encoding product types

- `checkout_models` (package, esim) — already removed in Phase 4.5.
- No other config arrays keyed by esim/package in `config/officeguy.php` after 4.5.
- Add `officeguy.checkout.view_resolver` (callable), single default view.

## 4. Summary

- **MUST REMOVE FROM CORE:** Type-specific fulfillment handlers (Digital, Infrastructure, Subscription); view resolver type-based logic; domain strings (esim, package, digital) in handler/view code; ServiceDataFactory type→template mapping for view; HasPayableType domain examples.
- **MAY LIVE IN OPTIONAL MODULE:** Host can register custom handlers and view resolver; PayableType enum remains for contract but core does not use it for fulfillment or view selection.
- **Docs-only / N/A:** PackageVersion*, comments "the package" (Laravel package), line item field name `package_id` as generic ID.
