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

## תוכן עניינים

- [התקנה](#התקנה)
- [הגדרות](#הגדרות)
- [עמוד תשלום](#עמוד-תשלום)
- [שדות ת"ז ו-CVV](#שדות-תז-ו-cvv)
- [מסמכים](#מסמכים)
- [סוגי תשלומים](#סוגי-תשלומים)
- [תשלומים מחולקים](#תשלומים-מחולקים-installments)
- [תפיסת מסגרת (Authorize Only)](#תפיסת-מסגרת-authorize-only)
- [מצב טסט](#מצב-טסט)
- [שמירת פרטי אשראי](#שמירת-פרטי-אשראי-tokens)
- [הוראות קבע ומנויים](#הוראות-קבע-ומנויים-subscriptions)
- [מלאי](#מלאי-stock-management)
- [Bit ו-Redirect](#bit-ו-redirect)
- [מיזוג לקוחות](#מיזוג-לקוחות-אוטומטי)
- [Multi-Vendor](#multi-vendor)
- [תרומות](#תרומות-donations)
- [Upsell / CartFlows](#upsell--cartflows)
- [אירועים](#אירועים-events)
- [Custom Event Webhooks](#custom-event-webhooks)
- [Webhook Events Resource](#webhook-events-resource-admin-panel)
- [קבלת Webhooks מ-SUMIT](#קבלת-webhooks-מ-sumit-incoming-webhooks)
- [מיגרציות נתונים](#מיגרציות-נתונים)
- [בדיקות](#בדיקות)
- [קבצים לפרסום](#קבצים-לפרסום-publishable-assets)

## התקנה
```bash
composer require officeguy/laravel-sumit-gateway
php artisan migrate   # יריץ את כל מיגרציות החבילה
```

> אם תרצה להעתיק גם קונפיג/מיגרציות/תצוגות: `--tag=officeguy-config`, `--tag=officeguy-migrations`, `--tag=officeguy-views`. ראה [קבצים לפרסום](#קבצים-לפרסום-publishable-assets) לפרטים נוספים.

## הגדרות

כל ההגדרות נשמרות במסד הנתונים (טבלת `officeguy_settings`) עם fallback לקובץ config. ניתן לערוך דרך Filament (עמוד **Gateway Settings**) או בקוד באמצעות `SettingsService`.

### גישה לעמוד ההגדרות
נווטו ל-**SUMIT Gateway** > **Gateway Settings** ב-Admin Panel.

### שדות עיקריים
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

### התאמת נתיבים (Route Configuration)

ניתן להתאים את כל נתיבי החבילה ישירות מה-Admin Panel:

**ב-Admin Panel:**
נווטו ל-**SUMIT Gateway** > **Gateway Settings** > **Route Configuration**

**נתיבים ניתנים להתאמה:**

| הגדרה | ברירת מחדל | תיאור |
|-------|------------|--------|
| Route Prefix | `officeguy` | קידומת לכל הנתיבים |
| Card Callback | `callback/card` | חזרה מתשלום בכרטיס |
| Bit Webhook | `webhook/bit` | קבלת IPN מ-Bit |
| SUMIT Webhook | `webhook/sumit` | קבלת webhooks מ-SUMIT |
| Document Download | `documents/{document}` | הורדת מסמכים |
| Checkout Charge | `checkout/charge` | חיוב ישיר |
| Public Checkout | `checkout/{id}` | עמוד תשלום ציבורי |
| Success Route | `checkout.success` | נתיב הצלחה |
| Failed Route | `checkout.failed` | נתיב כישלון |

**דוגמה - שינוי נתיבים:**

1. גשו ל-Admin Panel > Gateway Settings > Route Configuration
2. שנו את Route Prefix ל-`payments`
3. שנו את Card Callback ל-`return/card`
4. שמרו את ההגדרות
5. נקו cache: `php artisan route:clear`

**תוצאה:**
- `POST /payments/return/card` במקום `POST /officeguy/callback/card`
- `POST /payments/webhook/bit` במקום `POST /officeguy/webhook/bit`

**או ב-.env:**
```env
OFFICEGUY_ROUTE_PREFIX=payments
OFFICEGUY_CARD_CALLBACK_PATH=return/card
OFFICEGUY_BIT_WEBHOOK_PATH=ipn/bit
OFFICEGUY_SUMIT_WEBHOOK_PATH=triggers/sumit
```

**שימוש בקוד:**
```php
use OfficeGuy\LaravelSumitGateway\Support\RouteConfig;

// קבלת כל הנתיבים המוגדרים
$paths = RouteConfig::getAllPaths();
// [
//     'prefix' => 'officeguy',
//     'card_callback' => 'officeguy/callback/card',
//     'bit_webhook' => 'officeguy/webhook/bit',
//     'sumit_webhook' => 'officeguy/webhook/sumit',
//     ...
// ]

// קבלת נתיב ספציפי
$cardCallbackPath = RouteConfig::getPrefix() . '/' . RouteConfig::getCardCallbackPath();
```

---

## עמוד תשלום

### תצוגה, ממשק ותוכן

עמוד התשלום מספק ממשק מלא ומותאם לגביית תשלומים מלקוחות. ניתן להתאים את התוכן, העיצוב והשדות.

**הפעלה:**
```php
// ב-Admin Panel
// נווטו ל-SUMIT Gateway > Gateway Settings > Public Checkout Page
// הפעילו את "Enable Public Checkout"
```

**או ב-.env:**
```env
OFFICEGUY_ENABLE_PUBLIC_CHECKOUT=true
```

**גישה לעמוד:**
```
GET /officeguy/checkout/{id}
```

**יצירת קישור תשלום:**
```php
$checkoutUrl = route('officeguy.public.checkout', ['id' => $order->id]);

// שליחה ללקוח
Mail::to($customer->email)->send(new PaymentLinkEmail($checkoutUrl));
```

### התאמת עיצוב עמוד התשלום

```bash
php artisan vendor:publish --tag=officeguy-views
```

לאחר מכן ערכו את הקובץ:
`resources/views/vendor/officeguy/pages/checkout.blade.php`

**תכונות עמוד התשלום:**
- תמיכה מלאה ב-RTL (עברית/ערבית)
- עיצוב רספונסיבי עם Tailwind CSS
- בחירת אמצעי תשלום (כרטיס אשראי / Bit)
- תמיכה בכרטיסים שמורים (טוקנים)
- בחירת מספר תשלומים
- סיכום הזמנה

---

## שדות ת"ז ו-CVV

### הגדרת שדות חובה

ניתן להגדיר אם שדות ת.ז ו-CVV יהיו חובה, אופציונליים, או מוסתרים.

**ב-Admin Panel:**
נווטו ל-**SUMIT Gateway** > **Gateway Settings** > **Payment Settings**

**אפשרויות לכל שדה:**
- `required` - חובה (ברירת מחדל)
- `yes` - אופציונלי (מוצג אך לא חובה)
- `no` - מוסתר

**ב-.env:**
```env
OFFICEGUY_CITIZEN_ID=required   # required/yes/no
OFFICEGUY_CVV=required          # required/yes/no
```

**בקוד:**
```php
// קריאה להגדרות
$settings = app(SettingsService::class);
$citizenIdMode = $settings->get('citizen_id', 'required');
$cvvMode = $settings->get('cvv', 'required');
```

> ⚠️ **חשוב:** חברות האשראי מחייבות הזנת נתונים אלה. כדי להסתיר את השדות, יש לקבל מהן פטור מהזנת מס' ת.ז ו-CVV.

---

## מסמכים

### בחירת שפה אוטומטית

בברירת המחדל יופקו המסמכים בעברית. הפעלת "בחירת שפה אוטומטית" תאפשר להפיק את המסמכים בהתאם לשפת הלקוח/ה.

**ב-Admin Panel:**
- נווטו ל-**Gateway Settings** > **Document Settings**
- סמנו את **"Automatic Languages"**

**ב-.env:**
```env
OFFICEGUY_AUTOMATIC_LANGUAGES=true
```

### הפקת מסמך הזמנה

הפקת מסמך הזמנה נוסף ושליחתו ללקוח לאחר חיוב מוצלח, בנוסף למסמך חשבונית/קבלה.

**ב-Admin Panel:**
- סמנו את **"Create Order Document"**

**ב-.env:**
```env
OFFICEGUY_CREATE_ORDER_DOCUMENT=true
```

### הגדרות מסמכים נוספות

```env
# שליחת מסמך במייל ללקוח
OFFICEGUY_EMAIL_DOCUMENT=true

# יצירת מסמך כטיוטא (לא סופי)
OFFICEGUY_DRAFT_DOCUMENT=false
```

### שיעור מע"מ מותאם

```php
// במודל Payable שלכם
public function getVatRate(): ?float
{
    return 17.0; // 17% מע"מ
}

public function isTaxEnabled(): bool
{
    return true;
}
```

---

## סוגי תשלומים

### אינטגרציות עם PayPal ו-BlueSnap

הפקת מסמך (חשבונית/קבלה) אוטומטית בתשלום ב-PayPal, BlueSnap, או שערי תשלום אחרים.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Additional Features**

**ב-.env:**
```env
# PayPal - אפשרויות: no, yes, async
OFFICEGUY_PAYPAL_RECEIPTS=yes

# BlueSnap
OFFICEGUY_BLUESNAP_RECEIPTS=true

# שערים אחרים
OFFICEGUY_OTHER_RECEIPTS=stripe,paddle
```

**בקוד:**
```php
// הפקת קבלה ידנית לתשלום PayPal
DocumentService::createReceiptForExternalPayment($order, 'paypal', $transactionId);
```

---

## תשלומים מחולקים (Installments)

### הגדרת עסקאות תשלומים

הגדרת מספר תשלומים (עד 36) אפשרי לעסקה.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Payment Settings**

**הגדרות:**
- **Max Payments** - מספר תשלומים מקסימלי (עד 36)
- **Min Amount for Payments** - סכום מינימלי לאפשר תשלומים
- **Min Amount per Payment** - סכום מינימלי לתשלום בודד

**ב-.env:**
```env
OFFICEGUY_MAX_PAYMENTS=12
OFFICEGUY_MIN_AMOUNT_FOR_PAYMENTS=100
OFFICEGUY_MIN_AMOUNT_PER_PAYMENT=50
```

**בקוד:**
```php
// קבלת מספר תשלומים מקסימלי לסכום מסוים
$maxPayments = PaymentService::getMaximumPayments($amount);

// חיוב עם תשלומים
$result = PaymentService::processCharge($order, $paymentsCount = 6);
```

---

## תפיסת מסגרת (Authorize Only)

### קביעת מסגרת אשראי לחיוב מושהה

תפיסת מסגרת מאפשרת לבצע את חיוב האשראי בשלב מאוחר יותר - מתאימה לעסקאות עם סכום חיוב משתנה.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Payment Settings**

**הגדרות:**
- **Authorize Only** - הפעלת מצב תפיסת מסגרת
- **Authorize Added Percent** - אחוז תוספת למסגרת (למשל: 20%)
- **Authorize Minimum Addition** - סכום תוספת מינימלי

**ב-.env:**
```env
OFFICEGUY_AUTHORIZE_ONLY=true
OFFICEGUY_AUTHORIZE_ADDED_PERCENT=20
OFFICEGUY_AUTHORIZE_MINIMUM_ADDITION=50
```

**בקוד:**
```php
// תפיסת מסגרת
$result = PaymentService::authorizePayment($order, $amount);

// חיוב מאוחר יותר
$result = PaymentService::capturePayment($transactionId, $finalAmount);
```

> 💡 **שימוש נפוץ:** בתי מלון, השכרת רכב, או כל עסקה שבה הסכום הסופי עשוי להשתנות.

---

## מצב טסט

### בדיקות ללא חיוב אמיתי

מצב טסט מאפשר לבצע בדיקות כדי לוודא שהכל עובד בלי לסלוק ולבצע חיובים אמיתיים. מסמכים יופקו כטיוטות.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Environment Settings** > סמנו **"Testing Mode"**

**ב-.env:**
```env
OFFICEGUY_TESTING=true
```

**מספרי כרטיסים לבדיקות:**
| כרטיס | מספר | תוקף | CVV |
|-------|------|------|-----|
| ויזה (הצלחה) | 4580 0000 0000 0000 | כל תאריך עתידי | 123 |
| ויזה (כישלון) | 4580 0000 0000 0001 | כל תאריך עתידי | 123 |
| מאסטרקארד | 5326 1000 0000 0000 | כל תאריך עתידי | 123 |

**בקוד:**
```php
// בדיקה אם במצב טסט
$isTest = app(SettingsService::class)->get('testing', false);
```

> ⚠️ **חשוב:** לפני שהאתר עולה לאוויר, ודאו שביטלתם את מצב הטסט כדי לא לפספס מכירות אמיתיות!

---

## שמירת פרטי אשראי (Tokens)

### שמירת כרטיסי אשראי לרכישות חוזרות

מאפשר ללקוחות לשמור את פרטי כרטיס האשראי לרכישות עתידיות מהירות יותר.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Tokenization** > סמנו **"Support Tokens"**

**ב-.env:**
```env
OFFICEGUY_SUPPORT_TOKENS=true
OFFICEGUY_TOKEN_PARAM=5   # 5=J5 (מומלץ), 2=J2
```

**בקוד:**
```php
// שמירת טוקן לאחר חיוב
$token = OfficeGuyToken::createFromApiResponse($customer, $response);

// חיוב עם טוקן שמור
$result = PaymentService::processCharge($order, $payments, false, false, $token);

// קבלת טוקנים של לקוח
$tokens = OfficeGuyToken::where('owner_type', get_class($user))
    ->where('owner_id', $user->id)
    ->get();
```

**תכונות:**
- שמירת פרטי כרטיס מאובטחת (PCI DSS)
- מילוי אוטומטי ברכישות הבאות
- תמיכה בחיובים חוזרים (Subscriptions)
- ניהול כרטיסים בפאנל לקוח

---

## הוראות קבע ומנויים (Subscriptions)

### גביית תשלומים קבועים באשראי

לגביית תשלומים קבועים מלקוחות או תורמים, החבילה מספקת פתרון יעיל ואוטומטי לניהול מנויים.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Subscriptions**

**הגדרות:**
- **Enable Subscriptions** - הפעלת מנויים
- **Default Interval (Months)** - מרווח ברירת מחדל בחודשים
- **Default Cycles** - מספר חיובים (ריק = ללא הגבלה)
- **Allow Pause** - אפשרות להשהות מנוי
- **Retry Failed Charges** - ניסיון חוזר בכישלון
- **Max Retry Attempts** - מספר ניסיונות מקסימלי

**ב-.env:**
```env
OFFICEGUY_SUBSCRIPTIONS_ENABLED=true
OFFICEGUY_SUBSCRIPTIONS_DEFAULT_INTERVAL=1
OFFICEGUY_SUBSCRIPTIONS_DEFAULT_CYCLES=12
OFFICEGUY_SUBSCRIPTIONS_ALLOW_PAUSE=true
OFFICEGUY_SUBSCRIPTIONS_RETRY_FAILED=true
OFFICEGUY_SUBSCRIPTIONS_MAX_RETRIES=3
```

**יצירת מנוי:**
```php
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

// יצירת מנוי חדש
$subscription = SubscriptionService::create(
    $user,              // הלקוח
    'תוכנית חודשית',    // שם המנוי
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

// השהיית מנוי
SubscriptionService::pause($subscription);

// חידוש מנוי
SubscriptionService::resume($subscription);

// ביטול מנוי
SubscriptionService::cancel($subscription);
```

**תזמון חיובים חוזרים אוטומטיים:**

הוסיפו ל-`routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

// חיוב יומי בשעה 8:00
Schedule::command('sumit:process-recurring-payments')->dailyAt('08:00');

// או חיוב כל שעה
Schedule::command('sumit:process-recurring-payments')->hourly();

// עם דיווח על כשלונות
Schedule::command('sumit:process-recurring-payments')
    ->daily()
    ->emailOutputOnFailure('admin@example.com');
```

**הרצה ידנית:**
```bash
# הרצה אסינכרונית (כ-job)
php artisan sumit:process-recurring-payments

# הרצה סינכרונית
php artisan sumit:process-recurring-payments --sync

# עיבוד מנוי ספציפי
php artisan sumit:process-recurring-payments --subscription=123
```

---

## מלאי (Stock Management)

### סנכרון מלאי עם מערכת החשבונות

> 📦 **לניהול המלאי, יש להתקין את מודול מלאי בחשבון SUMIT.**

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Additional Features**

**הגדרות:**
- **Stock Sync Frequency** - תדירות סנכרון: `none`, `12` (שעות), `24` (שעות)
- **Checkout Stock Sync** - סנכרון בזמן Checkout

**ב-.env:**
```env
OFFICEGUY_STOCK_SYNC_FREQ=12      # none/12/24
OFFICEGUY_CHECKOUT_STOCK_SYNC=true
```

**Callback לעדכון מלאי:**
```php
// config/officeguy.php
'stock' => [
    'update_callback' => function(array $stockItem) {
        // עדכון מלאי במוצר
        $product = Product::where('sku', $stockItem['sku'])->first();
        if ($product) {
            $product->update(['stock_quantity' => $stockItem['quantity']]);
        }
    },
],
```

**הרצת סנכרון ידנית:**
```bash
php artisan sumit:stock-sync
```

**סנכרון בקוד:**
```php
use OfficeGuy\LaravelSumitGateway\Services\Stock\StockSyncService;

// סנכרון כל המלאי
StockSyncService::syncAll();

// סנכרון מוצר ספציפי
StockSyncService::syncProduct($sku);
```

**תזמון סנכרון אוטומטי:**
```php
// routes/console.php
Schedule::command('sumit:stock-sync')->everyTwelveHours();
```

---

## Bit ו-Redirect

### דף סליקה מסוג Redirect

גביה באמצעות Bit, Google Pay, Apple Pay, 3DS אפשרית באמצעות הגדרת דף סליקה בשיטת Redirect.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Environment Settings** > **PCI Mode** > בחרו **"Redirect"**

**ב-.env:**
```env
OFFICEGUY_PCI_MODE=redirect
OFFICEGUY_BIT_ENABLED=true
```

**בקוד:**
```php
// חיוב עם Bit
$result = BitPaymentService::processOrder(
    $order,
    route('checkout.success'),
    route('checkout.failed'),
    route('officeguy.webhook.bit')
);

if ($result['success']) {
    return redirect($result['redirect_url']);
}
```

**Webhook ל-Bit:**
```
POST /officeguy/webhook/bit
```

החבילה מטפלת אוטומטית ב-webhook ומעדכנת את סטטוס ההזמנה.

> ⚠️ **שימו לב:** מצב Redirect לא תומך בהוראות קבע, שמירת פרטי תשלום, ותפיסת מסגרת.

---

## מיזוג לקוחות אוטומטי

### מניעת כפילות כרטיסי לקוח

מיזוג כרטיס לקוח קיים במערכת SUMIT בסיום הרכישה באתר כדי למנוע כפילות. המיזוג מתבצע בהתאם למזהה הלקוח או המייל.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Customer Settings** > סמנו **"Merge Customers"**

**ב-.env:**
```env
OFFICEGUY_MERGE_CUSTOMERS=true
```

**איך זה עובד:**
1. בעת יצירת מסמך, המערכת מחפשת לקוח קיים לפי מייל או מזהה
2. אם נמצא - המסמך מקושר ללקוח הקיים
3. אם לא נמצא - נוצר לקוח חדש

---

## מודל Order (Payable)

החבילה דורשת שמודל ההזמנה יממש `OfficeGuy\LaravelSumitGateway\Contracts\Payable`.

### אפשרות 1: מיפוי שדות מ-Admin Panel (ללא שינוי קוד)

ניתן לחבר כל מודל קיים מבלי לשנות את הקוד שלו. ראו סעיף [עמוד תשלום ציבורי](#עמוד-תשלום-ציבורי-public-checkout-page).

### אפשרות 2: שימוש ב-Trait

```php
class Order extends Model implements Payable {
    use \OfficeGuy\LaravelSumitGateway\Support\Traits\PayableAdapter;
}
```

כדאי להעמיס (eager load) יחסי items/fees.

### קונפיגורציה

```php
'order' => [
    'model' => App\Models\Order::class,
    // או
    'resolver' => fn($id) => App\Models\Order::with('items','fees')->find($id),
],
```

---

## מסלולים (Routes)

תחת prefix (ברירת מחדל `officeguy`):

| מסלול | סוג | תיאור |
|-------|-----|-------|
| `callback/card` | GET | חזרת Redirect מכרטיס |
| `webhook/bit` | POST | IPN ל-Bit |
| `checkout/charge` | POST | מסלול סליקה מובנה (אופציונלי) |
| `checkout/{id}` | GET/POST | עמוד תשלום ציבורי (אופציונלי) |

מסלולי הצלחה/כישלון: מוגדרים ב-config `routes.success` / `routes.failed`.

---

## Filament Admin Panel

### עמודים וניהול
- **Gateway Settings** - הגדרות שער התשלום (ניווט: SUMIT Gateway)
- **משאבי לקוח** - טרנזקציות, מסמכים, אמצעי תשלום (Client Panel)

### גישה להגדרות
```
Admin Panel > SUMIT Gateway > Gateway Settings
```

---

## SSL

ה-HTTP client משתמש ב-`ssl_verify` (ברירת מחדל true). לשימוש dev בלבד ניתן לכבות:

```env
OFFICEGUY_SSL_VERIFY=false
```

---

## לוגים

הפעלת לוגים לניטור ודיבוג:

```env
OFFICEGUY_LOGGING=true
OFFICEGUY_LOG_CHANNEL=stack
```

> 🔒 נתונים רגישים (מספר כרטיס/CVV) מנוקים אוטומטית מהלוגים.

---

## Multi-Vendor

### תמיכה בריבוי מוכרים

תמיכה בשוק (marketplace) עם credentials נפרדים לכל ספק.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Multi-Vendor**

**הגדרות:**
- **Enable Multi-Vendor** - הפעלת מצב ריבוי מוכרים
- **Validate Vendor Credentials** - אימות פרטי ספק
- **Allow Authorize Only** - אפשרות תפיסת מסגרת לספקים

**ב-.env:**
```env
OFFICEGUY_MULTIVENDOR_ENABLED=true
OFFICEGUY_MULTIVENDOR_VALIDATE_CREDENTIALS=true
OFFICEGUY_MULTIVENDOR_ALLOW_AUTHORIZE=false
```

**בקוד:**
```php
use OfficeGuy\LaravelSumitGateway\Models\VendorCredential;
use OfficeGuy\LaravelSumitGateway\Services\MultiVendorPaymentService;

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

**Resolver לזיהוי ספק:**
```php
// config/officeguy.php
'multivendor' => [
    'vendor_resolver' => fn(array $item) => \App\Models\Vendor::find($item['vendor_id']),
],
```

---

## תרומות (Donations)

### תמיכה במוצרי תרומה

הפקת קבלת תרומה אוטומטית במקום חשבונית רגילה.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Donations**

**הגדרות:**
- **Enable Donations** - הפעלת תמיכה בתרומות
- **Allow Mixed Cart** - אפשרות לשלב תרומות עם מוצרים רגילים
- **Document Type** - סוג מסמך (Donation Receipt / Invoice)

**ב-.env:**
```env
OFFICEGUY_DONATIONS_ENABLED=true
OFFICEGUY_DONATIONS_ALLOW_MIXED=false
OFFICEGUY_DONATIONS_DOCUMENT_TYPE=320   # 320=קבלת תרומה
```

**בקוד:**
```php
use OfficeGuy\LaravelSumitGateway\Services\DonationService;

// בדיקה אם עגלה מכילה תרומות ומוצרים רגילים
$validation = DonationService::validateCart($order);
if (!$validation['valid']) {
    return redirect()->back()->with('error', $validation['message']);
}

// קבלת סוג המסמך המתאים
$docType = DonationService::getDocumentType($order);
```

---

## Upsell / CartFlows

### חיוב מוצרי upsell

חיוב מוצרים נוספים באמצעות טוקן מהחיוב הראשי - ללא צורך להזין שוב פרטי כרטיס.

**ב-Admin Panel:**
נווטו ל-**Gateway Settings** > **Upsell / CartFlows**

**הגדרות:**
- **Enable Upsell** - הפעלת upsell
- **Require Saved Token** - דרישת טוקן שמור
- **Max Upsells Per Order** - מקסימום upsells להזמנה

**ב-.env:**
```env
OFFICEGUY_UPSELL_ENABLED=true
OFFICEGUY_UPSELL_REQUIRE_TOKEN=true
OFFICEGUY_UPSELL_MAX_PER_ORDER=5
```

**בקוד:**
```php
use OfficeGuy\LaravelSumitGateway\Services\UpsellService;

// חיוב עם טוקן ידוע
$result = UpsellService::processUpsellCharge($upsellOrder, $token, $parentOrderId);

// חיוב עם זיהוי אוטומטי של הטוקן
$result = UpsellService::processUpsellWithAutoToken($upsellOrder, $parentOrderId, $customer);
```

---

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

**האזנה לאירועים:**
```php
// app/Providers/EventServiceProvider.php
use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;

protected $listen = [
    PaymentCompleted::class => [
        \App\Listeners\SendPaymentConfirmation::class,
        \App\Listeners\UpdateOrderStatus::class,
    ],
];
```

**דוגמת Listener:**
```php
namespace App\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;

class SendPaymentConfirmation
{
    public function handle(PaymentCompleted $event): void
    {
        $orderId = $event->orderId;
        $transactionId = $event->transactionId;
        
        // שליחת אימייל אישור
        Mail::to($event->customerEmail)->send(new PaymentConfirmed($orderId));
    }
}
```

---

## Custom Event Webhooks

### הגדרת Webhooks מה-Admin Panel

במקום ליצור Listeners בקוד, ניתן להגדיר Webhooks מותאמים אישית ישירות מה-Admin Panel. המערכת תשלח התראות HTTP לכל URL שתגדירו כאשר מתרחשים אירועים.

**ב-Admin Panel:**
נווטו ל-**SUMIT Gateway** > **Gateway Settings** > **Custom Event Webhooks**

**אירועים נתמכים:**
| אירוע | שדה בהגדרות | תיאור |
|-------|-------------|--------|
| Payment Completed | `webhook_payment_completed` | תשלום הושלם בהצלחה |
| Payment Failed | `webhook_payment_failed` | תשלום נכשל |
| Document Created | `webhook_document_created` | מסמך (חשבונית/קבלה) נוצר |
| Subscription Created | `webhook_subscription_created` | מנוי חדש נוצר |
| Subscription Charged | `webhook_subscription_charged` | מנוי חויב |
| Bit Payment Completed | `webhook_bit_payment_completed` | תשלום Bit הושלם |
| Stock Synced | `webhook_stock_synced` | מלאי סונכרן |

**הגדרת סוד לאימות:**
הגדירו `Webhook Secret` ב-Admin Panel. המערכת תשלח חתימה בכותרת `X-Webhook-Signature` לאימות מקור הבקשה.

**דוגמת Payload:**
```json
{
    "event": "payment_completed",
    "timestamp": "2024-01-15T10:30:00+02:00",
    "order_id": 123,
    "transaction_id": "TXN_12345",
    "amount": 99.00,
    "currency": "ILS",
    "customer_email": "customer@example.com"
}
```

**כותרות HTTP:**
```
Content-Type: application/json
X-Webhook-Event: payment_completed
X-Webhook-Signature: sha256=abc123...
X-Webhook-Timestamp: 2024-01-15T10:30:00+02:00
```

**אימות חתימה בשרת שלכם:**
```php
function verifyWebhook(Request $request): bool
{
    $signature = $request->header('X-Webhook-Signature');
    $payload = $request->getContent();
    $secret = config('your-webhook-secret');
    
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expectedSignature, $signature);
}
```

**שימוש ב-WebhookService ישירות (אופציונלי):**
```php
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

// שליחת webhook ידנית
$webhookService = app(WebhookService::class);
$webhookService->send('payment_completed', [
    'order_id' => 123,
    'amount' => 99.00,
]);
```

---

## Webhook Events Resource (Admin Panel)

### צפייה ב-Webhook Events

משאב מלא לצפייה וניהול כל אירועי ה-Webhook, כולל חיבור למשאבים קיימים לבניית אוטומציות.

**ב-Admin Panel:**
נווטו ל-**SUMIT Gateway** > **Webhook Events**

### תכונות הממשק

**רשימת אירועים:**
- צפייה בכל האירועים שנשלחו
- סינון לפי סוג אירוע, סטטוס, טווח תאריכים
- חיפוש לפי מייל לקוח או מזהה
- מיון לפי תאריך, סטטוס, HTTP status
- Badge עם מספר אירועים שנכשלו

**פעולות:**
- **Retry** - שליחה חוזרת של webhook שנכשל
- **Retry All Failed** - שליחה חוזרת לכל האירועים הכושלים
- **Clear Sent Events** - מחיקת אירועים ישנים (7+ ימים)
- **Copy Payload** - העתקת ה-payload

**חיבור למשאבים קיימים:**
כל אירוע מקושר אוטומטית למשאבים הרלוונטיים:
- **Transaction** - לחיצה מעבירה לעמוד הטרנזקציה
- **Document** - לחיצה מעבירה לעמוד המסמך
- **Token** - לחיצה מעבירה לעמוד הטוקן
- **Subscription** - לחיצה מעבירה לעמוד המנוי

**סטטיסטיקות (Widget):**
- אירועים היום
- אחוז הצלחה
- אירועים שנכשלו
- זמן תגובה ממוצע
- גרף 7 ימים אחרונים

### שימוש בסיסי

#### 1. הגדרת Webhook בשרת חיצוני

כדי לקבל התראות, צרו endpoint בשרת שלכם שמקבל בקשות POST:

```php
// routes/api.php
Route::post('/webhooks/sumit', [WebhookController::class, 'handle']);
```

```php
// app/Http/Controllers/WebhookController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1. אימות החתימה
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->getContent();
        $secret = config('services.sumit.webhook_secret');
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid webhook signature');
            return response('Invalid signature', 401);
        }
        
        // 2. עיבוד האירוע
        $event = $request->input('event');
        $data = $request->all();
        
        switch ($event) {
            case 'payment_completed':
                $this->handlePaymentCompleted($data);
                break;
            case 'payment_failed':
                $this->handlePaymentFailed($data);
                break;
            case 'document_created':
                $this->handleDocumentCreated($data);
                break;
            case 'subscription_charged':
                $this->handleSubscriptionCharged($data);
                break;
        }
        
        return response('OK', 200);
    }
    
    protected function handlePaymentCompleted(array $data): void
    {
        $orderId = $data['order_id'];
        $transactionId = $data['transaction_id'];
        $amount = $data['amount'];
        
        // עדכון הזמנה
        $order = Order::find($orderId);
        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        
        // שליחת אישור ללקוח
        Mail::to($data['customer_email'])->send(new PaymentConfirmation($order));
        
        // עדכון CRM
        CrmService::updateCustomer($data['customer_email'], [
            'last_purchase' => now(),
            'total_spent' => $amount,
        ]);
    }
    
    protected function handlePaymentFailed(array $data): void
    {
        $orderId = $data['order_id'];
        $error = $data['error'] ?? 'Unknown error';
        
        // עדכון הזמנה
        Order::find($orderId)?->update(['status' => 'payment_failed']);
        
        // התראה לצוות
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new PaymentFailedNotification($orderId, $error));
    }
    
    protected function handleDocumentCreated(array $data): void
    {
        // שמירת קישור למסמך
        $orderId = $data['order_id'];
        $documentUrl = $data['document_url'] ?? null;
        
        Order::find($orderId)?->update(['invoice_url' => $documentUrl]);
    }
    
    protected function handleSubscriptionCharged(array $data): void
    {
        $subscriptionId = $data['subscription_id'];
        $amount = $data['amount'];
        
        // רישום חיוב
        SubscriptionCharge::create([
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'charged_at' => now(),
        ]);
    }
}
```

#### 2. הגדרת URL ב-Admin Panel

1. גשו ל-**SUMIT Gateway** > **Gateway Settings** > **Custom Event Webhooks**
2. הזינו את ה-URL של ה-endpoint שלכם בשדה המתאים
3. הגדירו סוד (Secret) לאימות החתימה
4. שמרו את ההגדרות

### שימוש מתקדם בקוד

#### שליפת אירועים ב-Eloquent

```php
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

// קבלת כל האירועים שנכשלו
$failedEvents = WebhookEvent::failed()->get();

// קבלת אירועים של לקוח ספציפי
$customerEvents = WebhookEvent::forCustomer('customer@example.com')->get();

// קבלת אירועים מסוג מסוים
$paymentEvents = WebhookEvent::ofType('payment_completed')
    ->with(['transaction', 'document'])
    ->get();

// אירועים מוכנים לשליחה חוזרת
$pendingRetries = WebhookEvent::readyForRetry()->get();

// אירועים ממוינים לפי תאריך
$recentEvents = WebhookEvent::orderBy('created_at', 'desc')
    ->limit(100)
    ->get();
```

#### גישה למשאבים מקושרים

```php
// לכל אירוע יש גישה למשאבים הקשורים אליו
foreach ($paymentEvents as $event) {
    // גישה לטרנזקציה
    $transaction = $event->transaction;
    if ($transaction) {
        echo "Transaction ID: {$transaction->payment_id}";
        echo "Amount: {$transaction->amount}";
    }
    
    // גישה למסמך
    $document = $event->document;
    if ($document) {
        echo "Document Number: {$document->document_number}";
        echo "Document URL: {$document->url}";
    }
    
    // גישה לטוקן
    $token = $event->token;
    if ($token) {
        echo "Card: ****{$token->last_digits}";
    }
    
    // גישה למנוי
    $subscription = $event->subscription;
    if ($subscription) {
        echo "Subscription: {$subscription->name}";
        echo "Next Charge: {$subscription->next_charge_at}";
    }
    
    // גישה להזמנה (polymorphic)
    $order = $event->order;
    if ($order) {
        echo "Order ID: {$order->id}";
    }
}
```

#### שליחה חוזרת של אירועים

```php
use OfficeGuy\LaravelSumitGateway\Services\WebhookService;

// שליחה חוזרת של אירוע בודד
$event = WebhookEvent::find(123);
if ($event->canRetry()) {
    $webhookService = app(WebhookService::class);
    $success = $webhookService->send($event->event_type, $event->payload);
    
    if ($success) {
        $event->markAsSent(200);
    } else {
        $event->scheduleRetry(5); // retry in 5 minutes
    }
}

// שליחה חוזרת לכל האירועים שנכשלו
$failedEvents = WebhookEvent::failed()->get();
foreach ($failedEvents as $event) {
    if ($event->canRetry()) {
        $event->scheduleRetry();
    }
}
```

#### יצירת אירוע ידנית

```php
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

// יצירת אירוע חדש
$event = WebhookEvent::createEvent('payment_completed', [
    'order_id' => 123,
    'amount' => 99.00,
    'currency' => 'ILS',
    'customer_email' => 'customer@example.com',
], [
    'transaction_id' => $transaction->id,
    'document_id' => $document->id,
    'webhook_url' => 'https://your-site.com/webhook',
]);

// סימון כנשלח
$event->markAsSent(200, ['received' => true]);

// סימון ככישלון
$event->markAsFailed('Connection timeout', 504);
```

### בניית אוטומציות

#### דוגמה: סנכרון עם CRM

```php
// app/Console/Commands/SyncWebhooksToCrm.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;
use App\Services\CrmService;

class SyncWebhooksToCrm extends Command
{
    protected $signature = 'crm:sync-webhooks';
    protected $description = 'Sync payment webhooks to CRM';

    public function handle(CrmService $crm)
    {
        // קבלת כל האירועים שטרם סונכרנו
        $events = WebhookEvent::ofType('payment_completed')
            ->sent()
            ->where('synced_to_crm', false)
            ->with(['transaction', 'document'])
            ->get();
        
        foreach ($events as $event) {
            $crm->recordPurchase([
                'email' => $event->customer_email,
                'amount' => $event->amount,
                'currency' => $event->currency,
                'transaction_id' => $event->transaction?->payment_id,
                'invoice_url' => $event->document?->url,
            ]);
            
            $event->update(['synced_to_crm' => true]);
        }
        
        $this->info("Synced {$events->count()} events to CRM");
    }
}
```

#### דוגמה: דוח יומי

```php
// app/Console/Commands/WebhookDailyReport.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;
use Illuminate\Support\Facades\Mail;

class WebhookDailyReport extends Command
{
    protected $signature = 'webhooks:daily-report';
    protected $description = 'Send daily webhook statistics report';

    public function handle()
    {
        $today = now()->startOfDay();
        
        $stats = [
            'total' => WebhookEvent::whereDate('created_at', $today)->count(),
            'sent' => WebhookEvent::sent()->whereDate('created_at', $today)->count(),
            'failed' => WebhookEvent::failed()->whereDate('created_at', $today)->count(),
            'by_type' => WebhookEvent::whereDate('created_at', $today)
                ->selectRaw('event_type, count(*) as count')
                ->groupBy('event_type')
                ->pluck('count', 'event_type'),
            'total_amount' => WebhookEvent::ofType('payment_completed')
                ->whereDate('created_at', $today)
                ->sum('amount'),
        ];
        
        // שליחת דוח במייל
        Mail::to('admin@example.com')->send(new WebhookStatsReport($stats));
        
        $this->info("Report sent. Total events: {$stats['total']}");
    }
}
```

#### דוגמה: ניטור כשלונות

```php
// app/Console/Commands/MonitorWebhookFailures.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;
use Illuminate\Support\Facades\Notification;
use App\Notifications\WebhookFailureAlert;

class MonitorWebhookFailures extends Command
{
    protected $signature = 'webhooks:monitor';
    protected $description = 'Monitor webhook failures and alert';

    public function handle()
    {
        $failedCount = WebhookEvent::failed()
            ->where('created_at', '>=', now()->subHour())
            ->count();
        
        if ($failedCount > 10) {
            // שליחת התראה
            Notification::route('slack', config('services.slack.webhook'))
                ->notify(new WebhookFailureAlert($failedCount));
            
            $this->error("Alert sent: {$failedCount} failures in the last hour");
        } else {
            $this->info("All good: {$failedCount} failures in the last hour");
        }
    }
}
```

### תזמון משימות

הוסיפו ל-`routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// עיבוד webhooks שממתינים לשליחה חוזרת
Schedule::command('sumit:process-webhook-retries')->everyFiveMinutes();

// דוח יומי
Schedule::command('webhooks:daily-report')->dailyAt('09:00');

// ניטור כשלונות
Schedule::command('webhooks:monitor')->everyThirtyMinutes();

// סנכרון עם CRM
Schedule::command('crm:sync-webhooks')->hourly();
```

### סוגי אירועים

| סוג אירוע | קבוע | תיאור | שדות עיקריים |
|-----------|------|--------|--------------|
| Payment Completed | `payment_completed` | תשלום הושלם בהצלחה | `order_id`, `transaction_id`, `amount`, `customer_email` |
| Payment Failed | `payment_failed` | תשלום נכשל | `order_id`, `error`, `customer_email` |
| Document Created | `document_created` | מסמך נוצר | `order_id`, `document_id`, `document_number`, `document_url` |
| Subscription Created | `subscription_created` | מנוי חדש נוצר | `subscription_id`, `customer_email`, `amount`, `interval` |
| Subscription Charged | `subscription_charged` | מנוי חויב | `subscription_id`, `transaction_id`, `amount` |
| Bit Payment | `bit_payment_completed` | תשלום Bit הושלם | `order_id`, `transaction_id`, `amount` |
| Stock Synced | `stock_synced` | מלאי סונכרן | `items_count`, `sync_time` |

### סטטוסים

| סטטוס | קבוע | תיאור |
|-------|------|--------|
| Pending | `pending` | ממתין לשליחה |
| Sent | `sent` | נשלח בהצלחה |
| Failed | `failed` | השליחה נכשלה |
| Retrying | `retrying` | מתוזמן לשליחה חוזרת |

---

## קבלת Webhooks מ-SUMIT (Incoming Webhooks)

### מהי שליחת Webhook מ-SUMIT?

SUMIT יכולה לשלוח התראות (Webhooks) לאפליקציה שלכם כאשר מתרחשות פעולות במערכת SUMIT. זה מאפשר לכם לקבל עדכונים בזמן אמת על פעולות שבוצעו במערכת ניהול החשבונות.

**מידע נוסף:**
- [מדריך שליחת Webhook מ-SUMIT](https://help.sumit.co.il/he/articles/11577644-שליחת-webhook-ממערכת-סאמיט)
- [מבוא לטריגרים](https://help.sumit.co.il/he/articles/6324125-מבוא-לטריגרים)

### סוגי אירועים נתמכים

| פעולה | תיאור |
|-------|--------|
| `card_created` | יצירת כרטיס (לקוח, מסמך, פריט וכו') |
| `card_updated` | עדכון כרטיס |
| `card_deleted` | מחיקת כרטיס |
| `card_archived` | העברת כרטיס לארכיון |

### סוגי כרטיסים

| סוג כרטיס | תיאור |
|-----------|--------|
| `customer` | כרטיס לקוח |
| `document` | מסמך (חשבונית, קבלה) |
| `transaction` | עסקה |
| `item` | פריט מלאי |
| `payment` | תשלום |

### כתובות Webhook

החבילה חושפת מספר endpoints לקבלת webhooks מ-SUMIT:

| כתובת | תיאור |
|-------|--------|
| `POST /officeguy/webhook/sumit` | Endpoint כללי (זיהוי אוטומטי) |
| `POST /officeguy/webhook/sumit/card-created` | יצירת כרטיס |
| `POST /officeguy/webhook/sumit/card-updated` | עדכון כרטיס |
| `POST /officeguy/webhook/sumit/card-deleted` | מחיקת כרטיס |
| `POST /officeguy/webhook/sumit/card-archived` | העברת לארכיון |

### הגדרת Trigger ב-SUMIT

1. **התקנת מודולים נדרשים ב-SUMIT:**
   - מודול טריגרים
   - מודול API
   - מודול ניהול תצוגות

2. **יצירת תצוגה:**
   - הגדירו אילו כרטיסים יכללו
   - בחרו אילו שדות יועברו ב-webhook

3. **יצירת טריגר:**
   - בחרו תיקייה ותצוגה
   - הגדירו תנאי הפעלה (יצירה/עדכון/מחיקה/ארכיון)
   - בחרו פעולת HTTP
   - הזינו את כתובת ה-webhook שלכם

4. **הגדרת הכתובת:**
   ```
   https://your-domain.com/officeguy/webhook/sumit
   ```
   
   או לאירוע ספציפי:
   ```
   https://your-domain.com/officeguy/webhook/sumit/card-created
   ```

### SUMIT Webhooks Resource (Admin Panel)

צפייה בכל ה-webhooks שהתקבלו מ-SUMIT ב-Admin Panel:

**ב-Admin Panel:**
נווטו ל-**SUMIT Gateway** > **SUMIT Webhooks**

**תכונות:**
- צפייה בכל ה-webhooks שהתקבלו
- סינון לפי סוג אירוע, סוג כרטיס, סטטוס
- חיפוש לפי מזהה כרטיס, לקוח, מייל
- עיבוד webhooks שטרם טופלו
- סימון webhooks כמעובדים או מתעלמים

**סטטיסטיקות:**
- Webhooks היום
- ממתינים לעיבוד
- אחוז עיבוד
- webhooks שנכשלו

### טיפול ב-Webhooks בקוד

#### האזנה לאירוע

```php
// app/Providers/EventServiceProvider.php
use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;

protected $listen = [
    SumitWebhookReceived::class => [
        \App\Listeners\HandleSumitWebhook::class,
    ],
];
```

#### יצירת Listener

```php
// app/Listeners/HandleSumitWebhook.php
namespace App\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\SumitWebhookReceived;
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

class HandleSumitWebhook
{
    public function handle(SumitWebhookReceived $event): void
    {
        $webhook = $event->webhook;
        
        switch ($webhook->event_type) {
            case SumitWebhook::TYPE_CARD_CREATED:
                $this->handleCardCreated($webhook);
                break;
            case SumitWebhook::TYPE_CARD_UPDATED:
                $this->handleCardUpdated($webhook);
                break;
            case SumitWebhook::TYPE_CARD_DELETED:
                $this->handleCardDeleted($webhook);
                break;
            case SumitWebhook::TYPE_CARD_ARCHIVED:
                $this->handleCardArchived($webhook);
                break;
        }
    }
    
    protected function handleCardCreated(SumitWebhook $webhook): void
    {
        // טיפול ביצירת כרטיס
        $cardType = $webhook->card_type;
        $cardId = $webhook->card_id;
        $payload = $webhook->payload;
        
        if ($cardType === 'customer') {
            // סנכרון לקוח חדש למערכת
            Customer::create([
                'sumit_id' => $cardId,
                'name' => $payload['Name'] ?? '',
                'email' => $payload['Email'] ?? '',
                'phone' => $payload['Phone'] ?? '',
            ]);
        } elseif ($cardType === 'document') {
            // שמירת מסמך חדש
            Document::create([
                'sumit_id' => $cardId,
                'number' => $payload['Number'] ?? '',
                'amount' => $payload['Amount'] ?? 0,
            ]);
        }
        
        // סימון כמעובד
        $webhook->markAsProcessed('Successfully synced');
    }
    
    protected function handleCardUpdated(SumitWebhook $webhook): void
    {
        // עדכון כרטיס קיים
        $cardType = $webhook->card_type;
        $cardId = $webhook->card_id;
        
        if ($cardType === 'customer') {
            Customer::where('sumit_id', $cardId)->update([
                'name' => $webhook->payload['Name'] ?? '',
                'email' => $webhook->payload['Email'] ?? '',
            ]);
        }
        
        $webhook->markAsProcessed('Successfully updated');
    }
    
    protected function handleCardDeleted(SumitWebhook $webhook): void
    {
        // מחיקת כרטיס
        $cardType = $webhook->card_type;
        $cardId = $webhook->card_id;
        
        if ($cardType === 'customer') {
            Customer::where('sumit_id', $cardId)->delete();
        }
        
        $webhook->markAsProcessed('Successfully deleted');
    }
    
    protected function handleCardArchived(SumitWebhook $webhook): void
    {
        // סימון כרטיס כמאורכב
        $cardType = $webhook->card_type;
        $cardId = $webhook->card_id;
        
        if ($cardType === 'customer') {
            Customer::where('sumit_id', $cardId)
                ->update(['archived' => true]);
        }
        
        $webhook->markAsProcessed('Successfully archived');
    }
}
```

### שימוש ב-Eloquent

```php
use OfficeGuy\LaravelSumitGateway\Models\SumitWebhook;

// קבלת webhooks שטרם טופלו
$pending = SumitWebhook::received()->get();

// קבלת webhooks לפי סוג אירוע
$createdCards = SumitWebhook::ofType('card_created')->get();

// קבלת webhooks לפי סוג כרטיס
$customerWebhooks = SumitWebhook::ofCardType('customer')->get();

// קבלת webhooks שנכשלו
$failed = SumitWebhook::failed()->get();

// קבלת webhooks של לקוח ספציפי
$customerWebhooks = SumitWebhook::forCustomer('CUST123')->get();

// סימון webhook כמעובד
$webhook->markAsProcessed('Synced to CRM', [
    'transaction_id' => $transaction->id,
]);

// סימון webhook כנכשל
$webhook->markAsFailed('API error: 500');

// סימון webhook כמתעלם
$webhook->markAsIgnored('Duplicate webhook');
```

### התמודדות עם ניסיונות חוזרים מ-SUMIT

SUMIT מבצעת ניסיונות חוזרים אוטומטיים:

1. **Timeout:** המערכת ממתינה 10 שניות לתשובה
2. **Retry:** אם אין תשובה, ממתינה 30 שניות ומנסה שוב
3. **Max Retries:** לאחר 5 ניסיונות כושלים, הטריגר מושהה
4. **Resume:** כשהטריגר מופעל מחדש, כל הפעולות שהצטברו נשלחות

**המלצות:**

```php
// מומלץ: עיבוד אסינכרוני
public function handle(Request $request): JsonResponse
{
    // שמירה מהירה של ה-webhook
    $webhook = SumitWebhook::createFromRequest(...);
    
    // דחיית העיבוד ל-queue
    ProcessSumitWebhookJob::dispatch($webhook);
    
    // החזרת תשובה מיידית (תוך 10 שניות!)
    return response()->json(['success' => true], 200);
}
```

### דוגמאות שימוש נפוצות

#### סנכרון לקוחות

```php
// app/Jobs/SyncCustomerFromSumit.php
public function handle(): void
{
    $webhook = $this->webhook;
    
    if ($webhook->card_type !== 'customer') {
        $webhook->markAsIgnored('Not a customer card');
        return;
    }
    
    $payload = $webhook->payload;
    
    Customer::updateOrCreate(
        ['sumit_id' => $webhook->card_id],
        [
            'name' => $payload['Name'] ?? '',
            'email' => $payload['Email'] ?? '',
            'phone' => $payload['Phone'] ?? '',
            'address' => $payload['Address'] ?? '',
        ]
    );
    
    $webhook->markAsProcessed('Customer synced');
}
```

#### עדכון מלאי

```php
// app/Jobs/SyncInventoryFromSumit.php
public function handle(): void
{
    $webhook = $this->webhook;
    
    if ($webhook->card_type !== 'item') {
        $webhook->markAsIgnored('Not an item card');
        return;
    }
    
    $payload = $webhook->payload;
    
    Product::updateOrCreate(
        ['sumit_sku' => $payload['SKU'] ?? $webhook->card_id],
        [
            'name' => $payload['Name'] ?? '',
            'price' => $payload['Price'] ?? 0,
            'stock' => $payload['Stock'] ?? 0,
        ]
    );
    
    $webhook->markAsProcessed('Inventory synced');
}
```

#### התראה על מסמך חדש

```php
// app/Jobs/NotifyNewDocument.php
public function handle(): void
{
    $webhook = $this->webhook;
    
    if ($webhook->card_type !== 'document') {
        $webhook->markAsIgnored('Not a document');
        return;
    }
    
    $payload = $webhook->payload;
    
    // שליחת התראה לצוות
    Notification::route('slack', config('services.slack.webhook'))
        ->notify(new NewDocumentFromSumit([
            'document_number' => $payload['Number'] ?? '',
            'amount' => $payload['Amount'] ?? 0,
            'customer' => $payload['CustomerName'] ?? '',
        ]));
    
    $webhook->markAsProcessed('Notification sent');
}
```

---

## מיגרציות נתונים

### טבלאות

| טבלה | תיאור |
|------|--------|
| `officeguy_transactions` | טרנזקציות תשלום |
| `officeguy_tokens` | כרטיסי אשראי שמורים |
| `officeguy_documents` | חשבוניות וקבלות |
| `officeguy_settings` | הגדרות מערכת |
| `vendor_credentials` | credentials לספקים |
| `subscriptions` | מנויים |
| `officeguy_webhook_events` | אירועי Webhook (יוצאים) |
| `officeguy_sumit_webhooks` | Webhooks מ-SUMIT (נכנסים) |

המיגרציות נטענות אוטומטית מהחבילה. להעתקה מקומית:
```bash
php artisan vendor:publish --tag=officeguy-migrations
```

---

## בדיקות

- phpunit / orchestra testbench מומלצים
- החבילה כוללת בסיס מיגרציות
- יש להגדיר מודל Order דמה ל-Payable

**הרצת בדיקות:**
```bash
composer test
```

---

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
