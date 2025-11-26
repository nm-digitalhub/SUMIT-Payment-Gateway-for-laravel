# SUMIT (OfficeGuy) Payment Gateway for Laravel 12 + Filament v4

**Clone 1:1 של התוסף WooCommerce `woo-payment-gateway-officeguy` עבור Laravel.**

- תשלומים בכרטיס אשראי (PCI modes: no/redirect/yes)
- תשלומי Bit
- תמיכה ב‑Tokens (J2/J5), Authorize Only, תשלומים (עד 36), recurring
- מסמכים (חשבונית/קבלה/תרומה), שילוב PayPal/BlueSnap receipts
- Multivendor & CartFlows מקבילים (לפי מפרט המקור)
- סנכרון מלאי (12/24 שעות/Checkout), ווידג'ט דשבורד (למימוש עתידי)
- ממשק ניהול Filament v4
- דפי לקוח Filament להצגת טרנזקציות/מסמכים/אמצעי תשלום

## התקנה
```bash
composer require officeguy/laravel-sumit-gateway
php artisan migrate   # יריץ את כל מיגרציות החבילה
```

> אם תרצה להעתיק גם קונפיג/מיגרציות/תצוגות: `--tag=officeguy-config`, `--tag=officeguy-migrations`, `--tag=officeguy-views`. ראה [קבצים לפרסום](#קבצים-לפרסום-publishable-assets) לפרטים נוספים.

## הגדרות
כל ההגדרות נשמרות במסד הנתונים (טבלת `officeguy_settings`) עם fallback לקובץ config. ניתן לערוך דרך Filament (עמוד **Gateway Settings**) או בקוד באמצעות `SettingsService`.

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
- טבלאות: `officeguy_transactions`, `officeguy_tokens`, `officeguy_documents`, `officeguy_settings`, `vendor_credentials`, `subscriptions`.
- המיגרציות נטענות אוטומטית מהחבילה. להעתקה מקומית: `php artisan vendor:publish --tag=officeguy-migrations`.

## בדיקות
- phpunit / orchestra testbench מומלצים. החבילה כוללת בסיס מיגרציות; יש להגדיר מודל Order דמה ל‑Payable.

## סטטוס
- Stock Sync: כולל שירות + Job + Command (`sumit:stock-sync`) עם callback התאמה אישית לעדכון מלאי (config `stock.update_callback`). cron לפי `stock_sync_freq`.
- Multivendor / CartFlows: נקודות הרחבה קיימות; שילוב מתבצע ע"י resolver שמחזיר VendorCredentials פר מוצר וקריאה ל-`/billing/payments/multivendorcharge/` (ראה docs). CartFlows ניתן לממש דרך Token + Child Orders עם PaymentService.
- אירועים: החבילה משדרת אירועים (`PaymentCompleted`, `PaymentFailed`, `DocumentCreated`, `StockSynced`, `BitPaymentCompleted`) לחיבור לוגיקה משלך.

## Multi-Vendor
תמיכה בריבוי מוכרים עם credentials נפרדים לכל ספק:
```php
// שמירת credentials לספק
VendorCredential::create([
    'vendor_type' => get_class($vendor),
    'vendor_id' => $vendor->id,
    'company_id' => '12345',
    'api_key' => 'your-api-key',
]);

// חיוב הזמנה מרובת ספקים
$result = MultiVendorPaymentService::processMultiVendorCharge($order, $paymentsCount);
```

## מנויים (Subscriptions)
ניהול מנויים וחיובים חוזרים:
```php
// יצירת מנוי
$subscription = SubscriptionService::create(
    $user,              // מנוי
    'תוכנית חודשית',    // שם
    99.00,              // סכום
    'ILS',              // מטבע
    1,                  // אינטרוול בחודשים
    12,                 // מספר חיובים (null = ללא הגבלה)
    $tokenId            // טוקן לתשלום
);

// חיוב ראשוני
$result = SubscriptionService::processInitialCharge($subscription);

// חיוב ידני
$result = SubscriptionService::processRecurringCharge($subscription);
```

### תזמון חיובים חוזרים (Task Scheduling)
הפקודה `sumit:process-recurring-payments` מעבדת את כל המנויים שהגיע זמן חיובם.

הוסף ל‑`routes/console.php` או `app/Console/Kernel.php`:
```php
use Illuminate\Support\Facades\Schedule;

// חיוב יומי
Schedule::command('sumit:process-recurring-payments')->daily();

// או חיוב כל שעה
Schedule::command('sumit:process-recurring-payments')->hourly();

// עם דיווח על כשלונות
Schedule::command('sumit:process-recurring-payments')
    ->daily()
    ->emailOutputOnFailure('admin@example.com');
```

הרצה ידנית:
```bash
# הרצה כ-job (אסינכרוני)
php artisan sumit:process-recurring-payments

# הרצה סינכרונית
php artisan sumit:process-recurring-payments --sync

# עיבוד מנוי ספציפי
php artisan sumit:process-recurring-payments --subscription=123
```

## תרומות (Donations)
תמיכה במוצרי תרומה עם קבלת תרומה אוטומטית:
```php
// בדיקה אם עגלה מכילה תרומות ומוצרים רגילים (אסור לשלב)
$validation = DonationService::validateCart($order);
if (!$validation['valid']) {
    return redirect()->back()->with('error', $validation['message']);
}

// קבלת סוג המסמך (DonationReceipt לתרומות)
$docType = DonationService::getDocumentType($order);
```

## Upsell / CartFlows
חיוב מוצרי upsell באמצעות טוקן מהחיוב הראשי:
```php
// חיוב עם טוקן ידוע
$result = UpsellService::processUpsellCharge($upsellOrder, $token, $parentOrderId);

// חיוב עם זיהוי אוטומטי של הטוקן
$result = UpsellService::processUpsellWithAutoToken($upsellOrder, $parentOrderId, $customer);
```

## אירועים (Events)
החבילה משדרת את האירועים הבאים:

| אירוע | תיאור |
|-------|--------|
| `PaymentCompleted` | תשלום הצליח |
| `PaymentFailed` | תשלום נכשל |
| `DocumentCreated` | מסמך נוצר |
| `StockSynced` | מלאי סונכרן |
| `BitPaymentCompleted` | תשלום Bit הושלם |
| `SubscriptionCreated` | מנוי נוצר |
| `SubscriptionCharged` | מנוי חויב |
| `SubscriptionChargesFailed` | חיוב מנוי נכשל |
| `SubscriptionCancelled` | מנוי בוטל |
| `MultiVendorPaymentCompleted` | תשלום מרובה-ספקים הצליח |
| `MultiVendorPaymentFailed` | תשלום מרובה-ספקים נכשל |
| `UpsellPaymentCompleted` | תשלום upsell הצליח |
| `UpsellPaymentFailed` | תשלום upsell נכשל |

## קבצים לפרסום (Publishable Assets)

החבילה מציעה מספר קבצים לפרסום (publish) להתאמה אישית. להלן פירוט כל קובץ, מה הוא מכיל, ומתי כדאי להשתמש בו.

### פקודת Publish כללית
```bash
# פרסום כל הקבצים בבת אחת
php artisan vendor:publish --provider="OfficeGuy\LaravelSumitGateway\OfficeGuyServiceProvider"

# או פרסום קבצים ספציפיים לפי תגית (tag)
php artisan vendor:publish --tag=<tag-name>
```

### 1. קונפיגורציה (`--tag=officeguy-config`)

```bash
php artisan vendor:publish --tag=officeguy-config
```

**מיקום:** `config/officeguy.php`

**מה מכיל:**
- הגדרות חברה (Company ID, API Keys)
- מצב PCI (no/redirect/yes)
- הגדרות תשלומים ותשלומים מחולקים (installments)
- הגדרות Bit
- הגדרות מסמכים
- הגדרות טוקנים
- הגדרות מנויים, תרומות, Multi-Vendor ו-Upsell
- הגדרות נתיבים (Routes)
- הגדרות מלאי
- הגדרות לוגים ו-SSL

**מתי להשתמש:**
- כאשר רוצים להגדיר ערכים קבועים שאינם משתנים מ-.env
- כאשר צריך להגדיר resolvers או callbacks מותאמים אישית (למשל `order.resolver`, `stock.update_callback`)
- כאשר רוצים לשנות את רשימת המטבעות הנתמכים
- כאשר צריך להגדיר middleware מותאם אישית לנתיבים

**דוגמה להתאמה אישית:**
```php
// config/officeguy.php
return [
    'order' => [
        'resolver' => fn($id) => \App\Models\Order::with(['items', 'fees', 'customer'])->find($id),
    ],
    'stock' => [
        'update_callback' => fn(array $stockItem) => \App\Services\InventoryService::updateStock($stockItem),
    ],
    'multivendor' => [
        'enabled' => true,
        'vendor_resolver' => fn(array $item) => \App\Models\Vendor::find($item['vendor_id']),
    ],
];
```

### 2. מיגרציות (`--tag=officeguy-migrations`)

```bash
php artisan vendor:publish --tag=officeguy-migrations
```

**מיקום:** `database/migrations/`

**מה מכיל:**
- `create_officeguy_transactions_table` - טבלת טרנזקציות
- `create_officeguy_tokens_table` - טבלת טוקנים (כרטיסי אשראי שמורים)
- `create_officeguy_documents_table` - טבלת מסמכים (חשבוניות/קבלות)
- `create_officeguy_settings_table` - טבלת הגדרות
- `create_vendor_credentials_table` - טבלת credentials לספקים (Multi-Vendor)
- `create_subscriptions_table` - טבלת מנויים
- `add_donation_and_vendor_fields` - שדות נוספים לתרומות וספקים

**מתי להשתמש:**
- כאשר רוצים לשנות את מבנה הטבלאות (הוספת שדות, שינוי indexes)
- כאשר צריך להתאים שמות טבלאות לקונבנציות הפרויקט
- כאשר רוצים לשלב עם מיגרציות קיימות בפרויקט
- כאשר צריך שליטה על סדר הרצת המיגרציות

**הערה חשובה:** לאחר פרסום המיגרציות, החבילה תמשיך לטעון את המיגרציות שלה מ-`vendor/`. כדי למנוע כפילויות, ודאו שאתם לא מריצים את אותן מיגרציות פעמיים.

### 3. תצוגות (`--tag=officeguy-views`)

```bash
php artisan vendor:publish --tag=officeguy-views
```

**מיקום:** `resources/views/vendor/officeguy/`

**מה מכיל:**
- **`components/payment-form.blade.php`** - טופס תשלום עם:
  - שדות כרטיס אשראי (מספר, תוקף, CVV, ת.ז.)
  - בחירת אמצעי תשלום שמור (טוקן)
  - בחירת מספר תשלומים
  - תמיכה ב-RTL וולידציה צד-לקוח עם Alpine.js
- **`pages/checkout.blade.php`** - עמוד תשלום ציבורי מלא עם:
  - תצוגת סיכום הזמנה
  - פרטי לקוח
  - בחירת אמצעי תשלום (כרטיס/Bit)
  - תמיכה בתשלומים
  - עיצוב מודרני עם Tailwind CSS
  - תמיכה מלאה ב-RTL
- **`filament/pages/officeguy-settings.blade.php`** - עמוד הגדרות ב-Filament Admin
- **`filament/client/payment-methods/hosted-token-form.blade.php`** - טופס ניהול אמצעי תשלום ללקוח

**מתי להשתמש:**
- כאשר רוצים לשנות את עיצוב טופס התשלום
- כאשר צריך להתאים את הטופס לעיצוב הייחודי של האתר
- כאשר רוצים להוסיף שדות נוספים לטופס
- כאשר צריך לשנות את הטקסטים או התרגומים
- כאשר רוצים לשנות את לוגיקת הולידציה בצד הלקוח

**דוגמה להתאמת טופס תשלום:**
```blade
{{-- resources/views/vendor/officeguy/components/payment-form.blade.php --}}
<div class="my-custom-payment-form">
    {{-- הוספת לוגו חברה --}}
    <div class="company-logo mb-4">
        <img src="{{ asset('images/logo.svg') }}" alt="Logo">
    </div>
    
    {{-- שאר הטופס... --}}
</div>
```

### טבלת סיכום

| תגית | מיקום יעד | שימוש עיקרי |
|------|-----------|-------------|
| `officeguy-config` | `config/officeguy.php` | הגדרות API, תשלומים, resolvers |
| `officeguy-migrations` | `database/migrations/` | התאמת מבנה מסד נתונים |
| `officeguy-views` | `resources/views/vendor/officeguy/` | התאמת עיצוב וממשק משתמש |

### העתקה סלקטיבית

ניתן לפרסם מספר תגיות בבת אחת:
```bash
# פרסום קונפיג ותצוגות בלבד
php artisan vendor:publish --tag=officeguy-config --tag=officeguy-views
```

## עמוד תשלום ציבורי (Public Checkout Page)

החבילה מספקת עמוד תשלום ציבורי שניתן לשייך לכל מודל המממש את הממשק `Payable`. זה מאפשר ליצור קישורי תשלום לכל סוג של מוצר, שירות או הזמנה במערכת.

### הפעלה

ניתן להפעיל את עמוד התשלום הציבורי בשתי דרכים:

**1. דרך Admin Panel (מומלץ):**

גשו לעמוד ההגדרות ב-Filament Admin Panel:
- נווטו ל-**SUMIT Gateway** > **Gateway Settings**
- מצאו את הסעיף **"Public Checkout Page"**
- הפעילו את **"Enable Public Checkout"**
- הגדירו את **"Payable Model Class"** עם שם המודל המלא (לדוגמה: `App\Models\Order`)
- ניתן גם להגדיר נתיב מותאם אישית

**2. דרך קובץ .env:**

```env
OFFICEGUY_ENABLE_PUBLIC_CHECKOUT=true
OFFICEGUY_ORDER_MODEL=App\Models\Order
```

### שימוש

לאחר ההפעלה, ניתן לגשת לעמוד התשלום בכתובת:
```
GET /officeguy/checkout/{id}
```

כאשר `{id}` הוא המזהה של המודל ה-Payable (למשל מזהה הזמנה).

### דוגמה - יצירת קישור תשלום

```php
// יצירת קישור תשלום להזמנה
$order = Order::find(123);
$checkoutUrl = route('officeguy.public.checkout', ['id' => $order->id]);

// שליחת הקישור ללקוח
Mail::to($order->customer_email)->send(new PaymentLinkEmail($checkoutUrl));
```

### התאמה אישית של המודל

יש שתי דרכים לחבר את המודל שלכם לעמוד התשלום:

**אפשרות 1: מיפוי שדות מ-Admin Panel (ללא שינוי קוד)**

ניתן לחבר כל מודל קיים **מבלי לשנות את הקוד שלו**. פשוט הגדירו את מיפוי השדות ב-Admin Panel:

1. גשו ל-**SUMIT Gateway** > **Gateway Settings** > **Field Mapping**
2. הזינו את שמות השדות במודל שלכם:
   - **Amount Field** - שדה הסכום (לדוגמה: `total`, `price`, `amount`)
   - **Currency Field** - שדה המטבע (לדוגמה: `currency`) או השאירו ריק עבור ILS
   - **Customer Name Field** - שדה שם הלקוח
   - **Customer Email Field** - שדה האימייל
   - **Customer Phone Field** - שדה הטלפון
   - **Description Field** - שדה תיאור הפריט

המערכת תעטוף אוטומטית את המודל שלכם ותמפה את השדות.

**אפשרות 2: מימוש ממשק Payable (למודלים מורכבים)**

```php
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Support\Traits\PayableAdapter;

class Order extends Model implements Payable
{
    use PayableAdapter;
    
    // או מימוש ידני של המתודות
}
```

### התאמת העיצוב

פרסמו את התצוגות והתאימו את `pages/checkout.blade.php`:

```bash
php artisan vendor:publish --tag=officeguy-views
```

לאחר מכן ערכו את הקובץ `resources/views/vendor/officeguy/pages/checkout.blade.php` להתאמה לעיצוב האתר שלכם.

### משתנים זמינים בתצוגה

| משתנה | תיאור |
|-------|--------|
| `$payable` | אובייקט ה-Payable (הזמנה/מוצר) |
| `$settings` | הגדרות שער התשלום |
| `$maxPayments` | מספר תשלומים מקסימלי |
| `$bitEnabled` | האם Bit מופעל |
| `$supportTokens` | האם שמירת כרטיסים מופעלת |
| `$savedTokens` | אוסף כרטיסים שמורים (למשתמש מחובר) |
| `$currency` | קוד מטבע (ILS, USD וכו') |
| `$currencySymbol` | סימן מטבע (₪, $ וכו') |
| `$checkoutUrl` | כתובת לשליחת הטופס |

### Resolver מותאם אישית

ניתן להגדיר resolver מותאם אישית בקונפיגורציה:

```php
// config/officeguy.php
'order' => [
    'resolver' => fn($id) => \App\Models\Product::with('prices')->find($id),
],
```

## רישיון
MIT
