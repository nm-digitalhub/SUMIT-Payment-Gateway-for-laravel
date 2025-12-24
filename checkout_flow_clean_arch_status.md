# Checkout Flow Clean Architecture — מיפוי מצב נוכחי + תוכנית עבודה מדויקת

מטרת המסמך: לתעד **בדיוק** מה קיים כיום (כולל UI) עם הפניות לקבצים ושורות, ואז להציג **תוכנית עבודה מדויקת** להעברת ה‑Checkout Flow לארכיטקטורה נקייה.

## יעד הארכיטקטורה (כפי שהוגדר)
- **Controller** – HTTP בלבד (קלט/פלט)
- **CheckoutIntent** – נתוני כוונה בלבד (Immutable DTO)
- **CheckoutIntentResolver** – כל ההחלטות (PCI, redirect, token, payments)
- **PaymentService** – ביצוע בלבד (Execution)

---

## קבצים שנבדקו (קוד + UI)

### Controllers / Requests / Services / DTOs
- `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/CheckoutController.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/PublicCheckoutController.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Http/Requests/CheckoutRequest.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Actions/PrepareCheckoutIntentAction.php`
- `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/CheckoutIntent.php`
- `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/PaymentPreferences.php`
- `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/ResolvedPaymentIntent.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutIntentResolver.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/PaymentService.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/TokenService.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/BitPaymentService.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/TemporaryStorageService.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutViewResolver.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Support/RequestHelpers.php`

### UI (Published Overrides + Package Views)
**Published (במערכת הראשית):**
- `resources/views/vendor/officeguy/pages/checkout.blade.php`
- `resources/views/vendor/officeguy/pages/digital.blade.php`
- `resources/views/vendor/officeguy/pages/infrastructure.blade.php`
- `resources/views/vendor/officeguy/pages/partials/payment-section.blade.php`
- `resources/views/vendor/officeguy/components/payment-form.blade.php`

**Package defaults:**
- `vendor/officeguy/laravel-sumit-gateway/resources/views/pages/checkout.blade.php`
- `vendor/officeguy/laravel-sumit-gateway/resources/views/pages/partials/payment-section.blade.php`
- `vendor/officeguy/laravel-sumit-gateway/resources/views/components/payment-form.blade.php`

---

## מיפוי UI → שדות בקשה (Request Payload) — מצב קיים

### 1) Published Checkout (`resources/views/vendor/officeguy/pages/checkout.blade.php`)
- **PaymentJS scripts נטענים רק אם pci_mode = no**
  - שורות 157–161
- **שדות עיקריים שנשלחים**
  - `payment_method` (hidden, x-model) — שורות 500–505
  - `payment_token` (hidden, x-model selectedToken) — שורות 502–505
  - `payment_token_choice` (radio) — שורות 530–615 (UI בלבד, backend לא קורא ישירות)
  - `payments_count` (select) — שורות 808–814
  - `og-token` (hidden, data-og token) — שורות 785–787 (מופיע רק כש־pci_mode = no)
  - `cvv` (input, data-og="cvv") — שורות 736–752
  - `citizen_id` (input, data-og="citizenid") — שורות 759–781
- **PaymentsJS BindFormSubmit**
  - שורות 1349–1373
  - `IgnoreBind` מבטל התערבות SUMIT כשנבחר טוקן שמור (לא "new") — שורות 1360–1372
- **Alpine state**
  - `paymentMethod`, `selectedToken`, `singleUseToken`, `paymentsCount` וכו' — שורות 1176–1219
  - `validate()` מונע submit במידה וחסרים שדות — שורות 1243–1266

### 2) Payment Section Partial (Published)
- `resources/views/vendor/officeguy/pages/partials/payment-section.blade.php`
- **שדות עיקריים**
  - `payment_method` — שורות 46–47
  - `payment_token` — שורה 47
  - `payment_token_choice` — שורות 68–133
  - `payments_count` — שורות 275–283
  - `og-token` — שורות 253–255 (pci_mode = no)
  - `cvv` — שורות 204–222
  - `citizen_id` — שורות 227–251

