# Transaction Payload Mapping Guide - מדריך מיפוי Payload לשדות

> **מטרה**: תיעוד מלא של מיפוי הנתונים מ-JSON Payload (SUMIT API) לשדות במודל ול-UI

**תאריך יצירה**: 2026-01-03
**גרסה**: 1.0.0
**חבילה**: `officeguy/laravel-sumit-gateway`

---

## 📋 תוכן עניינים

1. [סקירה כללית](#סקירה-כללית)
2. [מבנה ה-Payload המלא](#מבנה-ה-payload-המלא)
3. [מיפוי JSON → Model](#מיפוי-json--model)
4. [מיפוי Model → UI Components](#מיפוי-model--ui-components)
5. [ארכיטקטורת עץ ה-JSON](#ארכיטקטורת-עץ-ה-json)
6. [איפה להציג מה - מדריך החלטות](#איפה-להציג-מה---מדריך-החלטות)

---

## 🎯 סקירה כללית

### המבנה התלת-שכבתי

```
┌─────────────────────────────────────────────────────────────┐
│  1. SUMIT API Response (JSON)                               │
│     raw_request + raw_response                              │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          │ createFromApiResponse()
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  2. OfficeGuyTransaction Model (Database Columns)           │
│     payment_id, amount, status, last_digits, etc.           │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          │ TransactionInfolist::configure()
                          ▼
┌─────────────────────────────────────────────────────────────┐
│  3. Filament UI (User Interface)                            │
│     Cards, Badges, TextEntries, JSON Tree                   │
└─────────────────────────────────────────────────────────────┘
```

### קבצים רלוונטיים

| שכבה | קובץ | תפקיד |
|------|------|-------|
| **API** | `OfficeGuyApi.php` | HTTP Client לשליחה/קבלה |
| **Model** | `OfficeGuyTransaction.php` | מודל Eloquent + `createFromApiResponse()` |
| **UI - Form** | `TransactionForm.php` | טופס עריכה (נכון לעכשיו - מוצג בלבד) |
| **UI - Infolist** | `TransactionInfolist.php` | **📍 כאן מציגים שדות "למעלה"** |
| **UI - Table** | `TransactionsTable.php` | טבלת רשימה |
| **UI - Page** | `ViewTransaction.php` | Actions בלבד (Refund, Open Client, etc.) |
| **Blade - Tree** | `api-payload-node.blade.php` | עץ JSON אינטראקטיבי |

---

## 📦 מבנה ה-Payload המלא

### 1️⃣ `raw_request` - בקשה ל-SUMIT

```json
{
  "Credentials": {
    "CompanyID": 1082100759,
    "APIKey": "ab9IdSvCFTjI5gnTYXxGEGCRu8mYP5a5ILhhLqQATzg8iKOg6J"
  },
  "Customer": {
    "Name": "NM-DigitalHub - Netanel Mevorach KALFA",
    "EmailAddress": "admin@nm-digitalhub.com",
    "Phone": "0532743588",
    "SearchMode": "Automatic",
    "ExternalIdentifier": "7",           // ← client_id (App\Models\Client)
    "Address": "רחוב הרב בלולו 4",
    "City": "אשדוד, Not Applicable",
    "ZipCode": "7753802",
    "CompanyNumber": "316125434"
  },
  "Items": {
    "Item": {
      "ExternalIdentifier": 408,         // ← order_id
      "Name": "Belgium 1GB - 5 Days",
      "SKU": "rCWuVXnwjtgy",
      "SearchMode": "Automatic"
    },
    "Quantity": 1,
    "UnitPrice": 7.22,
    "Currency": "ILS",
    "Duration_Days": "0",
    "Duration_Months": "0",
    "Recurrence": "0"
  },
  "VATIncluded": true,
  "VATRate": 0.17,
  "AuthoriseOnly": false,
  "DraftDocument": false,
  "SendDocumentByEmail": true,
  "UpdateCustomerByEmail": true,
  "UpdateCustomerOnSuccess": true,
  "DocumentDescription": "eSIM Package Purchase",
  "Payments_Count": 1,                   // ← payments_count
  "MaximumPayments": 1,
  "DocumentLanguage": "he",
  "MerchantNumber": null,
  "PaymentMethod": {
    "CreditCard_Token": "5c84abf2-bcc1-41c3-b099-29c624cbb682",  // ← payment_token
    "CreditCard_CVV": null,
    "CreditCard_CitizenID": "316125434",
    "CreditCard_ExpirationMonth": "09",  // ← expiration_month
    "CreditCard_ExpirationYear": "2031", // ← expiration_year
    "Type": 1                             // ← card_type (1=credit, 2=debit)
  }
}
```

### 2️⃣ `raw_response` - תשובה מ-SUMIT

```json
{
  "Status": 0,                            // ← 0 = Success, >0 = Error
  "UserErrorMessage": "",
  "TechnicalErrorDetails": "",
  "Data": {
    "Payment": {
      "ID": 1314635826,                   // ← payment_id
      "CustomerID": 1314635824,           // ← customer_id (SUMIT Customer ID)
      "Date": "2025-12-30T10:15:30",
      "ValidPayment": true,               // ← status = completed/failed
      "Status": 0,
      "StatusDescription": "מאושר (קוד 000)", // ← status_description
      "Amount": 7.22,                     // ← amount
      "Currency": 0,                      // ← currency (0=ILS, 1=USD, 2=EUR, 3=GBP)
      "PaymentMethod": {
        "ID": 1314635825,
        "CustomerID": null,
        "CreditCard_Number": null,
        "CreditCard_LastDigits": "9429", // ← last_digits
        "CreditCard_ExpirationMonth": 9, // ← expiration_month
        "CreditCard_ExpirationYear": 2031, // ← expiration_year
        "CreditCard_CVV": null,
        "CreditCard_Track2": null,
        "CreditCard_CitizenID": "316125434",
        "CreditCard_CardMask": "XXXXXXXXXXXX9429",
        "CreditCard_Token": "5c84abf2-bcc1-41c3-b099-29c624cbb682", // ← payment_token
        "DirectDebit_Bank": null,
        "DirectDebit_Branch": null,
        "DirectDebit_Account": null,
        "DirectDebit_ExpirationDate": null,
        "DirectDebit_MaximumAmount": null,
        "Type": 1                         // ← card_type
      },
      "AuthNumber": " 072121",            // ← auth_number
      "FirstPaymentAmount": 7.22,         // ← first_payment_amount
      "NonFirstPaymentAmount": null,      // ← non_first_payment_amount
      "RecurringCustomerItemIDs": []
    },
    "DocumentID": 1314635832,             // ← document_id
    "DocumentNumber": 40037,
    "CustomerID": 1314635824,             // ← customer_id
    "DocumentDownloadURL": "https://pay.sumit.co.il/..."
  }
}
```

---

## 🗺️ מיפוי JSON → Model

### קובץ: `OfficeGuyTransaction.php:158-252`

#### פונקציה: `createFromApiResponse()`

```php
public static function createFromApiResponse(
    string|int $orderId,
    array $response,
    array $request = [],
    ?string $orderType = null
): static
```

### טבלת מיפוי מלאה

| Model Column | JSON Path (Response) | JSON Path (Request) | Data Type | Notes |
|--------------|---------------------|---------------------|-----------|-------|
| **Payment Info** |
| `payment_id` | `Data.Payment.ID` | - | `string` | מזהה תשלום SUMIT |
| `auth_number` | `Data.Payment.AuthNumber` | - | `string` | מספר אישור מסליקה |
| `amount` | `Data.Payment.Amount` | - | `decimal:2` | סכום |
| `first_payment_amount` | `Data.Payment.FirstPaymentAmount` | - | `decimal:2` | תשלום ראשון |
| `non_first_payment_amount` | `Data.Payment.NonFirstPaymentAmount` | - | `decimal:2` | תשלומים נוספים |
| `currency` | `Data.Payment.Currency` | `Items.Currency` | `string` | מטבע (0→ILS, 1→USD, 2→EUR, 3→GBP) |
| `payments_count` | - | `Payments_Count` | `integer` | מספר תשלומים |
| **Status & Description** |
| `status` | `Status` + `Data.Payment.ValidPayment` | - | `string` | `completed` / `failed` / `pending` / `refunded` |
| `status_description` | `Data.Payment.StatusDescription` | - | `string` | תיאור סטטוס מסליקה |
| `error_message` | `UserErrorMessage` | - | `string` | הודעת שגיאה למשתמש |
| **Transaction Type** |
| `transaction_type` | - | - | `string` | `charge` / `refund` / `void` (**Derived field** - נקבע לפי הקשר עסקי, לא מה-API) |
| `parent_transaction_id` | - | - | `integer` | קישור לחיוב מקורי (אם זה refund) |
| `refund_transaction_id` | - | - | `integer` | קישור לזיכוי (אם בוצע refund) |
| **Payment Method** |
| `payment_method` | - | - | `string` | `card` / `bit` (**Derived field** - נגזר מסוג התשלום, לא שדה ישיר) |
| `payment_token` | `Data.Payment.PaymentMethod.CreditCard_Token` | `PaymentMethod.CreditCard_Token` | `string` | Token לכרטיס שמור |
| `last_digits` | `Data.Payment.PaymentMethod.CreditCard_LastDigits` | - | `string` | 4 ספרות אחרונות |
| `expiration_month` | `Data.Payment.PaymentMethod.CreditCard_ExpirationMonth` | `PaymentMethod.CreditCard_ExpirationMonth` | `string` | חודש תפוגה |
| `expiration_year` | `Data.Payment.PaymentMethod.CreditCard_ExpirationYear` | `PaymentMethod.CreditCard_ExpirationYear` | `string` | שנת תפוגה |
| `card_type` | `Data.Payment.PaymentMethod.Type` | `PaymentMethod.Type` | `string` | 1=Credit, 2=Debit |
| **Document & Customer** |
| `document_id` | `Data.DocumentID` | - | `string` | מזהה מסמך SUMIT |
| `customer_id` | `Data.CustomerID` | - | `string` | **Legacy** - מזהה לקוח SUMIT |
| `client_id` | - | `Customer.ExternalIdentifier` | `integer` | **Canonical** - App\Models\Client |
| `sumit_customer_id_used` | `Data.CustomerID` | - | `string` | מה ש-SUMIT בעצם השתמש |
| **Order Linking** |
| `order_id` | - | `Items.Item.ExternalIdentifier` | `integer` | מזהה ההזמנה המקומית |
| `order_type` | - | Parameter | `string` | `App\Models\Order` / `App\Models\Subscription` |
| **Metadata** |
| `source` | - | `_source` / `_webhook` | `string` | `checkout` / `webhook` / `api_polling` |
| `environment` | - | config | `string` | `www` / `dev` |
| `is_test` | - | config | `boolean` | האם זו עסקה בדיקה |
| `completed_at` | - | - | `datetime` | מתי הושלם התשלום |
| `notes` | - | - | `text` | הערות פנימיות |
| **Raw Data** |
| `raw_request` | - | **Entire $request** | `array` | JSON מלא של הבקשה |
| `raw_response` | **Entire $response** | - | `array` | JSON מלא של התשובה |

### לוגיקת מיפוי מיוחדת

#### 1. Currency Mapping

**חשוב**: ב-SUMIT API המטבע מגיע כ-**enum** (מספר), אבל נשמר ב-DB כ-**string**.

```php
$currencyMap = [0 => 'ILS', 1 => 'USD', 2 => 'EUR', 3 => 'GBP'];
$currencyEnum = $payment['Currency'] ?? null;  // 0, 1, 2, 3 מה-API
$currency = $currencyMap[$currencyEnum] ?? config('app.currency', 'ILS');  // 'ILS', 'USD' ב-DB
```

| API Response | DB Column |
|--------------|-----------|
| `Currency: 0` | `currency: "ILS"` |
| `Currency: 1` | `currency: "USD"` |
| `Currency: 2` | `currency: "EUR"` |
| `Currency: 3` | `currency: "GBP"` |

#### 2. Status Logic
```php
'status' => ($response['Status'] === 0 && ($payment['ValidPayment'] ?? false))
    ? 'completed'
    : 'failed',
```

#### 3. Client ID Resolution
```php
// Priority 1: ExternalIdentifier from request
$externalId = data_get($request, 'Customer.ExternalIdentifier');
if ($externalId && is_numeric($externalId)) {
    $clientId = (int) $externalId;
}

// Priority 2: Find by sumit_customer_id
if (!$clientId && $sumitCustomerIdUsed) {
    $client = \App\Models\Client::where('sumit_customer_id', $sumitCustomerIdUsed)->first();
    $clientId = $client?->id;
}
```

---

## 🎨 מיפוי Model → UI Components

### קובץ: `TransactionInfolist.php` - **🚨 כרגע ריק! זה המקום להדביק UI**

**מיקום**: `src/Filament/Resources/Transactions/Schemas/TransactionInfolist.php:9-15`

```php
public static function configure(Schema $schema): Schema
{
    return $schema
        ->components([
            // 👈 כאן מציגים שדות "למעלה"!
        ]);
}
```

### 📐 תבנית מומלצת - "שדות למעלה"

```php
use Filament\Infolists\Components as InfolistComponents;
use Filament\Schemas\Components as Schemas;

public static function configure(Schema $schema): Schema
{
    return $schema->components([

        // ═══════════════════════════════════════════════════════
        // 1️⃣ Payment Summary Card (סטטוס + סכום + מזהה)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('סיכום תשלום')
            ->schema([
                InfolistComponents\TextEntry::make('status')
                    ->label('סטטוס')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'completed' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        'pending' => 'heroicon-o-clock',
                        'refunded' => 'heroicon-o-arrow-path',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'completed' => 'הושלם',
                        'failed' => 'נכשל',
                        'pending' => 'ממתין',
                        'refunded' => 'זוכה',
                        default => $state,
                    }),

                InfolistComponents\TextEntry::make('amount')
                    ->label('סכום')
                    ->formatStateUsing(function ($state, $record) {
                        $currency = $record->currency ?? 'ILS';
                        $symbol = match (strtoupper($currency)) {
                            'ILS' => '₪',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            default => $currency,
                        };
                        return $symbol . ' ' . number_format((float) $state, 2);
                    })
                    ->weight('bold')
                    ->size('lg'),

                InfolistComponents\TextEntry::make('payment_id')
                    ->label('מזהה תשלום SUMIT')
                    ->copyable()
                    ->icon('heroicon-o-credit-card'),

                InfolistComponents\TextEntry::make('auth_number')
                    ->label('מספר אישור')
                    ->copyable()
                    ->icon('heroicon-o-shield-check'),
            ])
            ->columns(4)
            ->columnSpanFull(),

        // ═══════════════════════════════════════════════════════
        // 2️⃣ Customer Information (לקוח)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('פרטי לקוח')
            ->schema([
                InfolistComponents\TextEntry::make('customer_id')
                    ->label('מזהה לקוח SUMIT')
                    ->copyable(),

                InfolistComponents\TextEntry::make('client.name')
                    ->label('שם לקוח')
                    ->default('לא מקושר')
                    ->url(function ($record) {
                        if ($record->client_id) {
                            return route('filament.admin.resources.clients.view', ['record' => $record->client_id]);
                        }
                        return null;
                    })
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-user'),

                InfolistComponents\TextEntry::make('client.email')
                    ->label('אימייל')
                    ->copyable()
                    ->icon('heroicon-o-envelope'),
            ])
            ->columns(3)
            ->columnSpanFull()
            ->collapsible(),

        // ═══════════════════════════════════════════════════════
        // 3️⃣ Card Details (פרטי כרטיס)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('פרטי כרטיס')
            ->schema([
                InfolistComponents\TextEntry::make('card_type')
                    ->label('סוג כרטיס')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        '1' => 'אשראי',
                        '2' => 'חיוב מיידי',
                        default => 'לא ידוע',
                    })
                    ->badge(),

                InfolistComponents\TextEntry::make('last_digits')
                    ->label('4 ספרות אחרונות')
                    ->formatStateUsing(fn ($state) => $state ? '****' . $state : '-')
                    ->weight('bold'),

                InfolistComponents\TextEntry::make('expiration_month')
                    ->label('חודש תפוגה')
                    ->formatStateUsing(fn ($state, $record) =>
                        $state && $record->expiration_year
                            ? str_pad($state, 2, '0', STR_PAD_LEFT) . '/' . $record->expiration_year
                            : '-'
                    ),
            ])
            ->columns(3)
            ->columnSpanFull()
            ->collapsible(),

        // ═══════════════════════════════════════════════════════
        // 4️⃣ Payment Plan (תשלומים)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('פירוט תשלומים')
            ->schema([
                InfolistComponents\TextEntry::make('payments_count')
                    ->label('מספר תשלומים')
                    ->badge()
                    ->color(fn ($state) => $state > 1 ? 'warning' : 'success'),

                InfolistComponents\TextEntry::make('first_payment_amount')
                    ->label('תשלום ראשון')
                    ->formatStateUsing(function ($state, $record) {
                        $currency = $record->currency ?? 'ILS';
                        $symbol = match (strtoupper($currency)) {
                            'ILS' => '₪',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            default => $currency,
                        };
                        return $symbol . ' ' . number_format((float) $state, 2);
                    })
                    ->visible(fn ($record) => $record->payments_count > 1),

                InfolistComponents\TextEntry::make('non_first_payment_amount')
                    ->label('תשלומים נוספים')
                    ->formatStateUsing(function ($state, $record) {
                        $currency = $record->currency ?? 'ILS';
                        $symbol = match (strtoupper($currency)) {
                            'ILS' => '₪',
                            'USD' => '$',
                            'EUR' => '€',
                            'GBP' => '£',
                            default => $currency,
                        };
                        return $symbol . ' ' . number_format((float) $state, 2);
                    })
                    ->visible(fn ($record) => $record->payments_count > 1),
            ])
            ->columns(3)
            ->columnSpanFull()
            ->visible(fn ($record) => $record->payments_count > 1)
            ->collapsible()
            ->collapsed(),

        // ═══════════════════════════════════════════════════════
        // 5️⃣ Document & Metadata (מסמך ומטא-דאטה)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('מידע נוסף')
            ->schema([
                InfolistComponents\TextEntry::make('document_id')
                    ->label('מזהה מסמך')
                    ->url(function ($record) {
                        $docId = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument::query()
                            ->where('document_id', $record->document_id)
                            ->value('id');
                        return $docId ? route('filament.admin.sumit-gateway.resources.documents.view', ['record' => $docId]) : null;
                    })
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-document-text'),

                InfolistComponents\TextEntry::make('environment')
                    ->label('סביבה')
                    ->badge()
                    ->color(fn ($state) => $state === 'www' ? 'success' : 'warning'),

                InfolistComponents\IconEntry::make('is_test')
                    ->label('מצב בדיקות')
                    ->boolean(),

                InfolistComponents\TextEntry::make('created_at')
                    ->label('תאריך יצירה')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->columns(4)
            ->columnSpanFull()
            ->collapsible()
            ->collapsed(),

        // ═══════════════════════════════════════════════════════
        // 6️⃣ Raw JSON Payloads (נתוני API גולמיים)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('נתוני API גולמיים')
            ->schema([
                InfolistComponents\ViewEntry::make('raw_request')
                    ->view('officeguy::filament.components.api-payload')
                    ->label('נתוני בקשה (Request)'),

                InfolistComponents\ViewEntry::make('raw_response')
                    ->view('officeguy::filament.components.api-payload')
                    ->label('נתוני תגובה (Response)'),
            ])
            ->columnSpanFull()
            ->collapsible()
            ->collapsed(),

        // ═══════════════════════════════════════════════════════
        // 7️⃣ Request ↔ Response Diff (השוואה)
        // ═══════════════════════════════════════════════════════
        Schemas\Section::make('השוואת Request ל-Response')
            ->schema([
                InfolistComponents\ViewEntry::make('api_diff')
                    ->view('officeguy::filament.components.api-payload-diff')
                    ->label(null),
            ])
            ->columnSpanFull()
            ->collapsible()
            ->collapsed()
            ->description('השוואה מפורטת בין נתוני ה-Request לנתוני ה-Response'),
    ]);
}
```

---

## 🌳 ארכיטקטורת עץ ה-JSON

### מרכיבי Blade

#### 1️⃣ `api-payload.blade.php` (Container)

**מיקום**: `resources/views/filament/components/api-payload.blade.php`

**תפקיד**: Wrapper עבור ViewEntry בפילמנט

```blade
@php
    $state = $getState();

    // Normalize: JSON string → array
    if (is_string($state)) {
        $decoded = json_decode($state, true);
        $state = json_last_error() === JSON_ERROR_NONE ? $decoded : ['_raw' => $state];
    }
@endphp

<div class="rounded-lg border...">
    @if(empty($state))
        <div>אין נתוני API</div>
    @else
        <x-officeguy::api-payload
            :value="$state"
            :highlight="['Payment', 'Customer', 'Errors', 'Error', 'Status', 'Data', 'Amount']"
        />
    @endif
</div>
```

#### 2️⃣ `api-payload-node.blade.php` (Recursive Tree Node)

**מיקום**: `resources/views/components/api-payload-node.blade.php`

**Features**:
- ✅ **Recursive rendering** - עץ אינסופי
- ✅ **Smart icons** - זיהוי אוטומטי של טיפוס שדה
- ✅ **Auto-linking** - קישורים אוטומטיים ל-resources
- ✅ **Copy path** - העתקת נתיב JSON
- ✅ **Syntax highlighting** - צביעה לפי טיפוס
- ✅ **Expand/Collapse** - פתיחה/סגירה של צמתים

**Smart Icon Detection**:
```php
$icon = match(true) {
    str_contains($keyLower, 'payment') => 'heroicon-o-credit-card',
    str_contains($keyLower, 'customer') => 'heroicon-o-user',
    str_contains($keyLower, 'error') => 'heroicon-o-exclamation-triangle',
    str_contains($keyLower, 'status') => 'heroicon-o-signal',
    str_contains($keyLower, 'email') => 'heroicon-o-envelope',
    str_contains($keyLower, 'document') => 'heroicon-o-document-text',
    $isArray => 'heroicon-o-queue-list',
    $isObject => 'heroicon-o-cube',
    default => null,
};
```

**Auto-Linking Logic**:
```php
if ($enableLinks && $isScalar && is_numeric($node)) {
    $linkUrl = match(true) {
        str_contains($keyLower, 'transaction') && str_contains($keyLower, 'id') =>
            route('filament.admin.sumit-gateway.resources.transactions.view', ['record' => $node]),

        str_contains($keyLower, 'document') && str_contains($keyLower, 'id') =>
            route('filament.admin.sumit-gateway.resources.documents.view', ['record' => $node]),

        str_contains($keyLower, 'customer') && str_contains($keyLower, 'id') =>
            route('filament.admin.resources.clients.index') . '?tableFilters[sumit_customer_id][value]=' . $node,

        default => null,
    };
}
```

#### 3️⃣ Alpine.js State Management

**Global Store** (`apiPayloadTree`):
```javascript
Alpine.store('apiPayloadTree', {
    openMap: {},              // { "path.to.node": true/false }

    isOpen(path) {
        return !!this.openMap[path];
    },

    toggle(path) {
        this.openMap[path] = !this.openMap[path];
    },

    setOpen(path, state) {
        this.openMap[path] = state;
    },

    reset() {
        this.openMap = {};
    }
});
```

**Features**:
- 🔍 **Search** - חיפוש בתוך JSON (min 2 chars)
- 🔄 **Expand/Collapse All** - פתח/סגור הכל
- 📋 **Copy Path** - העתק נתיב JSON ללוח
- 🚨 **Performance Guards** - הגנות על ביצועים
  - `maxRenderNodes: 2500` - מקסימום צמתים
  - `maxStringifyBytes: 350000` - מקסימום בתים
  - `maxDepth: 18` - עומק מקסימלי

---

## 🧭 איפה להציג מה - מדריך החלטות

### שאלות מנחות

**שאלה 1**: האם זה שדה עסקי שהמשתמש צריך לראות **מיד**?
- ✅ כן → **TransactionInfolist.php** (Cards למעלה)
- ❌ לא → המשך לשאלה 2

**שאלה 2**: האם זה שדה טכני/debug שצריך רק בבדיקות?
- ✅ כן → **עץ JSON בלבד** (Collapsed Section)
- ❌ לא → המשך לשאלה 3

**שאלה 3**: האם זה שדה שמשתנה לפי סוג עסקה?
- ✅ כן → **Conditional Sections** ב-Infolist
- ❌ לא → שדה רגיל ב-Infolist

### מטריצת החלטות

| סוג שדה | Infolist Cards | JSON Tree | Table | Form | Notes |
|---------|----------------|-----------|-------|------|-------|
| **Identifiers** |
| `payment_id` | ✅ Copyable | ✅ Auto-link | ✅ | ❌ | מזהה ראשי |
| `auth_number` | ✅ Copyable | ✅ | ✅ | ❌ | מספר אישור |
| `document_id` | ✅ Link | ✅ Auto-link | ✅ Link | ❌ | קישור למסמך |
| `customer_id` | ✅ Link | ✅ Auto-link | ✅ Link | ❌ | קישור ללקוח |
| **Financial** |
| `amount` | ✅ **Bold** | ✅ | ✅ | ❌ | סכום ראשי |
| `currency` | ✅ Badge | ✅ | ✅ | ❌ | מטבע |
| `first_payment_amount` | ✅ Conditional | ✅ | ❌ | ❌ | רק אם >1 תשלומים |
| `non_first_payment_amount` | ✅ Conditional | ✅ | ❌ | ❌ | רק אם >1 תשלומים |
| `payments_count` | ✅ Badge | ✅ | ✅ | ❌ | מספר תשלומים |
| **Status** |
| `status` | ✅ **Badge** | ✅ | ✅ Badge | ❌ | סטטוס ראשי |
| `status_description` | ✅ | ✅ | ❌ | ❌ | תיאור מפורט |
| `error_message` | ✅ Collapsed | ✅ | ❌ | ❌ | רק אם נכשל |
| **Card Details** |
| `last_digits` | ✅ | ✅ | ✅ | ❌ | 4 ספרות |
| `card_type` | ✅ Badge | ✅ | ❌ | ❌ | אשראי/חיוב |
| `expiration_month` | ✅ Combined | ✅ | ❌ | ❌ | MM/YYYY |
| `expiration_year` | - | ✅ | ❌ | ❌ | חלק מ-Combined |
| `payment_token` | ❌ | ✅ | ✅ Copyable | ❌ | טכני - רק JSON |
| **Metadata** |
| `environment` | ✅ Badge | ✅ | ✅ | ❌ | www/dev |
| `is_test` | ✅ Icon | ✅ | ✅ Icon | ❌ | בדיקות |
| `source` | ✅ Collapsed | ✅ | ❌ | ❌ | checkout/webhook |
| `created_at` | ✅ | ✅ | ✅ | ❌ | תאריך |
| **Raw Data** |
| `raw_request` | ❌ | ✅ **Tree** | ❌ | ✅ ViewField | JSON מלא |
| `raw_response` | ❌ | ✅ **Tree** | ❌ | ✅ ViewField | JSON מלא |

### עקרונות כלליים

#### ✅ הצג ב-Infolist Cards (למעלה)
- שדות עסקיים קריטיים
- מזהים שצריך להעתיק
- סטטוסים וסכומים
- קישורים לישויות קשורות
- פרטי כרטיס (לא רגישים)

#### ⚠️ הצג Collapsed ב-Infolist
- שדות משניים
- מידע טכני שלא תמיד רלוונטי
- פירוט תשלומים (אם >1)
- הודעות שגיאה

#### ❌ **אל** תציג ב-Infolist (רק ב-JSON Tree)
- Raw payloads
- Nested objects מורכבים
- Debug data
- Credentials (אפילו מוסתרים!)
- Internal IDs שלא רלוונטיים למשתמש

---

## 🎨 דוגמאות קוד מלאות

### דוגמה 1: Card Details Section

```php
Schemas\Section::make('פרטי כרטיס')
    ->schema([
        InfolistComponents\TextEntry::make('payment_method')
            ->label('אמצעי תשלום')
            ->badge()
            ->formatStateUsing(fn ($state) => match ($state) {
                'card' => 'כרטיס אשראי',
                'bit' => 'Bit',
                default => $state,
            })
            ->color(fn ($state) => match ($state) {
                'card' => 'success',
                'bit' => 'primary',
                default => 'gray',
            })
            ->icon(fn ($state) => match ($state) {
                'card' => 'heroicon-o-credit-card',
                'bit' => 'heroicon-o-device-phone-mobile',
                default => 'heroicon-o-question-mark-circle',
            }),

        InfolistComponents\TextEntry::make('last_digits')
            ->label('מספר כרטיס')
            ->formatStateUsing(fn ($state) => $state ? 'XXXX-XXXX-XXXX-' . $state : 'לא זמין')
            ->weight('bold')
            ->copyable(),

        InfolistComponents\TextEntry::make('card_type')
            ->label('סוג')
            ->badge()
            ->formatStateUsing(fn ($state) => match ($state) {
                '1', 'credit' => 'אשראי',
                '2', 'debit' => 'חיוב מיידי',
                default => 'לא ידוע',
            })
            ->color(fn ($state) => match ($state) {
                '1', 'credit' => 'success',
                '2', 'debit' => 'warning',
                default => 'gray',
            }),

        InfolistComponents\TextEntry::make('expiration')
            ->label('תוקף')
            ->state(function ($record) {
                if (!$record->expiration_month || !$record->expiration_year) {
                    return null;
                }
                return str_pad($record->expiration_month, 2, '0', STR_PAD_LEFT)
                    . '/'
                    . $record->expiration_year;
            })
            ->placeholder('לא זמין'),
    ])
    ->columns(4)
    ->columnSpanFull()
    ->visible(fn ($record) => $record->payment_method === 'card')
    ->collapsible(),
```

### דוגמה 2: Conditional Refund Info

```php
Schemas\Section::make('מידע זיכוי')
    ->schema([
        InfolistComponents\TextEntry::make('transaction_type')
            ->label('סוג עסקה')
            ->badge()
            ->formatStateUsing(fn ($state) => match ($state) {
                'charge' => 'חיוב',
                'refund' => 'זיכוי',
                'void' => 'ביטול',
                default => $state,
            })
            ->color(fn ($state) => match ($state) {
                'charge' => 'success',
                'refund' => 'warning',
                'void' => 'danger',
                default => 'gray',
            }),

        InfolistComponents\TextEntry::make('parent_transaction_id')
            ->label('חיוב מקורי')
            ->formatStateUsing(fn ($state) => $state ? "#$state" : null)
            ->url(function ($record) {
                return $record->parent_transaction_id
                    ? route('filament.admin.sumit-gateway.resources.transactions.view', ['record' => $record->parent_transaction_id])
                    : null;
            })
            ->openUrlInNewTab()
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn ($record) => $record->isRefund()),

        InfolistComponents\TextEntry::make('refund_transaction_id')
            ->label('עסקת זיכוי')
            ->formatStateUsing(fn ($state) => $state ? "#$state" : 'לא בוצע זיכוי')
            ->url(function ($record) {
                return $record->refund_transaction_id
                    ? route('filament.admin.sumit-gateway.resources.transactions.view', ['record' => $record->refund_transaction_id])
                    : null;
            })
            ->openUrlInNewTab()
            ->icon('heroicon-o-arrow-path')
            ->visible(fn ($record) => $record->hasBeenRefunded()),
    ])
    ->columns(3)
    ->columnSpanFull()
    ->visible(fn ($record) => $record->isRefund() || $record->hasBeenRefunded())
    ->collapsible(),
```

---

## 🚀 סיכום ומסקנות

### עקרונות זהב

1. **שכבות ברורות**: JSON → Model → UI
2. **UI למעלה**: רק שדות עסקיים קריטיים
3. **JSON למטה**: כל מה שטכני או debug
4. **Conditional Sections**: הצג רק מה שרלוונטי
5. **Links Everywhere**: קישורים לכל ישות קשורה

### Checklist ליישום

- [ ] העתק את הקוד מ-"תבנית מומלצת" ל-`TransactionInfolist.php`
- [ ] התאם את השדות לפי הצרכים שלך
- [ ] וודא ש-`raw_request` ו-`raw_response` מוצגים רק ב-Collapsed Section
- [ ] בדוק שכל ה-Links עובדים
- [ ] וודא Dark Mode תומך
- [ ] בדוק Mobile Responsiveness
- [ ] הרץ `vendor/bin/duster fix --dirty`
- [ ] הרץ `php artisan test`

### קבצים לעדכון

| קובץ | פעולה | סטטוס |
|------|-------|-------|
| `TransactionInfolist.php` | ✏️ **הדבק קוד מהתבנית** | ⏳ Pending |
| `ViewTransaction.php` | ✅ כבר מוכן (Actions בלבד) | ✅ Complete |
| `TransactionForm.php` | ✅ כבר מוכן (Form עם JSON) | ✅ Complete |
| `TransactionsTable.php` | ✅ כבר מוכן (טבלה) | ✅ Complete |

---

**תאריך יצירה**: 2026-01-03
**גרסה**: 1.0.0
**יוצר**: Claude Code
**מטרה**: תיעוד מלא של מיפוי Payload לשדות

---

## 📚 קריאה נוספת

- [Filament Infolists Documentation](https://filamentphp.com/docs/4.x/infolists)
- [SUMIT API Documentation](https://docs.sumit.co.il)
- [OfficeGuyTransaction Model](../src/Models/OfficeGuyTransaction.php)
- [Package CLAUDE.md](../CLAUDE.md)
