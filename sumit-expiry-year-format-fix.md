# SUMIT Expiration Year Format Fix (Package Only)

## Requirement (SUMIT)
- `ExpirationYear` and `CreditCard_ExpirationYear` **must be 4 digits** (YYYY).
- `ExpirationMonth` and `CreditCard_ExpirationMonth` are **1–12**.

Source (inside package):
- `vendor/officeguy/laravel-sumit-gateway/sumit-openapi.json` — request schema for tokenization and PaymentMethod definitions.

## Where the package sends expiry to SUMIT
1. `CheckoutIntentResolver::buildPaymentMethodPayload()` (PCI flow)
   - File: `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutIntentResolver.php`
   - Lines: 63–98
   - Previously sent `CreditCard_ExpirationYear` **as-is** from request.

2. `TokenService::getTokenRequest()` (PCI tokenization)
   - File: `vendor/officeguy/laravel-sumit-gateway/src/Services/TokenService.php`
   - Lines: 14–39
   - Previously sent `ExpirationYear` **as-is** from request.

3. `TokenService::getPaymentMethodPCI()` (PCI payment method)
   - File: `vendor/officeguy/laravel-sumit-gateway/src/Services/TokenService.php`
   - Lines: 119–131
   - Previously sent `CreditCard_ExpirationYear` **as-is** from request.

## Fix Implemented (Package Only)
### New helper
- File: `vendor/officeguy/laravel-sumit-gateway/src/Support/PaymentFormat.php`
- Lines: 1–31
- `normalizeExpirationYear()` converts 1–2 digit year to **2000 + YY**, keeps 4-digit as-is.

### Updated send paths
- `CheckoutIntentResolver::buildPaymentMethodPayload()`
  - Now calls `PaymentFormat::normalizeExpirationYear(...)` before sending to SUMIT.

- `TokenService::getTokenRequest()`
  - Now calls `PaymentFormat::normalizeExpirationYear(...)` before sending to SUMIT.

- `TokenService::getPaymentMethodPCI()`
  - Now calls `PaymentFormat::normalizeExpirationYear(...)` before sending to SUMIT.

## Manual Diff (package is not a git repo)
```diff
+ // new helper
+ final class PaymentFormat
+ {
+     public static function normalizeExpirationYear(string|int|null $year): ?string
+     {
+         if ($year === null) {
+             return null;
+         }
+         $yearString = trim((string) $year);
+         if ($yearString === '') {
+             return null;
+         }
+         $digits = preg_replace('/\D+/', '', $yearString);
+         if ($digits === '') {
+             return null;
+         }
+         if (strlen($digits) <= 2) {
+             return (string) (2000 + (int) $digits);
+         }
+         return $digits;
+     }
+ }
```

```diff
- use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
+ use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
+ use OfficeGuy\LaravelSumitGateway\Support\PaymentFormat;

- $year = $intent->payment->ogExpYear ?? $intent->payment->expYear;
+ $year = PaymentFormat::normalizeExpirationYear(
+     $intent->payment->ogExpYear ?? $intent->payment->expYear
+ );
```

```diff
- use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;
+ use OfficeGuy\LaravelSumitGateway\Support\PaymentFormat;
+ use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;

- $month = (int) RequestHelpers::post('og-expmonth');
+ $month = (int) RequestHelpers::post('og-expmonth');
+ $year = PaymentFormat::normalizeExpirationYear(RequestHelpers::post('og-expyear'));

- 'ExpirationYear'  => RequestHelpers::post('og-expyear'),
+ 'ExpirationYear'  => $year,

- $month = (int) RequestHelpers::post('og-expmonth');
+ $month = (int) RequestHelpers::post('og-expmonth');
+ $year = PaymentFormat::normalizeExpirationYear(RequestHelpers::post('og-expyear'));

- 'CreditCard_ExpirationYear'  => RequestHelpers::post('og-expyear'),
+ 'CreditCard_ExpirationYear'  => $year,
```

## Files + Line Numbers
- `vendor/officeguy/laravel-sumit-gateway/src/Support/PaymentFormat.php:1`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutIntentResolver.php:10,77–97`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/TokenService.php:8,22–32,119–129`

## Tooling
- Ran: `vendor/bin/pint --dirty`

## Result
- All outbound SUMIT requests from the **package** now send `ExpirationYear` / `CreditCard_ExpirationYear` as **4-digit** year.
- Input of `YY` is normalized to `20YY`. Input of `YYYY` passes through unchanged.

## Runtime Verification (Tinker)
Command executed from `/var/www/vhosts/nm-digitalhub.com/httpdocs`:
```
php artisan tinker --execute "<script>"
```

Output (with `og-expyear = 25`):
```
--- Resolved PaymentMethodPayload ---
Array
(
    [CreditCard_Number] => 4111111111111111
    [CreditCard_CVV] => 123
    [CreditCard_CitizenID] => 123456789
    [CreditCard_ExpirationMonth] => 03
    [CreditCard_ExpirationYear] => 2025
    [Type] => 1
)

--- TokenService::getTokenRequest('yes') ---
Array
(
    [ParamJ] => 5
    [Amount] => 1
    [Credentials] => Array
        (
            [CompanyID] =>
            [APIKey] =>
        )

    [CardNumber] => 4111111111111111
    [CVV] => 123
    [CitizenID] => 123456789
    [ExpirationMonth] => 03
    [ExpirationYear] => 2025
)

--- TokenService::getPaymentMethodPCI() ---
Array
(
    [CreditCard_Number] => 4111111111111111
    [CreditCard_CVV] => 123
    [CreditCard_CitizenID] => 123456789
    [CreditCard_ExpirationMonth] => 03
    [CreditCard_ExpirationYear] => 2025
    [Type] => 1
)
```
