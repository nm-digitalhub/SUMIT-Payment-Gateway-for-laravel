# ניתוח צימוד (coupling) – client_id והתנהגות מתוקנת

**תוכנית ביצוע:** [PLAN_CLIENT_ID_DECOUPLING.md](./PLAN_CLIENT_ID_DECOUPLING.md) – תוכנית פאזות לביטול כתיבת `client_id` בחבילה ושמירה על הפרדת שכבות.

## מהי התלות הלא רצויה

החבילה שומרת בטבלאות שלה:

- `officeguy_transactions.client_id`
- `officeguy_sumit_webhooks.client_id`
- `officeguy_crm_entities.client_id`

הערך שמוכנס לשדות האלה הוא **`$client->id`** – המפתח הראשי של מודל הלקוח של **האפליקציה המארחת**.

כלומר החבילה מניחה:

- שהמארח מחזיק מודל לקוח (למשל `Client`)
- עם PK מספרי
- שניתן למצוא אותו לפי: `email`, `vat_number`/`id_number`, `phone`, `sumit_customer_id`
- ושהשדה הזה נקרא `client_id` גם בטבלאות אחרות (למשל ב־Order)

זו תלות חזקה: חבילה אמורה להיות agnostic למודל הדומיין של האפליקציה.  
אם מישהו יתקין את החבילה בפרויקט **בלי** טבלת `clients` / עמודת `client_id`, החבילה עלולה להישבר או לא להתאים לארכיטקטורה.

---

## איך נוצרת התלות (התבנית ב־3 צעדים)

התלות נוצרת ברגעים שבהם הקוד עושה:

1. **מחפש מודל של האפליקציה**  
   `$customerModel::where(...)` (או `Client::where(...)`)

2. **לוקח את ה־PK שלו**  
   `$client->id`

3. **שומר אותו בטבלה של החבילה**  
   `'client_id' => $client->id`

זה הרגע שבו הדומיין של האפליקציה "דולף" לתוך החבילה.

---

## מקומות שבהם התבנית הזו קיימת (צימוד ל־client_id)

| # | קובץ | שורות | מה קורה |
|---|------|--------|----------|
| 1 | `src/Models/SumitWebhook.php` | 267, 277, 291–331 | `matchClientIdFromPayload()` → `$customerModel::where('sumit_customer_id', …)->first()` (ואימייל/VAT/טלפון) → `$client->id` → `SumitWebhook::create(..., 'client_id' => $clientId)` |
| 2 | `src/Models/OfficeGuyTransaction.php` | 242–244, 247–252, 263 | (א) `$clientId = (int) data_get($request, 'Customer.ExternalIdentifier')` ואז `create(..., 'client_id' => $clientId)`; (ב) אחרת `$customerModel::where('sumit_customer_id', …)->first()` → `$client?->id` → `create(..., 'client_id' => $clientId)` |
| 3 | `src/Services/CrmDataService.php` | 522, 532–538, 912–963 | `matchClientId($entityData, $sumitEntityId)` → `$customerModel::where('sumit_customer_id', …)` (ואימייל/VAT/טלפון) → `$client->id` → `CrmEntity::updateOrCreate(..., 'client_id' => $clientId)` |
| 4 | `src/Listeners/AutoCreateUserListener.php` | 77–80, 118–121 | עדכון **טבלת המארח**: `$order->update(['client_id' => $client->id])` – החבילה דוחפת `client_id` למודל Order של המארח |

בנוסף:

- **Policy** – `src/Policies/OfficeGuyTransactionPolicy.php` (33–37) משתמש ב־`$transaction->client_id` ו־`$user->client_id` לאישור; אבל יש fallback (39–40) ל־`customer_id` / `sumit_customer_id` (ראו להלן).
- **SuccessAccessValidator** – משתמש ב־`client_id` אם קיים (255, 264).

---

## מקומות שבהם ההתנהגות כבר מנותקת / עובדת כנדרש

כאן החבילה **לא** שומרת את ה־PK של המארח (`$client->id`). היא שומרת **מזהה חיצוני (SUMIT)** או ערך מהבקשה/API, וה־relationship (אם קיים) מתבסס על השדה הזה במודל המארח.

### 1. OfficeGuyDocument – רק מזהה SUMIT

- **טבלה:** `officeguy_documents`
- **שדה:** `customer_id` = **מזהה הלקוח ב־SUMIT** (`CustomerID` מתשובת/בקשת API).
- **אין שדה `client_id`** במודל.