### 3) Digital / Infrastructure Templates (Published)
- **Digital**: `resources/views/vendor/officeguy/pages/digital.blade.php`
  - כולל `payment-section` — שורות 182–190
  - PaymentsJS נטען ומאתחל `BindFormSubmit` + OnTokenReceived — שורות 211–275
- **Infrastructure**: `resources/views/vendor/officeguy/pages/infrastructure.blade.php`
  - PaymentsJS init + BindFormSubmit — שורות 398–481

### 4) Package default views (vendor)
- `vendor/officeguy/laravel-sumit-gateway/resources/views/pages/checkout.blade.php`
  - שדה `payment_method` — שורה 396
  - שדה `payment_token` (radio) — שורות 418–424
  - `og-token` — שורות 675–677
  - `payments_count` — שורות 697–704

### 5) Component PaymentForm (Published)
- `resources/views/vendor/officeguy/components/payment-form.blade.php`
  - `og-token` hidden — שורות 189–192
  - CVV ל־saved card — שורות 199–214
  - `og-paymentscount` (שונה מה־checkout) — שורות 156–169

> **הערה עובדתית:** קיימים הבדלים בשמות השדות בין ה־checkout הראשי ל־PaymentForm component.

---

## זרימת Controller → Intent → Resolver → PaymentService (מצב קיים)

### A) CheckoutController (לא public)
- יוצר Intent → Resolver → processResolvedIntent:
  - `CheckoutIntent::fromRequest` — שורה 52
  - `CheckoutIntentResolver::resolve` — שורה 54
  - `PaymentService::processResolvedIntent` — שורה 56
- **קוד PCI/redirect/token קודם** נשאר בקונטרולר אך לא מוזן ל־processResolvedIntent:
  - `pciMode`/`redirectMode`/`extra` — שורות 39–46
  - `token` — שורות 48–50

קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/CheckoutController.php` שורות 23–70.

### B) PublicCheckoutController (flow הראשי בפועל)
- מבצע Validation + Guest registration + לוגיקה עסקית בתוך הקונטרולר:
  - PCI/token validation — שורות 241–257
  - Bit payment flow — שורות 260–262
  - Process card payment — שורות 441–535
  - Intent נוצר אך **לא** משמש להחלטות — שורות 320–325

קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Http/Controllers/PublicCheckoutController.php`.

### C) CheckoutRequest (FormRequest)
- מחייב: `payment_method`, `payments_count`, `payment_token`, `og-token` וכו׳
  - שורות 61–92
- `accept_terms` חייב accepted — שורה 91

קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Http/Requests/CheckoutRequest.php`.

### D) CheckoutIntent + PaymentPreferences
- Intent immutability + fromRequest — שורות 25–47
- PaymentPreferences מתבסס על:
  - `payment_method`
  - `payments_count`
  - `payment_token`
  - `save_card`
  - קובץ: `vendor/officeguy/laravel-sumit-gateway/src/DataTransferObjects/PaymentPreferences.php` שורות 29–42

### E) CheckoutIntentResolver (מינימלי)
- קובע:
  - recurring (אם isRecurring) — שורות 25–27
  - redirectMode = Bit בלבד — שורה 29
  - token אם יש tokenId — שורות 31–35
- אין החלטות PCI / og-token / redirect card.

קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutIntentResolver.php`.

### F) PaymentService (עדיין מקבל החלטות)
- `processResolvedIntent()` קורא `processCharge()` בלי `extra` — שורות 932–942
- `buildChargeRequest()` בונה PaymentMethod לפי PCI + RequestHelpers:
  - `og-token` דרך `RequestHelpers::post('og-token')` — שורות 785–800
  - pciMode נקבע בתוך השירות — שורות 774–783
- Redirect flow דורש `RedirectURL/CancelRedirectURL` ב־`$extra` — שורות 774–776 (comment)

קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Services/PaymentService.php`.

### G) TokenService (תלוי ב‑RequestHelpers)
- קורא ישירות מה־Request:
  - `og-ccnum`, `og-cvv`, `og-citizenid`, `og-expmonth`, `og-expyear`, `og-token`
  - קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Services/TokenService.php` שורות 21–35, 105–128

