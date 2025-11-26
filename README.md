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

### תכונות

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

**שימוש לבניית אוטומציות:**
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

// גישה למשאבים מקושרים
foreach ($paymentEvents as $event) {
    $transaction = $event->transaction;
    $document = $event->document;
    $subscription = $event->subscription;
}

// שליחה חוזרת של אירוע
$event = WebhookEvent::find(123);
if ($event->canRetry()) {
    $event->scheduleRetry(5); // retry in 5 minutes
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
| `officeguy_webhook_events` | אירועי Webhook |

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
