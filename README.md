# SUMIT (OfficeGuy) Payment Gateway for Laravel 12 + Filament v4

**Clone 1:1 של התוסף WooCommerce `woo-payment-gateway-officeguy` עבור Laravel.**

- תשלומים בכרטיס אשראי (PCI modes: no/redirect/yes)
- תשלומי Bit
- תמיכה ב‑Tokens (J2/J5), Authorize Only, תשלומים (עד 36), recurring
- מסמכים (חשבונית/קבלה/תרומה), שילוב PayPal/BlueSnap receipts
- Multivendor & CartFlows מקבילים (לפי מפרט המקור)
- סנכרון מלאי (12/24 שעות/Checkout), ווידג'ט דשבורד (למימוש עתידי)
- ממשק ניהול Filament v4 + Spatie Settings
- דפי לקוח Filament להצגת טרנזקציות/מסמכים/אמצעי תשלום

## התקנה
```bash
composer require officeguy/laravel-sumit-gateway
php artisan vendor:publish --tag=officeguy-settings   # מיגרציית Spatie
php artisan settings:migrate
```

> אם תרצה להעתיק גם קונפיג/מיגרציות/תצוגות: `--tag=officeguy-config`, `--tag=officeguy-migrations`, `--tag=officeguy-views`, `--tag=officeguy-settings`.

## הגדרות (Spatie Settings)
כל ההגדרות נשמרות ב‑Spatie Settings. ניתן לערוך דרך Filament (עמוד **Gateway Settings**) או בקוד.

שדות עיקריים:
- מפתחות חברה: company_id, private_key, public_key
- PCI mode: `no` (PaymentsJS), `redirect`, `yes` (PCI server)
- תשלומים: max_payments, min_amount_for_payments, min_amount_per_payment
- Authorize Only: דגל + אחוז תוספת + מינימום תוספת
- מסמכים: draft_document, email_document, create_order_document, merge_customers, automatic_languages
- Tokens: support_tokens, token_param (J2/J5)
- Bit: bit_enabled
- מלאי: stock_sync_freq (none/12/24), checkout_stock_sync
- לוגים: logging, log_channel, ssl_verify
- מסלולי Redirect: routes.success, routes.failed
- Order binding: order.model או order.resolver (callable)

## מודל Order (Payable)
החבילה דורשת שמודל ההזמנה יממש `OfficeGuy\LaravelSumitGateway\Contracts\Payable`.
דרך מהירה: השתמשו ב‑Trait
```php
class Order extends Model implements Payable {
    use \OfficeGuy\LaravelSumitGateway\Support\Traits\PayableAdapter;
}
```
כדאי להעמיס (eager load) יחסי items/fees.

קונפיג:
```php
'order' => [
    'model' => App\Models\Order::class,
    // או
    'resolver' => fn($id) => App\Models\Order::with('items','fees')->find($id),
],
```

## מסלולים
תחת prefix (ברירת מחדל `officeguy`):
- GET `callback/card` – חזרת Redirect מכרטיס
- POST `webhook/bit` – IPN ל‑Bit
- (אופציונלי) POST `checkout/charge` – מסלול סליקה מובנה (`OFFICEGUY_ENABLE_CHECKOUT_ROUTE=true`)
מסלולי הצלחה/כישלון: מוגדרים ב‑config `routes.success` / `routes.failed` (ברירת מחדל `checkout.success` / `checkout.failed`).

## שימוש ב‑Checkout מובנה
קריאה למסלול `officeguy.checkout.charge` עם פרמטרים:
- `order_id` (חובה)
- `payments_count` (אופציונלי, ברירת מחדל 1)
- `recurring` (bool)
- `token_id` (אופציונלי, לטוקן שמור)
המסלול יחזיר `redirect_url` (אם PCI=redirect) או תשובת הצלחה/שגיאה JSON.

ניתן גם לקרוא ישירות:
```php
$result = PaymentService::processCharge($order, $paymentsCount, $recurring, $redirectMode, $token, $extra);
```

## Filament
- עמוד הגדרות: `OfficeGuySettings` (ניווט: SUMIT Gateway)
- משאבי לקוח: טרנזקציות, מסמכים, אמצעי תשלום (Client panel provider)

## Bit
- הפעלה: enable `bit_enabled` בהגדרות.
- Webhook: POST `officeguy/webhook/bit` מקבל orderid/orderkey/documentid/customerid.

## SSL
ה‑HTTP client משתמש ב‑`ssl_verify` (ברירת מחדל true). לשימוש dev בלבד ניתן לכבות.

## לוגים
`logging` + `log_channel` (ברירת מחדל stack). נתונים רגישים מנוקים מלוגים (מספר כרטיס/CVV).

## מיגרציות נתונים
- טבלאות: transactions, tokens, documents (נלקחו מגרסת ה‑Woo).
- Spatie Settings: `database/settings/*` לאחר publish.

## בדיקות
- phpunit / orchestra testbench מומלצים. החבילה כוללת בסיס מיגרציות; יש להגדיר מודל Order דמה ל‑Payable.

## סטטוס
- Stock Sync: כולל שירות + Job + Command (`sumit:stock-sync`) עם callback התאמה אישית לעדכון מלאי (config `stock.update_callback`). cron לפי `stock_sync_freq`.
- Multivendor / CartFlows: נקודות הרחבה קיימות; שילוב מתבצע ע"י resolver שמחזיר VendorCredentials פר מוצר וקריאה ל-`/billing/payments/multivendorcharge/` (ראה docs). CartFlows ניתן לממש דרך Token + Child Orders עם PaymentService.
- אירועים: החבילה משדרת אירועים (`PaymentCompleted`, `PaymentFailed`, `DocumentCreated`, `StockSynced`, `BitPaymentCompleted`) לחיבור לוגיקה משלך.

## רישיון
MIT