**יצירה:**

- `OfficeGuyDocument::createFromApiResponse()` (שורות 147–160):  
  `'customer_id' => $data['CustomerID'] ?? null` – **רק ערך מהתשובה**, בלי שאילתה למודל לקוח.
- `OfficeGuyDocument::createFromListResponse()` (שורות 211–227):  
  `'customer_id' => $doc['CustomerID'] ?? null`.
- `DocumentService::sync...` (למשל שורות 424–429):  
  `'customer_id' => $doc['CustomerID'] ?? null`.

**Relationship:**

```php
return $this->belongsTo($customerModel, 'customer_id', 'sumit_customer_id');
```

- המפתח הזר בטבלת החבילה: `customer_id` (ערך SUMIT).
- המפתח במודל המארח: `sumit_customer_id`.
- אם המארח לא מגדיר `officeguy.customer_model` – ה־relationship מחזיר "empty" (`whereRaw('1 = 0')`), וה־`customer_id` נשאר ערך תקף (מזהה SUMIT) בלי תלות ב־PK של המארח.

**מסקנה:** כאן אין דליפה של `$client->id`; החבילה agnostic למודל הלקוח של המארח מבחינת **אחסון**.

---

### 2. WebhookEvent – רק ערך מ־payload/options

- **טבלה:** `officeguy_webhook_events`
- **שדה:** `customer_id` – מגיע מ־payload או options, **לא** מ־`$client->id`.

**יצירה:**

- `WebhookEvent::createEvent()` (שורות 200–201):  
  `'customer_id' => $payload['customer_id'] ?? $options['customer_id'] ?? null`

אין כאן `$customerModel::where(...)->first()` ואין `'client_id' => $client->id`.  
החבילה לא מניחה טבלת לקוחות במארח לצורך שדה זה.

---

### 3. OfficeGuyTransactionPolicy – fallback בלי client_id

ב־`view()` (שורות 31–41):

- אם יש `client_id`: בודק `$transaction->client_id === $user->client_id`.
- **אבל** יש fallback (39–40):  
  `(string) $transaction->customer_id === (string) ($user->sumit_customer_id ?? '')`  
  או השוואה ל־`order_id` / `$user->id`.

כלומר האישור **יכול** לעבוד גם כש־`client_id` ריק – באמצעות `customer_id` (מזהה SUMIT) ו־`sumit_customer_id` על המשתמש.  
ההתנהגות "המתוקנת" כאן: לא להסתמך רק על `client_id`; לאפשר זיהוי לפי מזהה SUMIT.

---

### 4. DebtService / CheckSumitDebtJob – עבודה ב־sumit_customer_id

- **DebtService:**  
  `getCustomerBalanceById(int $sumitCustomerId)` – מקבל **מזהה SUMIT** בלבד; אין שימוש ב־מודל Client של המארח.
- **CheckSumitDebtJob:**  
  עובד על `CrmEntity` עם `sumit_customer_id`; שולח לינק חוב לפי `(int) $entity->sumit_customer_id`.  
  אימייל/טלפון: `$entity->email ?? $entity->client?->email` – שימוש ב־`client` כ־**אופציונלי** (fallback), לא כחובה לאחסון או לזיהוי.

כאן הלוגיקה מבוססת על מזהה SUMIT, לא על PK של המארח.

---

### 5. OfficeGuyServiceProvider – customer_model אופציונלי

- `officeguy.customer_model` יכול להיות `null` (אם אין DB/config).
- מודלים כמו `SumitWebhook`, `OfficeGuyTransaction`, `CrmEntity` מטפלים ב־`! $customerModel` עם `whereRaw('1 = 0')` ב־relationship.

כלומר הממשק מאפשר "בלי מודל לקוח", אבל **במקומות שבהם נכתב `client_id`** הקוד עדיין מחשב `$client->id` כשהמודל מוגדר – ולכן הצימוד נשאר שם.

---

## איך זה מתוקן ועובד כנדרש (עקרון)

הדפוס המנותק:

1. **אל תשמור PK של המארח** – אל תשמור `$client->id` בעמודה בטבלאות החבילה.
2. **שמור רק מזהה חיצוני (SUMIT)** – עמודה כמו `customer_id` = `CustomerID` מ־API / payload.
3. **Relationship אופציונלי** – אם יש מודל לקוח במארח, השתמש ב־`belongsTo($customerModel, 'customer_id', 'sumit_customer_id')` (מפתח זר = ערך SUMIT, מפתח במודל = `sumit_customer_id`). אם אין מודל – relationship ריק.
4. **אישור/לוגיקה** – לאפשר fallback לפי `customer_id` / `sumit_customer_id` כשאין `client_id`.