### H) BitPaymentService (flow נפרד)
- מנהל redirect + יצירת transaction עבור Bit:
  - קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Services/BitPaymentService.php` שורות 33–115

### I) TemporaryStorageService
- Intent + ServiceData נשמרים ב־PendingCheckout:
  - קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Services/TemporaryStorageService.php` שורות 46–75

### J) CheckoutViewResolver
- בוחר template לפי PayableType:
  - קובץ: `vendor/officeguy/laravel-sumit-gateway/src/Services/CheckoutViewResolver.php` שורות 38–62

---

## פערים מדויקים מול הארכיטקטורה הנקייה

1) **Controllers מבצעים החלטות עסקיות**
- PublicCheckoutController: PCI/token validation + redirect logic + card/bit routing — שורות 241–257, 260–262, 441–535.

2) **CheckoutIntentResolver לא מכיל החלטות PCI/redirect/card**
- redirectMode נקבע רק לפי Bit — שורה 29.

3) **PaymentService תלוי ב־RequestHelpers**
- משתמש ב־`og-token`, `og-cvv`, `og-citizenid`, `og-expmonth`, `og-expyear` מה־Request במקום לקבל נתונים מוכנים.

4) **CheckoutController (non-public) מחזיק קוד PCI/redirect/token שאינו בשימוש**
- `extra`/`token` לא מועברים ל־processResolvedIntent — שורות 39–56.

5) **Redirect card flow תלוי ב־extra שאינו מועבר**
- `processResolvedIntent()` מעביר extra ריק — שורות 932–942.

---

## תוכנית עבודה מדויקת (Micro/Macro)

### Phase 0 — אימות ומיפוי נתוני קלט (ללא הנחות)
**מטרה:** להבין בדיוק אילו שדות מגיעים בפועל ל־Request עבור כל מסך.
1. **Validate request payload** עבור כל טמפלט (checkout/digital/infrastructure):
   - לאסוף בפועל את ה־request keys בשלב ה־Controller (log או debug) ולוודא:
     - האם `og-token` נשלח בפועל
     - האם `og-cvv` / `og-expmonth` / `og-expyear` נשלחים או רק `cvv`/`exp_month`
   - קבצים רלוונטיים:
     - `resources/views/vendor/officeguy/pages/checkout.blade.php` (שורות 785–787, 736–752)
     - `resources/views/vendor/officeguy/pages/partials/payment-section.blade.php` (שורות 204–255)
2. **למפות את שדות ה־Request → DTO** בפועל לכל תבנית (include טבלת שדות בקובץ).

### Phase 1 — Data Model Refinement (Intent + ResolvedIntent)
**מטרה:** להעביר את כל הנתונים הנדרשים לביצוע לתוך Intent/ResolvedIntent במקום Request.
1. להרחיב את `PaymentPreferences` או ליצור DTO חדש (למשל `PaymentInput`) שמכיל **רק** נתוני קלט:
   - `payment_method`, `payments_count`, `payment_token`, `save_card`
   - `single_use_token` (og-token)
   - `cvv`, `citizen_id`
   - `exp_month`, `exp_year`, `card_number` (ל־PCI yes)
2. לעדכן `CheckoutIntent::fromRequest()` כך שהוא אוסף את כל השדות בפועל (לפי Phase 0).
3. להרחיב `ResolvedPaymentIntent` כך שיכיל **נתוני ביצוע בלבד**, לדוגמה:
   - `redirectMode`
   - `paymentMethodPayload` (מערך מלא שמוכן ל־SUMIT)
   - `redirectUrls` (success/cancel)
   - `environment`, `locale`, `paymentsCount`, `recurring`, `token`

### Phase 2 — להעביר החלטות ל־CheckoutIntentResolver
**מטרה:** Resolver אחראי על כל ההחלטות.
1. **PCI mode decision**:
   - להכניס ל־Resolver קריאה ל־`config('officeguy.pci', 'no')`.
2. **Redirect mode decision**:
   - אם `payment_method === 'bit'` → redirectMode = true
   - אם `pciMode === 'redirect'` → redirectMode = true
