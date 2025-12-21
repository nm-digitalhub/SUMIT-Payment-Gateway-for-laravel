# תוכנית שיפור ארכיטקטורה - SUMIT Payment Gateway Package

> **מסמך זה**: תוכנית עבודה מפורטת ליישום המלצות המשוב מתוך `sumit-package-architecture-guide.md`
>
> **נוצר**: 2025-12-18
> **גרסה נוכחית**: v1.1.6
> **גרסת יעד**: v1.2.0 (minor release - backward compatible)

---

## 📋 תוכן עניינים

1. [סיכום ניתוח](#סיכום-ניתוח)
2. [מה כבר קיים ועובד היטב](#מה-כבר-קיים-ועובד-היטב)
3. [מה חסר או דורש שיפור](#מה-חסר-או-דורש-שיפור)
4. [תוכנית עבודה - 4 שלבים](#תוכנית-עבודה)
5. [עקרונות מנחים](#עקרונות-מנחים)
6. [סיכונים ואתגרים](#סיכונים-ואתגרים)

---

## 🔍 סיכום ניתוח

### מסקנות עיקריות מהמשוב:

החבילה מיושמת **טוב מאוד** בהיבטים הבאים:
- ✅ **Events & Listeners** - מערכת אירועים מפותחת לחלוטין
- ✅ **Fulfillment Pattern** - FulfillmentDispatcher + Handlers architecture קיים ועובד
- ✅ **PayableType Enum** - מפותח עם דרישות שדות לפי סוג
- ✅ **Services Layer** - 12 שירותים מאורגנים היטב
- ✅ **Filament Integration** - 13 resources (7 admin + 6 client)

**אך** - החבילה דורשת שיפורים בהיבטים הבאים:
- ❌ **Validation Layer** - ולידציה inline ב-Controller (צריך Form Request)
- ❌ **Context/Intent Object** - העברת נתונים כ-arrays (צריך DTO)
- ❌ **Service Data Preparation** - אין הכנת נתונים ספציפיים לשירות (registrant_contact, WHOIS)
- ❌ **Temporary Storage** - אין שמירה זמנית לפני אישור תשלום
- ❌ **Controller Responsibility** - PublicCheckoutController עמוס מדי (710 שורות)

---

## ⚠️ Pre-Implementation Rules (חובה!)

לפני כתיבת קוד, יש לעמוד בחוקים הבאים:

### 1. CheckoutIntent חייב להיות Immutable
- ✅ readonly properties בלבד
- ✅ לא לשנות אחרי יצירה
- ✅ אם צריך להוסיף מידע → ליצור מופע חדש
- ❌ אסור: `$intent->serviceSpecificData = $data`

### 2. אחסון זמני – DB כברירת מחדל
- ✅ טבלת `pending_checkouts` היא הפתרון הראשי
- ✅ Session רק כ-fallback (redirect, webhook, mobile)
- ❌ לא להשתמש ב-Session כפתרון ראשי

### 3. לא ליצור ServiceType כ-Enum גלובלי
- ✅ גזירת סוג השירות ב-Factory / Handler בלבד
- ✅ שימוש ב-PayableType הקיים
- ❌ אסור ליצור Enum חדש שמכפיל את PayableType

### 4. Fulfillment לא יוצר Order
- ✅ Fulfillment אחראי רק לפרוביז'נינג (API חיצוני)
- ✅ יצירת Order תתבצע באפליקציה דרך Event
- ✅ Event חדש: `FulfillmentCompleted`
- ❌ אסור ל-Handler ליצור Order ישירות

### 5. הגדרת גבולות אחריות ברורים
- **Controller** → HTTP + ולידציה בלבד
- **Intent** → הקשר רכישה (context) בלבד
- **PaymentService** → כסף בלבד
- **Fulfillment** → APIs חיצוניים בלבד
- ❌ בלי לוגיקה חוצה שכבות

**מטרה:** Controller רזה, דומיין מבודד, Fulfillment ניתן להרחבה.

---

## ✅ מה כבר קיים ועובד היטב

### 1. Events System (18 Events)

**קיים ב-**: `src/Events/`

```
✅ PaymentCompleted.php       - אירוע מרכזי עם transaction + payable
✅ PaymentFailed.php
✅ BitPaymentCompleted.php
✅ DocumentCreated.php
✅ SubscriptionCreated.php
✅ MultiVendorPaymentCompleted.php
... ועוד 12 events
```

**מה עובד היטב**:
- PaymentCompleted מכיל transaction object ו-payable object (v2.0)
- תמיכה ב-webhook confirmation check (isWebhookConfirmed)
- Backward compatibility עם גרסאות ישנות

### 2. Listeners System (6 Listeners)

**קיים ב-**: `src/Listeners/`

```
✅ FulfillmentListener.php          - מאזין ל-PaymentCompleted ומעביר ל-Dispatcher
✅ WebhookEventListener.php         - טיפול ב-webhooks נכנסים
✅ CustomerSyncListener.php         - סינכרון לקוחות ל-SUMIT
✅ DocumentSyncListener.php         - סינכרון מסמכים
✅ AutoCreateUserListener.php       - יצירת משתמשים אוטומטית
✅ CrmActivitySyncListener.php      - סינכרון פעילות CRM
```

**מה עובד היטב**:
- FulfillmentListener מקבל PaymentCompleted ומעביר ל-FulfillmentDispatcher
- ולידציה של transaction + payable לפני dispatch
- Logging מפורט בכל שלב
- Exception handling עם re-throw לניטור

### 3. FulfillmentDispatcher (Orchestration)

**קיים ב-**: `src/Services/FulfillmentDispatcher.php`

**מה עובד היטב**:
- **Type-based dispatch**: PayableType → Handler mapping
- **3 רמות עדיפות**:
  1. Custom override (Payable::getFulfillmentHandler)
  2. Type-based handler (registered in ServiceProvider)
  3. Fallback logging
- **Container integration**: Laravel service container
- **Testability**: clearHandlers(), registerMany() למבחנים

### 4. Handlers (3 Reference Implementations)

**קיים ב-**: `src/Handlers/`

```
✅ InfrastructureFulfillmentHandler.php    - domain, hosting, vps, ssl (TODO placeholders)
✅ DigitalProductFulfillmentHandler.php    - instant delivery
✅ SubscriptionFulfillmentHandler.php      - recurring billing
```

**מה עובד היטב**:
- מבנה ברור: handle() method
- Service type detection (getServiceType)
- Match expression לפילוח לפי סוג
- Logging מפורט

**מה חסר**:
- ❌ מימוש אמיתי של handleDomain(), handleHosting() (רק TODO comments)
- ❌ אין הכנת נתונים ספציפיים (registrant_contact, WHOIS)
- ❌ אין קריאה ל-API חיצוני (ResellerClub, cPanel)

### 5. PayableType Enum

**קיים ב-**: `src/Enums/PayableType.php`

**מה עובד היטב**:
- 5 קטגוריות: Infrastructure, Digital Product, Subscription, Service, Generic
- Methods למיפוי templates (checkoutTemplate)
- דרישות שדות לפי סוג (requiresAddress, requiresPhone)
- זמני אספקה (estimatedFulfillmentMinutes)
- תמיכה ב-i18n (label, labelEn)
- Filament integration (icon, color)

### 6. PublicCheckoutController

**קיים ב-**: `src/Http/Controllers/PublicCheckoutController.php` (710 שורות)

**מה עובד היטב**:
- Payable resolution (resolvePayable)
- תמיכה ב-3 PCI modes (no/redirect/yes)
- Prefill נתוני לקוח מפרופיל/user
- Idempotency protection (מניעת חיוב כפול)
- Guest registration
- Token management
- Bit payments

**מה דורש שיפור**:
- ❌ Validation inline (שורות 170-206) - צריך Form Request
- ❌ Profile update logic inline (שורות 293-347) - צריך Action class
- ❌ Guest user creation inline (שורות 208-265) - צריך Action class
- ❌ Controller עמוס מדי (710 שורות) - צריך הפרדה לשכבות

---

## ❌ מה חסר או דורש שיפור

### 1. **CheckoutRequest** (Form Request) - חסר לחלוטין

**בעיה נוכחית**:
```php
// PublicCheckoutController.php שורות 170-206
$rules = [
    'customer_name' => 'required|string|max:255',
    'customer_email' => 'required|email|max:255',
    'customer_phone' => 'required|string|max:50',
    'payment_method' => 'required|in:card,bit',
    // ... 20+ validation rules inline
];

// Conditional validation based on client profile
if (empty($client?->client_address)) {
    $rules['customer_address'] = 'required|string|max:255';
}

$validated = $request->validate($rules);
```

**פתרון מומלץ**:

יצירת `src/Http/Requests/CheckoutRequest.php`:

```php
namespace OfficeGuy\LaravelSumitGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or implement authorization logic
    }

    public function rules(): array
    {
        return [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:50',
            'payment_method' => 'required|in:card,bit',
            'payments_count' => 'nullable|integer|min:1|max:36',
            'payment_token' => 'nullable|string',
            'save_card' => 'nullable|boolean',
            // Address fields - conditional on PayableType
            'customer_address' => $this->addressRequired() ? 'required|string|max:255' : 'nullable|string|max:255',
            'customer_city' => $this->addressRequired() ? 'required|string|max:120' : 'nullable|string|max:120',
            // ...
        ];
    }

    protected function addressRequired(): bool
    {
        $payable = $this->getPayable();
        return $payable?->getPayableType()->requiresAddress() ?? false;
    }
}
```

**יתרונות**:
- ✅ שימוש חוזר (API, Livewire, admin checkout)
- ✅ בדיקות יחידתיות פשוטות
- ✅ הפרדת אחריות (Controller לא מטפל בולידציה)
- ✅ Conditional validation מאורגן

---

### 2. **CheckoutIntent** (Context Object) - חסר לחלוטין

**בעיה נוכחית**:

נתונים מועברים כ-arrays בין functions:

```php
// PublicCheckoutController.php שורה 511
$result = PaymentService::processCharge(
    $payable,
    $paymentsCount,
    false, // recurring
    $redirectMode,
    $token,
    $extra  // ← array with RedirectURL, CancelRedirectURL
);
```

**פתרון מומלץ**:

יצירת `src/DataTransferObjects/CheckoutIntent.php`:

```php
namespace OfficeGuy\LaravelSumitGateway\DataTransferObjects;

use OfficeGuy\LaravelSumitGateway\Contracts\Payable;

/**
 * CheckoutIntent DTO
 *
 * ⚠️ IMMUTABLE - אובייקט context שמתאר את כוונת הרכישה.
 * מכיל את כל הנתונים הנדרשים לעיבוד תשלום ללא לוגיקת דומיין.
 *
 * CRITICAL: כל ה-properties הם readonly - לא ניתן לשנות אחרי יצירה!
 * אם צריך להוסיף נתונים → ליצור CheckoutIntent חדש עם withServiceData()
 */
class CheckoutIntent
{
    public function __construct(
        public readonly Payable $payable,
        public readonly CustomerData $customer,
        public readonly PaymentPreferences $payment,
    ) {}

    public static function fromRequest(CheckoutRequest $request, Payable $payable): self
    {
        return new self(
            payable: $payable,
            customer: CustomerData::fromRequest($request),
            payment: PaymentPreferences::fromRequest($request),
        );
    }

    // ⚠️ Intent intentionally does not store service data
    // Service-specific data (WHOIS, cPanel config, etc.) is stored separately
    // in PendingCheckout table via TemporaryStorageService

    public function getAmount(): float
    {
        return $this->payable->getPayableAmount();
    }

    public function getCurrency(): string
    {
        return $this->payable->getPayableCurrency();
    }

    public function requiresAddress(): bool
    {
        return $this->payable->getPayableType()->requiresAddress();
    }
}
```

**Data classes נלווים**:

```php
class CustomerData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $company = null,
        public readonly ?string $vatNumber = null,
        public readonly ?string $citizenId = null,
        public readonly ?AddressData $address = null,
    ) {}
}

class PaymentPreferences
{
    public function __construct(
        public readonly string $method, // 'card' | 'bit'
        public readonly int $installments = 1,
        public readonly ?string $tokenId = null,
        public readonly bool $saveCard = false,
    ) {}
}

class AddressData
{
    public function __construct(
        public readonly string $line1,
        public readonly ?string $line2 = null,
        public readonly string $city,
        public readonly ?string $state = null,
        public readonly string $country = 'IL',
        public readonly ?string $postalCode = null,
    ) {}
}
```

**יתרונות**:
- ✅ Type safety (IDE autocomplete)
- ✅ קריאות - ברור מה נדרש בכל שלב
- ✅ Immutability (readonly properties)
- ✅ ניתן להעברה בין שכבות
- ✅ קל לבדיקות יחידתיות

---

### 3. **ServiceDataFactory** (Data Preparation Layer) - חסר לחלוטין

**בעיה נוכחית**:

אין הכנת נתונים ספציפיים לשירות כמו:
- `registrant_contact` לרישום דומיינים (ResellerClub)
- WHOIS data
- cPanel account details
- VPS configuration

**פתרון מומלץ**:

יצירת `src/Services/ServiceDataFactory.php`:

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\DataTransferObjects\CheckoutIntent;
use OfficeGuy\LaravelSumitGateway\Enums\ServiceType;

/**
 * ServiceDataFactory
 *
 * מכין נתונים ספציפיים לשירות על בסיס CheckoutIntent.
 * מתאים כוונה → אובייקט דומיין מוכן לשימוש ב-API חיצוני.
 */
class ServiceDataFactory
{
    public function build(CheckoutIntent $intent): array
    {
        // ⚠️ לא משתמשים ב-ServiceType Enum!
        // גוזרים סוג שירות מהמודל עצמו או מ-PayableType
        $serviceType = $this->detectServiceType($intent->payable);

        return match ($serviceType) {
            'domain' => $this->buildDomainData($intent),
            'hosting' => $this->buildHostingData($intent),
            'vps' => $this->buildVpsData($intent),
            'ssl' => $this->buildSslData($intent),
            default => [],
        };
    }

    /**
     * הכנת נתוני רישום דומיין (ResellerClub format)
     */
    protected function buildDomainData(CheckoutIntent $intent): array
    {
        return [
            'registrant_contact' => [
                'name' => $intent->customer->name,
                'company' => $intent->customer->company ?? '',
                'email' => $intent->customer->email,
                'address1' => $intent->customer->address?->line1 ?? '',
                'address2' => $intent->customer->address?->line2 ?? '',
                'city' => $intent->customer->address?->city ?? '',
                'state' => $intent->customer->address?->state ?? '',
                'country' => $intent->customer->address?->country ?? 'IL',
                'zipcode' => $intent->customer->address?->postalCode ?? '',
                'phone' => $this->formatPhoneForWhois($intent->customer->phone),
            ],
            'admin_contact' => 'same_as_registrant',
            'tech_contact' => 'same_as_registrant',
            'billing_contact' => 'same_as_registrant',
            'privacy_protection' => $this->shouldEnablePrivacy($intent),
            'nameservers' => $this->getDefaultNameservers(),
            'years' => $intent->payable->getYears() ?? 1,
        ];
    }

    /**
     * הכנת נתוני חבילת אירוח (cPanel WHM format)
     */
    protected function buildHostingData(CheckoutIntent $intent): array
    {
        return [
            'domain' => $intent->payable->getDomain(),
            'username' => $this->generateCpanelUsername($intent->payable->getDomain()),
            'plan' => $intent->payable->getHostingPlan(),
            'contactemail' => $intent->customer->email,
            'quotas' => [
                'disk' => $intent->payable->getDiskQuota(),
                'bandwidth' => $intent->payable->getBandwidth(),
            ],
        ];
    }

    /**
     * הכנת נתוני VPS
     */
    protected function buildVpsData(CheckoutIntent $intent): array
    {
        return [
            'hostname' => $intent->payable->getHostname(),
            'os' => $intent->payable->getOperatingSystem(),
            'ram' => $intent->payable->getRam(),
            'cpu' => $intent->payable->getCpu(),
            'disk' => $intent->payable->getDisk(),
            'ip_addresses' => $intent->payable->getIpCount(),
        ];
    }

    /**
     * הכנת נתוני SSL
     */
    protected function buildSslData(CheckoutIntent $intent): array
    {
        return [
            'domain' => $intent->payable->getDomain(),
            'csr' => $intent->payable->getCsr(),
            'validation_method' => 'dns', // or 'http'
            'admin_email' => $intent->customer->email,
        ];
    }

    protected function shouldEnablePrivacy(CheckoutIntent $intent): bool
    {
        // Check if privacy protection is enabled by default
        return config('officeguy.domain_privacy_protection', true);
    }

    protected function getDefaultNameservers(): array
    {
        return config('officeguy.default_nameservers', [
            'ns1.example.com',
            'ns2.example.com',
        ]);
    }

    protected function formatPhoneForWhois(string $phone): string
    {
        // Format: +972.541234567 (ResellerClub requirement)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return '+972.' . $phone;
    }

    protected function generateCpanelUsername(string $domain): string
    {
        // Extract domain name without TLD, max 8 chars
        $username = preg_replace('/\.[^.]+$/', '', $domain);
        $username = preg_replace('/[^a-z0-9]/', '', strtolower($username));
        return substr($username, 0, 8);
    }

    /**
     * ⚠️ גזירת סוג שירות מהמודל - לא Enum!
     *
     * משתמשים ב-PayableType הקיים + properties של המודל
     */
    protected function detectServiceType($payable): string
    {
        // עדיפות 1: שדה service_type במודל
        if (property_exists($payable, 'service_type')) {
            return $payable->service_type;
        }

        // עדיפות 2: method getServiceType() במודל
        if (method_exists($payable, 'getServiceType')) {
            return $payable->getServiceType();
        }

        // עדיפות 3: היסק מסוג המודל
        $className = class_basename($payable);
        if (str_contains($className, 'Domain')) return 'domain';
        if (str_contains($className, 'Hosting')) return 'hosting';
        if (str_contains($className, 'Vps')) return 'vps';
        if (str_contains($className, 'Ssl')) return 'ssl';

        // עדיפות 4: fallback לפי PayableType
        // ⚠️ מחזירים ערכים שיש להם טיפול ב-match למעלה!
        return match ($payable->getPayableType()) {
            PayableType::INFRASTRUCTURE => 'domain', // default for infrastructure
            PayableType::DIGITAL_PRODUCT => 'digital',
            PayableType::SUBSCRIPTION => 'subscription',
            default => 'generic',
        };
    }
}
```

**יתרונות**:
- ✅ הפרדה ברורה: Intent → Service Data
- ✅ ניתן להרחבה (קל להוסיף שירותים חדשים)
- ✅ Testable (מבחנים יחידתיים פשוטים)
- ✅ אין לוגיקת דומיין ב-Controller
- ✅ עקביות בפורמטים (WHOIS, cPanel, etc.)

---

### 4. **Temporary Data Storage** - חסר

**בעיה נוכחית**:

אין שמירה זמנית של נתוני checkout לפני אישור תשלום.

**פתרון מומלץ**:

**Database Table (פתרון ראשי - חובה!)**

⚠️ **CRITICAL**: DB הוא הפתרון הראשי, לא Session!
- ✅ DB: עמיד לרסטארט, webhooks, redirect flows
- ❌ Session: רק fallback למקרי קצה

Migration:
```php
Schema::create('pending_checkouts', function (Blueprint $table) {
    $table->id();
    $table->string('payable_type');
    $table->unsignedBigInteger('payable_id');
    $table->json('customer_data');
    $table->json('payment_preferences');
    $table->json('service_data')->nullable();
    $table->string('session_id')->nullable();
    $table->ipAddress('ip_address')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['payable_type', 'payable_id']);
    $table->index('expires_at');
});
```

Model:
```php
class PendingCheckout extends Model
{
    protected $casts = [
        'customer_data' => 'array',
        'payment_preferences' => 'array',
        'service_data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function toIntent(): CheckoutIntent
    {
        $payable = $this->payable_type::find($this->payable_id);

        return new CheckoutIntent(
            payable: $payable,
            customer: CustomerData::fromArray($this->customer_data),
            payment: PaymentPreferences::fromArray($this->payment_preferences),
            serviceSpecificData: $this->service_data,
        );
    }
}
```

**יתרונות**:
- ✅ לא "מלכלך" את המודל במקרה של כשל תשלום
- ✅ ניתן לשחזר checkout במקרה של timeout
- ✅ ניתן לניטור abandoned checkouts
- ✅ Auto-cleanup via scheduled job

---

### 5. **קישור Order רק אחרי הצלחה** - דורש מימוש

**מה קיים**:
- ✅ FulfillmentListener מאזין ל-PaymentCompleted
- ✅ FulfillmentDispatcher מעביר ל-Handler הנכון
- ✅ 3 Handlers (Infrastructure, Digital, Subscription)

**מה חסר**:
- ❌ מימוש אמיתי של handleDomain(), handleHosting(), handleVps(), handleSsl()
- ❌ קריאה ל-API חיצוני (ResellerClub, cPanel, WHM)
- ❌ Order creation logic

**פתרון מומלץ**:

**שלב 1: עדכון InfrastructureFulfillmentHandler**

```php
protected function handleDomain(OfficeGuyTransaction $transaction, $payable): void
{
    OfficeGuyApi::writeToLog(
        "InfrastructureFulfillmentHandler: Processing domain registration for {$payable->name}",
        'info'
    );

    // 1. Retrieve service data from transaction or pending checkout
    $serviceData = $this->getServiceData($transaction);

    if (!$serviceData) {
        throw new \RuntimeException('Service data not found for domain registration');
    }

    // 2. Call ResellerClub API
    try {
        $registrationResult = app(ResellerClubService::class)->registerDomain(
            domain: $payable->getDomain(),
            years: $payable->getYears() ?? 1,
            contacts: $serviceData['registrant_contact'],
            nameservers: $serviceData['nameservers'],
            privacyProtection: $serviceData['privacy_protection'],
        );

        // 3. Send confirmation email
        if ($transaction->payable?->getCustomerEmail()) {
            Mail::to($transaction->payable->getCustomerEmail())
                ->send(new DomainRegisteredMail($payable, $registrationResult));
        }

        // 4. ⚠️ לא יוצרים Order כאן! Event יטפל בזה
        // Dispatch FulfillmentCompleted event (פעם אחת בלבד!)
        // ⚠️ האפליקציה תאזין ל-event זה וליצור Order לפי הצורך
        event(new FulfillmentCompleted(
            transaction: $transaction,
            payable: $payable,
            provisioningData: $registrationResult,
            serviceType: 'domain'
        ));

        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Domain {$payable->getDomain()} registered successfully",
            'info'
        );

    } catch (\Exception $e) {
        OfficeGuyApi::writeToLog(
            "InfrastructureFulfillmentHandler: Domain registration failed: {$e->getMessage()}",
            'error'
        );

        // Dispatch failure event
        event(new FulfillmentFailed(
            transaction: $transaction,
            payable: $payable,
            error: $e,
            serviceType: 'domain'
        ));

        throw $e;
    }
}

/**
 * ⚠️ CRITICAL: Fulfillment לא יוצר Order!
 *
 * Flow:
 * 1. Handler קורא ל-API חיצוני (ResellerClub, cPanel...)
 * 2. Handler מפרסם FulfillmentCompleted event
 * 3. האפליקציה מאזינה ל-event ויוצרת Order
 *
 * דוגמה באפליקציה:
 *
 * // app/Listeners/CreateOrderAfterFulfillment.php
 * class CreateOrderAfterFulfillment
 * {
 *     public function handle(FulfillmentCompleted $event): void
 *     {
 *         Order::create([
 *             'user_id' => auth()->id(),
 *             'payable_type' => get_class($event->payable),
 *             'payable_id' => $event->payable->getPayableId(),
 *             'transaction_id' => $event->transaction->id,
 *             'status' => 'active',
 *             'external_order_id' => $event->provisioningData['order_id'],
 *         ]);
 *     }
 * }
 */

protected function getServiceData(OfficeGuyTransaction $transaction): ?array
{
    // Try to get from transaction metadata
    if ($transaction->metadata && isset($transaction->metadata['service_data'])) {
        return $transaction->metadata['service_data'];
    }

    // Try to get from pending checkout
    $pending = PendingCheckout::where('payable_type', get_class($transaction->payable))
        ->where('payable_id', $transaction->payable->getPayableId())
        ->first();

    return $pending?->service_data;
}
```

**שלב 2: יצירת ResellerClubService**

```php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class ResellerClubService
{
    protected string $apiUrl;
    protected string $resellerId;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.resellerclub.api_url');
        $this->resellerId = config('services.resellerclub.reseller_id');
        $this->apiKey = config('services.resellerclub.api_key');
    }

    public function registerDomain(
        string $domain,
        int $years,
        array $contacts,
        array $nameservers,
        bool $privacyProtection = true
    ): array {
        $response = Http::get($this->apiUrl . '/api/domains/register.json', [
            'auth-userid' => $this->resellerId,
            'api-key' => $this->apiKey,
            'domain-name' => $domain,
            'years' => $years,
            'ns' => $nameservers,
            'customer-id' => $this->getOrCreateCustomer($contacts),
            'reg-contact-id' => $this->createContact($contacts),
            'admin-contact-id' => '-1', // same as registrant
            'tech-contact-id' => '-1',
            'billing-contact-id' => '-1',
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => $privacyProtection ? 'true' : 'false',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Domain registration failed: ' . $response->body());
        }

        return $response->json();
    }

    protected function getOrCreateCustomer(array $contacts): int
    {
        // Implementation
    }

    protected function createContact(array $contacts): int
    {
        // Implementation
    }
}
```

**יתרונות**:
- ✅ הפרדה ברורה: Handler → External Service
- ✅ ניתן להרחבה (קל להוסיף providers נוספים)
- ✅ Testable (mock ResellerClubService)
- ✅ Order creation רק אחרי הצלחה
- ✅ Events לכל שלב (success, failure)

---

### 6. **Controller Refactoring** - הפרדת אחריות

**בעיה נוכחית**:

PublicCheckoutController מכיל 710 שורות עם:
- Validation (שורות 170-206)
- Guest user creation (שורות 208-265)
- Profile update (שורות 293-347)
- Card payment processing (שורות 466-550)
- Bit payment processing (שורות 559-584)

**פתרון מומלץ**:

**שלב 1: יצירת Action Classes**

```php
// src/Actions/CreateGuestUserAction.php
namespace OfficeGuy\LaravelSumitGateway\Actions;

class CreateGuestUserAction
{
    public function execute(array $validated): User
    {
        // Extract logic from lines 208-265
        $nameParts = explode(' ', trim($validated['customer_name']), 2);

        $user = User::create([
            'name' => $validated['customer_name'],
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'email' => $validated['customer_email'],
            'phone' => $validated['customer_phone'],
            // ...
        ]);

        event(new Registered($user));
        $user->notify(new WelcomeNotification);
        Auth::login($user);

        return $user;
    }
}

// src/Actions/UpdateClientProfileAction.php
namespace OfficeGuy\LaravelSumitGateway\Actions;

class UpdateClientProfileAction
{
    public function execute(Client $client, array $validated): void
    {
        // Extract logic from lines 293-347
        $dirty = false;

        if (empty($client->client_name) && !empty($validated['customer_name'])) {
            $client->client_name = $validated['customer_name'];
            $dirty = true;
        }
        // ... repeat for all fields

        if ($dirty) {
            $client->save();
        }
    }
}

// src/Actions/PrepareCheckoutIntentAction.php
namespace OfficeGuy\LaravelSumitGateway\Actions;

class PrepareCheckoutIntentAction
{
    public function execute(CheckoutRequest $request, Payable $payable): CheckoutIntent
    {
        $intent = CheckoutIntent::fromRequest($request, $payable);

        // Prepare service-specific data
        $serviceData = app(ServiceDataFactory::class)->build($intent);

        // ⚠️ Intent immutable - service data נשמר בנפרד!
        // Store Intent + ServiceData separately in DB
        app(TemporaryStorageService::class)->store($intent, $serviceData);

        return $intent;
    }
}
```

**שלב 2: Refactor PublicCheckoutController**

```php
public function process(CheckoutRequest $request, string|int $id)
{
    // 1. Resolve payable
    $payable = $this->resolvePayable($request, $id);

    if (!$payable) {
        abort(404, __('Order not found'));
    }

    // 2. Prepare checkout intent
    $intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);

    // 3. Handle guest registration (if needed)
    if (!auth()->check() && $request->filled('password')) {
        $user = app(CreateGuestUserAction::class)->execute($request->validated());
    }

    // 4. Update client profile (if missing data)
    if ($client = auth()->user()?->client) {
        app(UpdateClientProfileAction::class)->execute($client, $request->validated());
    }

    // 5. Process payment
    return match ($intent->payment->method) {
        'bit' => $this->processBitPayment($intent),
        'card' => $this->processCardPayment($intent, $request),
    };
}

protected function processCardPayment(CheckoutIntent $intent, Request $request)
{
    // Simplified - just call PaymentService with Intent
    $result = app(PaymentService::class)->processIntent($intent);

    if ($result['success']) {
        return redirect()->route(
            config('officeguy.routes.success', 'checkout.success'),
            ['order' => $intent->payable->getPayableId()]
        )->with('success', __('Payment completed successfully'));
    }

    return back()->withInput()->with('error', $result['message']);
}
```

**יתרונות**:
- ✅ Controller רזה (< 200 שורות)
- ✅ Single Responsibility Principle
- ✅ Testable (מבחנים יחידתיים לכל Action)
- ✅ Reusable (שימוש ב-API, Livewire, admin)
- ✅ קריאות - ברור מה קורה בכל שלב

---

## 📋 תוכנית עבודה

### שלב 1: Foundation - DTOs & Validation (1 גרסה: v1.2.0)

**מטרה**: הקמת תשתית - DTOs, Form Requests, ServiceDataFactory

**משימות**:

1. **יצירת CheckoutRequest** (Form Request)
   - קובץ: `src/Http/Requests/CheckoutRequest.php`
   - Validation rules מ-PublicCheckoutController
   - Conditional validation לפי PayableType
   - Tests: `tests/Unit/Requests/CheckoutRequestTest.php`

2. **יצירת CheckoutIntent & Data Classes** (⚠️ IMMUTABLE!)
   - קובץ: `src/DataTransferObjects/CheckoutIntent.php` (readonly properties!)
   - קובץ: `src/DataTransferObjects/CustomerData.php`
   - קובץ: `src/DataTransferObjects/PaymentPreferences.php`
   - קובץ: `src/DataTransferObjects/AddressData.php`
   - Tests: `tests/Unit/DataTransferObjects/CheckoutIntentTest.php`

3. **יצירת ServiceDataFactory** (⚠️ ללא ServiceType Enum!)
   - קובץ: `src/Services/ServiceDataFactory.php`
   - Methods: buildDomainData, buildHostingData, buildVpsData, buildSslData
   - Method: detectServiceType() - גזירה מהמודל, לא Enum
   - Tests: `tests/Unit/Services/ServiceDataFactoryTest.php`

4. **Documentation**
   - עדכון README.md - הוספת סקציית DTOs
   - עדכון CLAUDE.md - הוספת ServiceDataFactory
   - עדכון CHANGELOG.md - v1.2.0 features

**Output**:
- ✅ CheckoutIntent DTO ready
- ✅ ServiceDataFactory ready
- ✅ Tests pass (>80% coverage)
- ✅ Documentation updated

---

### שלב 2: Temporary Storage (1 גרסה: v1.2.0)

**מטרה**: שמירה זמנית של checkout data לפני אישור תשלום (⚠️ DB-first!)

**משימות**:

1. **יצירת PendingCheckout Model & Migration** (⚠️ פתרון ראשי!)
   - Migration: `database/migrations/xxxx_create_pending_checkouts_table.php`
   - Model: `src/Models/PendingCheckout.php`
   - שדות: payable_type, payable_id, customer_data, payment_preferences, service_data
   - Tests: `tests/Unit/Models/PendingCheckoutTest.php`

2. **יצירת TemporaryStorageService** (DB-first)
   - קובץ: `src/Services/TemporaryStorageService.php`
   - Methods: store(), retrieve(), cleanup()
   - שמירה ב-DB כברירת מחדל
   - Session רק כ-fallback (redirect, webhook)
   - Auto-cleanup job: `src/Jobs/CleanupExpiredCheckoutsJob.php`
   - Tests: `tests/Unit/Services/TemporaryStorageServiceTest.php`

3. **שילוב ב-PublicCheckoutController**
   - שמירת CheckoutIntent + ServiceData ב-DB לפני תשלום
   - שחזור מ-DB במקרה של timeout/redirect
   - Tests: `tests/Feature/CheckoutTemporaryStorageTest.php`

4. **Documentation**
   - עדכון README.md - הוספת סקציית Temporary Storage (DB-first)
   - עדכון CLAUDE.md - הוספת PendingCheckout model

**Output**:
- ✅ PendingCheckout table & model
- ✅ Temporary storage working
- ✅ Auto-cleanup scheduled
- ✅ Tests pass

---

### שלב 3: Controller Refactoring (1 גרסה: v1.3.0)

**מטרה**: הפרדת אחריות - Action classes

**משימות**:

1. **יצירת Action Classes**
   - קובץ: `src/Actions/CreateGuestUserAction.php`
   - קובץ: `src/Actions/UpdateClientProfileAction.php`
   - קובץ: `src/Actions/PrepareCheckoutIntentAction.php`
   - Tests: `tests/Unit/Actions/*Test.php`

2. **Refactor PublicCheckoutController**
   - שימוש ב-CheckoutRequest
   - שימוש ב-Action classes
   - Reduce to < 300 lines
   - Tests: `tests/Feature/PublicCheckoutControllerTest.php`

3. **Documentation**
   - עדכון README.md - הוספת Actions pattern
   - עדכון CLAUDE.md - Controller best practices

**Output**:
- ✅ Controller < 300 lines
- ✅ Action classes working
- ✅ Backward compatible
- ✅ Tests pass

---

### שלב 4: Fulfillment Implementation (1 גרסה: v1.4.0 - בתיאום עם לקוח)

**מטרה**: מימוש אמיתי של Fulfillment Handlers (⚠️ ללא יצירת Order!)

**משימות**:

1. **יצירת FulfillmentCompleted & FulfillmentFailed Events** (⚠️ חובה!)
   - Event: `src/Events/FulfillmentCompleted.php`
   - Event: `src/Events/FulfillmentFailed.php`
   - Properties: transaction, payable, provisioningData, serviceType
   - Tests: `tests/Unit/Events/FulfillmentCompletedTest.php`

2. **יצירת External Service Integrations** (בתיאום עם לקוח)
   - ResellerClubService (domain registration)
   - CpanelService (hosting provisioning)
   - VpsProviderService (VPS provisioning)
   - SslProviderService (SSL certificate generation)

3. **עדכון InfrastructureFulfillmentHandler**
   - מימוש handleDomain() עם ResellerClubService
   - מימוש handleHosting() עם CpanelService
   - מימוש handleVps() עם VpsProviderService
   - מימוש handleSsl() עם SslProviderService
   - ⚠️ כל handler מפרסם FulfillmentCompleted/FulfillmentFailed
   - ⚠️ אסור ליצור Order ב-Handler!

4. **⚠️ Order Creation - באפליקציה בלבד!**
   - ⚠️ לא ב-Handler! רק דרך Event Listener באפליקציה
   - דוגמה: `app/Listeners/CreateOrderAfterFulfillment.php`
   - Listener מאזין ל-FulfillmentCompleted ויוצר Order
   - קישור Order ↔ Transaction ↔ Payable

5. **Documentation**
   - עדכון README.md - Fulfillment workflow (ללא Order creation)
   - עדכון CLAUDE.md - External services integration
   - הוספת דוגמה: איך ליצור Order באפליקציה דרך Event

**Output**:
- ✅ Domain registration working
- ✅ Hosting provisioning working
- ✅ VPS provisioning working
- ✅ SSL certificate generation working
- ✅ Order creation working
- ✅ Tests pass

**⚠️ שים לב**: שלב 4 דורש תיאום עם הלקוח:
- קונפיגורציה של ResellerClub credentials
- גישה ל-cPanel/WHM API
- גישה ל-VPS provider API
- גישה ל-SSL provider API

---

## 🎯 עקרונות מנחים

### 1. Backward Compatibility

**חובה לשמור על תאימות לאחור**:
- ✅ שימוש קיים ב-PaymentService::processCharge() ימשיך לעבוד
- ✅ אירועים קיימים (PaymentCompleted) לא משתנים
- ✅ Listeners קיימים ממשיכים לעבוד
- ✅ אפשר להשתמש ב-DTOs בנוסף למבנה הקיים, לא במקומו

**דוגמה**:
```php
// Old way (still supported)
PaymentService::processCharge($payable, $paymentsCount, false, $redirectMode, $token, $extra);

// New way (preferred)
PaymentService::processIntent($intent);
```

### 2. Progressive Enhancement

**שיפור הדרגתי, לא שכתוב מחדש**:
- ✅ הוספת DTOs מהשלב הראשון
- ✅ Controller ממשיך לעבוד כרגיל
- ✅ בהדרגה, העברת לוגיקה ל-Actions
- ✅ בסוף - Controller רזה, Actions testable

### 3. Test Coverage

**כל שינוי מלווה במבחנים**:
- ✅ Unit tests לכל DTO, Action, Service
- ✅ Feature tests ל-checkout flow
- ✅ Integration tests ל-fulfillment
- ✅ Coverage > 80%

### 4. Documentation First

**תיעוד לפני קוד**:
- ✅ עדכון CLAUDE.md בכל שלב
- ✅ עדכון README.md עם דוגמאות
- ✅ PHPDoc מפורט
- ✅ עדכון CHANGELOG.md

### 5. Git Workflow

**תהליך Git מסודר**:
```bash
# 1. Work in vendor directory
cd /var/www/vhosts/nm-digitalhub.com/httpdocs/vendor/officeguy/laravel-sumit-gateway

# 2. Make changes, test
# ... make changes

# 3. Copy to original repo
cp -r src/ /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel/src/

# 4. Commit
cd /var/www/vhosts/nm-digitalhub.com/SUMIT-Payment-Gateway-for-laravel
git add .
git commit -m "feat: Add CheckoutIntent DTO

Added CheckoutIntent, CustomerData, PaymentPreferences, AddressData DTOs.
Part of architecture improvement plan (Phase 1).

Implements:
- Type-safe checkout context
- Immutable data objects
- Easy testing

Ref: docs/ARCHITECTURE_IMPROVEMENT_PLAN.md
"

# 5. Tag version
git tag -a v1.2.0 -m "Release v1.2.0: DTOs & ServiceDataFactory"
git push origin main
git push origin v1.2.0

# 6. Update parent app
cd /var/www/vhosts/nm-digitalhub.com/httpdocs
composer update officeguy/laravel-sumit-gateway
```

---

## ⚠️ סיכונים ואתגרים

### סיכון 1: Breaking Changes

**בעיה**: שינויים ב-API עלולים לשבור קוד קיים

**פתרון**:
- ✅ שמירה על תאימות לאחור בכל שלב
- ✅ Deprecation warnings במקום הסרה מיידית
- ✅ Upgrade guide מפורט ב-UPGRADE.md
- ✅ Semantic versioning strict (MAJOR.MINOR.PATCH)

### סיכון 2: External Service Dependencies

**בעיה**: ResellerClub, cPanel APIs עלולים להשתנות

**פתרון**:
- ✅ Service abstraction (interface)
- ✅ ניתן להחלפה (swap providers)
- ✅ Graceful degradation (fallback to manual provisioning)
- ✅ Retry logic עם exponential backoff

### סיכון 3: Data Migration

**בעיה**: pending_checkouts table חדשה

**פתרון**:
- ✅ Migration עם rollback
- ✅ Auto-cleanup של expired records
- ✅ אין תלות בתיעוד היסטורי (temporary storage)

### סיכון 4: Testing Coverage

**בעיה**: קשה לבדוק integration עם APIs חיצוניים

**פתרון**:
- ✅ HTTP mocking (Http::fake)
- ✅ Service mocking (Mockery)
- ✅ Sandbox accounts לבדיקות
- ✅ Manual testing checklist

### סיכון 5: Scope Creep

**בעיה**: הפרויקט יכול להתרחב מעבר לצפוי

**פתרון**:
- ✅ תוכנית קפדנית של 4 שלבים
- ✅ אישור לקוח לכל שלב
- ✅ MVP approach (minimum viable product)
- ✅ שלב 4 אופציונלי (בתיאום עם לקוח)

---

## 📝 סיכום

### מה כבר עובד היטב:
- ✅ Events & Listeners system
- ✅ FulfillmentDispatcher pattern
- ✅ PayableType Enum
- ✅ Services layer

### מה נוסיף:
- 📦 CheckoutIntent & DTOs (Phase 1)
- 📦 ServiceDataFactory (Phase 1)
- 📦 Temporary Storage (Phase 2)
- 📦 Action Classes (Phase 3)
- 📦 Fulfillment Implementation (Phase 4 - optional)

### Timeline משוער:
- **Phase 1**: 2-3 ימי עבודה (DTOs + ServiceDataFactory)
- **Phase 2**: 1-2 ימי עבודה (Temporary Storage)
- **Phase 3**: 2-3 ימי עבודה (Controller Refactoring)
- **Phase 4**: 5-7 ימי עבודה (Fulfillment Implementation - בתיאום)

**סה"כ**: 10-15 ימי עבודה לשלבים 1-3, +5-7 ימים לשלב 4

---

**Next Steps**:
1. ✅ אישור תוכנית עבודה
2. 📋 התחלת Phase 1 - יצירת CheckoutRequest
3. 📋 יצירת CheckoutIntent & DTOs
4. 📋 יצירת ServiceDataFactory

---

**מסמך זה**: תוכנית חיה - יעודכן לאחר כל שלב בפועל