הדוגמה העיקרית ליישום עקרונות אלה בחבילה: **OfficeGuyDocument** (ו־**WebhookEvent**), ו־**OfficeGuyTransactionPolicy** (fallback).

---

## המלצה ליישור שאר החבילה

כדי להקטין צימוד ל־client_id:

1. **SumitWebhook**  
   - להפסיק לחשב ולשמור `client_id` מ־`matchClientIdFromPayload`.  
   - להשאיר רק `customer_id` = מזהה SUMIT מה־payload.  
   - relationship ללקוח (אם רוצים) דרך `customer_id` ↔ `sumit_customer_id` כמו ב־OfficeGuyDocument.

2. **OfficeGuyTransaction**  
   - לא להשתמש ב־`Customer.ExternalIdentifier` כ־`client_id` (מניעת בלבול עם PK של המארח).  
   - לא לשמור `client_id` שמקורו ב־`$customerModel::where(...)->first()->id`.  
   - לשמור רק `customer_id` / `sumit_customer_id_used` (מזהה SUMIT).  
   - Policy כבר תומך ב־fallback לפי `customer_id` / `sumit_customer_id`.

3. **CrmEntity (CrmDataService)**  
   - להפסיק לחשב `matchClientId` ולשמור `client_id`.  
   - לשמור בישות רק מזהה SUMIT (כבר קיים שדה מתאים, למשל `sumit_customer_id` אם קיים ב־entity), ולקשר ללקוח מארח רק דרך relationship שמתבסס עליו.

4. **AutoCreateUserListener**  
   - לא לעדכן `$order->update(['client_id' => $client->id])` מהחבילה – או להפוך זאת לאופציונלי (רק אם המארח מספק callback/config שמציין שהוא רוצה שהחבילה תעדכן שדה כזה).  
   - כך המארח לא חייב עמודה `client_id` ב־Order.

5. **מיגרציות**  
   - להשאיר את עמודת `client_id` (למשל ל־backward compatibility) אבל **לא** למלא אותה מקוד החבילה; או לתעד שהעמודה "deprecated" ושהיא לא נדרשת למארחים חדשים.

---

## סיכום טבלאי

| טבלה/מודל | שדה | מקור הערך כיום | התנהגות מתוקנת (דוגמה קיימת) |
|-----------|------|-----------------|-------------------------------|
| officeguy_documents | customer_id | `$data['CustomerID']` / `$doc['CustomerID']` | ✅ כבר כך – רק SUMIT |
| officeguy_webhook_events | customer_id | payload / options | ✅ כבר כך – בלי client |
| officeguy_sumit_webhooks | client_id | `matchClientIdFromPayload` → `$client->id` | לעבור ל־customer_id (SUMIT) בלבד |
| officeguy_transactions | client_id | ExternalIdentifier / `$client?->id` | לעבור ל־customer_id + sumit_customer_id_used בלבד |
| officeguy_crm_entities | client_id | `matchClientId` → `$client->id` | לעבור למזהה SUMIT בלבד (שדה/relationship) |
| Order (מארח) | client_id | AutoCreateUserListener מעדכן | לא לעדכן מהחבילה (או אופציונלי via config) |

מסמך זה מתאר איפה נוצרת התלות ב־client_id, איפה הקוד כבר מנותק ועובד כנדרש, וכיצד לאחד את שאר החבילה לאותה גישה.

---

## שימוש נכון בחבילה באפליקציה המארחת – שמירה על הפרדת שכבות

הנחיות לאינטגרציה של האפליקציה המארחת (host) עם החבילה, כך שהדומיין של המארח לא "ידלוף" לחבילה והשכבות יישארו מופרדות.

### עקרון מרכזי