3. **Token strategy**:
   - אם `payment_token` קיים → לטעון `OfficeGuyToken`
   - אם אין token → להשתמש ב־single_use_token
4. **Build payment method payload** (ב־Resolver, לא ב־PaymentService):
   - אם token קיים → `TokenService::getPaymentMethodFromToken(..., $cvv)`
   - אם PCI yes → `TokenService::getPaymentMethodPCIFromInput(...)`
   - אם PaymentsJS → `{ SingleUseToken: ..., Type: 1 }`
5. **Prepare redirect URLs**:
   - `success` / `failed` לפי `config('officeguy.routes.*')`

### Phase 3 — להפוך PaymentService ל־Execution בלבד
**מטרה:** PaymentService יקבל payload מוכן, בלי Access ל־Request.
1. לעדכן `processResolvedIntent()` כך שיעביר ל־`processCharge()`:
   - `extra` כולל RedirectURL/CancelRedirectURL כשה־redirectMode true
   - `paymentMethodPayload` מתוך ResolvedIntent
2. לעדכן `buildChargeRequest()` כך שלא יקרא `RequestHelpers::post` כלל.
3. לעדכן `getOrderCustomer()` כך שישתמש ב־citizenId שהועבר מ־ResolvedIntent (במקום RequestHelpers).

### Phase 4 — ניקוי Controller Flow (HTTP only)
**PublicCheckoutController::process**
1. להשאיר רק:
   - resolve Payable
   - `CheckoutRequest` validation
   - `PrepareCheckoutIntentAction` → `CheckoutIntent`
   - `CheckoutIntentResolver::resolve()`
   - `PaymentService::processResolvedIntent()`
2. להסיר את:
   - בלוק PCI/token validation (שורות 241–257)
   - `processCardPayment()` (שורות 441–535)
   - `processBitPayment()` (שורות 544–560) או להעביר את הקריאה ל־PaymentService דרך Resolver.

**CheckoutController::charge**
1. להסיר קוד שאינו בשימוש:
   - pciMode / redirectMode / extra / token (שורות 39–50)
2. להשאיר flow זהה ל־PublicCheckoutController: Intent → Resolver → PaymentService.

### Phase 5 — UI Alignment (לוודא נתוני קלט נכונים)
1. לוודא שכל שדות ה־Request ש־Resolver צריך קיימים ומגיעים בפועל:
   - `payment_method`, `payment_token`, `payments_count`
   - `og-token` (PaymentsJS)
   - `cvv`, `citizen_id`, `exp_month`, `exp_year`, `card_number`
2. אם Resolver דורש שמות שונים, לעדכן view:
   - `resources/views/vendor/officeguy/pages/checkout.blade.php`
   - `resources/views/vendor/officeguy/pages/partials/payment-section.blade.php`
   - `resources/views/vendor/officeguy/pages/digital.blade.php`
   - `resources/views/vendor/officeguy/pages/infrastructure.blade.php`

### Phase 6 — Validation Updates
1. לעדכן `CheckoutRequest` עם ולידציות תלויות מצב PCI:
   - אם `payment_method=card` ו־`pci != redirect` ו־`payment_token` ריק → `og-token` חייב להיות קיים
2. להוסיף `withValidator()` כדי לבצע בדיקות קרוס־שדות לפי `config('officeguy.pci')`.

### Phase 7 — בדיקות זרימה (כתוצאה מהשינוי)
- Card + PaymentsJS (pci=no)
- Card + Redirect (pci=redirect)
- Card + PCI (pci=yes)
- Saved token (payment_token)
- Bit

---

## סיכום מצב נוכחי (ללא השערות)
- DTOs ו־Action קיימים ומחוברים חלקית.
- Resolver קיים אך לא מכיל את החלטות הליבה.
- Controllers עדיין מכילים חלק מהלוגיקה העסקית.
- PaymentService תלוי ב־Request Helpers במקום לקבל נתונים מוכנים.

---

**ממתין לאישורך להמשיך ליישום או לחדד את התוכנית על בסיס החלטות מוצריות/טכניות נוספות.**