- **החבילה אמורה לעבוד עם מזהה חיצוני (SUMIT)** – `CustomerID` / `sumit_customer_id` – ולא עם המפתח הראשי של המודל שלך (`id` של Client, Organization, Account וכו').
- **המארח מחליט** איזה מודל דומיין משמש כ־"לקוח" עבור SUMIT (למשל Organization, Account, Client). על המודל הזה לעמוד בחוזה המינימלי (ראו להלן).
- **אל תבנה לוגיקה באפליקציה שתלויה ב־`client_id`** שמולא על ידי החבילה בטבלאות שלה – השדה הזה יוצא משימוש (deprecated) בהתאם להמלצות במסמך.

---

### 1. הגדרת מודל הלקוח (customer model)

- מגדירים ב־`config/officeguy.php` (או ב־DB דרך `officeguy_settings`) את מודל הלקוח:
  - `customer_model_class` / `models.customer` → מחלקת המודל (למשל `App\Models\Organization` או `App\Models\Account`).
- **חוזה מינימלי למודל הלקוח (אם החבילה מחפשת/מקשרת לפי לקוח):**
  - **מזהה SUMIT:** שדה `sumit_customer_id` (או תמיכה ב־`HasSumitCustomer` עם `getSumitCustomerId()`) – **חובה** אם אתה רוצה שהחבילה תקשור תשלומים/מסמכים/CRM לישות שלך.
  - **לחיפוש/מיפוי (אם החבילה עדיין משתמשת ב־matchClientId וכו'):** שדות כמו `email`, `vat_number` או `id_number`, `phone` – לפי מה שהחבילה מחפשת (ראו `matchClientIdFromPayload` / `matchClientId`). אם אתה לא רוצה שהחבילה תמלא `client_id`, אפשר לא לחשוף מודל לקוח (לא להגדיר) או לחשוף מודל בלי להסתמך על `client_id`.
- **הפרדת שכבות:** בחר מודל אחד בדומיין שלך כ־"לקוח SUMIT" (למשל חשבון חיוב, ארגון, או לקוח). עדיף ששדה `sumit_customer_id` יישמר **בישות הזו** ולא רק בישות מקושרת, כדי שהחבילה תקבל ערך עקבי מ־`getSumitCustomerId()`.

**דוגמה (מבנה אפשרי):**  
אם יש לך `Account` (חשבון חיוב) עם `sumit_customer_id` ו־`Organization` מקושר ל־Account – אפשר להגדיר את `Account` כמודל הלקוח, או לחשוף ב־Organization accessor ל־`sumit_customer_id` מהחשבון (כך שהחבילה עדיין מקבלת מזהה SUMIT ולא את ה־PK הפנימי).

---

### 2. Payable (הזמנה/תשלום חד־פעמי)

- ה־Payable שמעבירים ל־`PaymentService::processCharge` וכו' מחזיר מ־`getCustomerId()` ערך.
- **מומלץ (שמירה על הפרדה):** להחזיר **מזהה SUMIT** (`sumit_customer_id`) כשיש – כך החבילה לא תצטרך "לנחש" לפי PK מקומי ולא תשמור אותו כ־`client_id`.
- **אם מחזירים PK מקומי (למשל `organization_id`):** החבילה עלולה להשתמש בו כ־`Customer.ExternalIdentifier` או לפתור מודל לקוח ולשמור את ה־PK כ־`client_id` – וזה יוצר את הצימוד המתואר במסמך.
- **בדומיין שלך:** שמור את `sumit_customer_id` בישות הלקוח (או במודל שמקושר ל־Payable), ובתוך ה־Payable החזר ב־`getCustomerId()` את `$this->eventBilling->organization->account->sumit_customer_id ?? null` (או את הנתיב המתאים בדומיין שלך) במקום `organization_id`.

---

### 3. מודל "הזמנה" (order / Payable)

- `config/officeguy.php` → `models.order` (למשל `App\Models\EventBilling`) מגדיר איזה מודל משמש כ־Payable כשהחבילה צריכה לגשת להזמנה (למשל אחרי תשלום).
- **אל תסמוך על כך שהחבילה תעדכן שדה `client_id` בטבלה שלך** (למשל ב־Order/EventBilling). לפי המסמך, עדכון כזה על ידי החבילה הוא תלות לא רצויה; עדיף שהמארח לא יחייב עמודה כזו, או שהחבילה לא תעדכן אותה.
- **המארח מחזיק את הקשר:** הזמנה (EventBilling) שייכת לארגון/חשבון – הקשר הזה נשאר בדומיין שלך. החבילה צריכה רק מזהה SUMIT ללקוח ופרטי תשלום/מסמך.

---

### 4. שימוש ב־Documents ו־Transactions של החבילה

- **מסמכים (OfficeGuyDocument):** החבילה שומרת `customer_id` = מזהה SUMIT בלבד. קישור ללקוח במארח: דרך `$document->customer` (relationship שמתבסס על `customer_id` ↔ `sumit_customer_id`). באפליקציה שלך – שאילתות לפי `sumit_customer_id` (למשל "כל המסמכים של הארגון שלי") יעשו דרך המודל שלך + `sumit_customer_id`, לא דרך `client_id`.
- **תשלומים (OfficeGuyTransaction):** כיום החבילה עדיין ממלאת `client_id`. עד לעדכון החבילה – **אל תבנה לוגיקה שמסתמכת על `client_id`**. השתמש ב־`customer_id` / `sumit_customer_id_used` כדי לקשר תשלום ללקוח שלך (למשל `Organization`/`Account` עם אותו `sumit_customer_id`).
- **Policy / SuccessAccessValidator:** אם המשתמש שלך חושף `sumit_customer_id` (למשל דרך Account או Organization), האישור יכול לעבוד לפי `customer_id` / `sumit_customer_id` גם בלי `client_id`.

---

### 5. Webhooks ו־CRM

- **Webhooks:** החבילה רושמת ב־`officeguy_sumit_webhooks` גם `customer_id` (מזהה SUMIT מה־payload). עדיף לקשר אירועים ללקוח לפי `customer_id` (SUMIT) ולא לפי `client_id`.
- **CRM (CrmEntity, CrmActivity):** אם אתה משתמש ב־CRM של החבילה, כיום החבילה עדיין ממלאת `client_id` מתוך מודל הלקוח. עד לעדכון – השתמש בזה רק אם אתה מוכן שהמארח יחזיק מודל לקוח עם שדות החיפוש שהחבילה מצפה להם; אחרת העדף שאילתות לפי מזהה SUMIT / שדות ישות ה־CRM.

---

### 6. סיכום – מה לעשות באפליקציה המארחת

| נושא | פעולה מומלצת |
|------|----------------|
| **מודל לקוח** | הגדר מודל אחד (Organization, Account, Client…) עם `sumit_customer_id`; צמצם חשיפה של PK מקומי לחבילה. |
| **Payable::getCustomerId()** | החזר `sumit_customer_id` כשיש; אל תחזיר רק `organization_id`/`client_id` כ־מזהה ל־SUMIT. |
| **קישור תשלומים/מסמכים** | קשר לפי `customer_id` / `sumit_customer_id` בטבלאות החבילה; אל תבנה על `client_id`. |
| **Order/Payable במארח** | אל תסמוך על עדכון `client_id` על ידי החבילה; החזק קשר organization/account בדומיין שלך. |
| **Config** | הגדר `customer_model_class` / `models.customer` ו־`models.order` בצורה עקבית; העדף מקור אחד (config) על פני .env למודלים. |

יישום ההנחיות האלה באפליקציה המארחת מקטין צימוד ל־`client_id` ומשאיר את השכבות מופרדות: החבילה עובדת עם מזהה SUMIT, והדומיין של המארח נשאר הבעלים של ישויות הלקוח וההזמנות.

---

### דוגמה: אפליקציה עם Organization / Account (השראה ממבנה קיים)

- **דומיין:** ארגונים (Organization) מקושרים לחשבון חיוב (Account). `sumit_customer_id` שמור ב־Account. EventBilling שייך ל־Organization (ול־Event).
- **Config:** `customer_model_class` => `App\Models\Organization`. כדי שהחבילה תקבל מזהה SUMIT, יש לחשוף אותו ממודל הלקוח – למשל ב־Organization: accessor ל־`sumit_customer_id` מה־Account, או להגדיר את Account כמודל הלקוח אם זה תואם יותר את הדומיין.
- **Payable (EventBilling):** כיום `getCustomerId()` יכול להחזיר `organization_id` (PK מקומי) – אז החבילה עלולה לשמור אותו כ־`client_id`. **שמירה על הפרדה:** להחזיר `$this->eventBilling->organization?->account?->sumit_customer_id` (או הנתיב המקביל) כשיש, ו־`null` כשאין; כך החבילה עובדת עם מזהה SUMIT בלבד ולא עם PK של המארח.
- **קישור תשלום/מסמך בארגון:** בשכבת האפליקציה – לשלוף תשלומים/מסמכים לפי `Organization`/`Account` שיש להם `sumit_customer_id` זהה ל־`OfficeGuyTransaction.customer_id` או `OfficeGuyDocument.customer_id`, בלי להסתמך על `client_id`.
