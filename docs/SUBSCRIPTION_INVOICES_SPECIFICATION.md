# איפיון: קישור חשבוניות למנויים (Subscription Invoices)

**תאריך**: 2025-11-30
**גירסה**: 1.0
**מטרה**: הצגת חשבוניות שהונפקו עבור כל מנוי ב-ClientSubscriptionResource

---

## 📋 סקירה כללית

כרגע, המערכת מסנכרנת מנויים מ-SUMIT API לטבלה `officeguy_subscriptions`, אך אין קישור ישיר בין מנוי לחשבוניות שהונפקו עבורו.

**הצורך**:
- הצגת רשימת חשבוניות לכל מנוי בעמוד הצפייה במנוי
- מעקב אחר תשלומים שבוצעו עבור המנוי
- קישור ישיר להורדת חשבונית מתוך עמוד המנוי

---

## 🔍 מחקר - SUMIT API

### Endpoints רלוונטיים:

#### 1. `/accounting/documents/list/` (קיים)
**מטרה**: שליפת רשימת חשבוניות מ-SUMIT

**Request Schema**:
```json
{
  "Credentials": {
    "CompanyID": 1082100759,
    "APIKey": "..."
  },
  "DocumentTypes": [1, 8],           // 1=Invoice, 8=Order (אופציונלי)
  "DateFrom": "2025-01-01T00:00:00",  // אופציונלי
  "DateTo": "2025-12-31T23:59:59",    // אופציונלי
  "IncludeDrafts": false,             // אופציונלי
  "Paging": {                         // אופציונלי
    "PageNumber": 1,
    "PageSize": 50
  }
}
```

**Response Schema** (`Accounting_Typed_ListDocumentsDocument`):
```json
{
  "Status": 0,
  "Data": {
    "Documents": [
      {
        "DocumentID": 123456,
        "DocumentNumber": 1001,
        "Type": "1",                    // 1=Invoice, 8=Order
        "IsDraft": false,
        "Date": "2025-11-23T00:00:00",
        "CustomerID": 1095061474,
        "CustomerName": "KALFA Netanel",
        "Description": "חיוב עבור מנוי...",
        "DocumentValue": 11.04,
        "CompanyValue": 11.04,
        "Currency": "ILS",
        "Language": "he",
        "DueDate": "2025-12-23T00:00:00",
        "IsClosed": true,
        "DocumentDownloadURL": "https://...",
        "DocumentPaymentURL": "https://...",
        "ExternalReference": "subscription_1126885960"  // ← שדה מפתח!
      }
    ],
    "HasNextPage": false
  }
}
```

**שדות מפתח**:
- `DocumentID` - מזהה ייחודי של המסמך ב-SUMIT
- `CustomerID` - מזהה לקוח ב-SUMIT (תואם ל-`sumit_customer_id` של המשתמש)
- `ExternalReference` - **שדה חופשי לקישור עם מנוי!** (נשתמש בו לאחסון `recurring_id`)
- `DocumentDownloadURL` - קישור להורדה ישירה של החשבונית PDF
- `DocumentPaymentURL` - קישור לתשלום (אם החשבונית פתוחה)

#### 2. `/billing/recurring/charge/` (קיים)
**מטרה**: חיוב מנוי והנפקת חשבונית

כשמבצעים חיוב למנוי, ה-API מחזיר גם את ה-`DocumentID` של החשבונית שנוצרה:

```json
{
  "RecurringPaymentID": 1126885960,
  "Items": [...],
  "SendDocumentByEmail": true,
  "DocumentDescription": "חיוב עבור מנוי domain - netanel.kalfa.com"
}
```

**Response**:
```json
{
  "Status": 0,
  "Data": {
    "Payment": {
      "ValidPayment": true,
      "TransactionID": "...",
      "RecurringID": 1126885960
    },
    "DocumentID": 987654  // ← מזהה החשבונית שנוצרה!
  }
}
```

---

## 🗄️ מבנה Database - מצב נוכחי

### טבלה: `officeguy_subscriptions`
```sql
CREATE TABLE officeguy_subscriptions (
  id BIGINT PRIMARY KEY,
  subscriber_type VARCHAR(255),
  subscriber_id BIGINT,
  name VARCHAR(255),
  amount DECIMAL(10,2),
  currency VARCHAR(10),
  interval_months INT,
  total_cycles INT,
  completed_cycles INT,
  recurring_id VARCHAR(255),      -- מזהה SUMIT Recurring Item
  status VARCHAR(50),
  payment_method_token VARCHAR(255),
  trial_ends_at TIMESTAMP,
  next_charge_at TIMESTAMP,
  last_charged_at TIMESTAMP,
  cancelled_at TIMESTAMP,
  expires_at TIMESTAMP,
  cancellation_reason VARCHAR(255),
  metadata LONGTEXT,              -- JSON
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP
);
```

### טבלה: `officeguy_documents`
```sql
CREATE TABLE officeguy_documents (
  id BIGINT PRIMARY KEY,
  document_id VARCHAR(255),       -- SUMIT DocumentID
  order_id VARCHAR(255),          -- Polymorphic: מזהה ההזמנה
  order_type VARCHAR(255),        -- Polymorphic: סוג ההזמנה (App\Models\Order)
  customer_id VARCHAR(255),       -- SUMIT CustomerID
  document_type VARCHAR(50),      -- 1=Invoice, 8=Order
  is_draft TINYINT(1),
  language VARCHAR(10),
  currency VARCHAR(10),
  amount DECIMAL(10,2),
  description TEXT,
  emailed TINYINT(1),
  raw_response LONGTEXT,          -- JSON - תגובה מלאה מ-SUMIT
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP
);
```

**בעיה**:
- אין שדה `subscription_id` בטבלת `officeguy_documents`
- הקשר Polymorphic (`order_id`, `order_type`) לא מתאים למנויים
- אין אינדקס על `customer_id` + `created_at` לשאילתות מהירות

---

## 🎯 פתרון מוצע

### שלב 1: הרחבת Database Schema

#### Migration: `add_subscription_support_to_documents_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('officeguy_documents', function (Blueprint $table) {
            // Add subscription_id foreign key
            $table->unsignedBigInteger('subscription_id')
                ->nullable()
                ->after('order_type');

            // Add external_reference from SUMIT (for linking)
            $table->string('external_reference')
                ->nullable()
                ->after('description')
                ->index();

            // Add document URLs from SUMIT
            $table->string('document_download_url', 500)
                ->nullable()
                ->after('external_reference');

            $table->string('document_payment_url', 500)
                ->nullable()
                ->after('document_download_url');

            // Add document number and date from SUMIT
            $table->bigInteger('document_number')
                ->nullable()
                ->after('document_id');

            $table->timestamp('document_date')
                ->nullable()
                ->after('document_number');

            $table->boolean('is_closed')
                ->default(false)
                ->after('is_draft');

            // Add indexes for performance
            $table->index(['subscription_id', 'created_at']);
            $table->index(['customer_id', 'document_date']);

            // Foreign key (optional - depends on your DB engine)
            // $table->foreign('subscription_id')
            //     ->references('id')->on('officeguy_subscriptions')
            //     ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('officeguy_documents', function (Blueprint $table) {
            $table->dropIndex(['subscription_id', 'created_at']);
            $table->dropIndex(['customer_id', 'document_date']);
            // $table->dropForeign(['subscription_id']);

            $table->dropColumn([
                'subscription_id',
                'external_reference',
                'document_download_url',
                'document_payment_url',
                'document_number',
                'document_date',
                'is_closed',
            ]);
        });
    }
};
```

---

### שלב 2: עדכון Eloquent Models

#### Model: `Subscription.php`

```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

class Subscription extends Model
{
    // ... existing code ...

    /**
     * Get all documents (invoices) for this subscription
     */
    public function documents()
    {
        return $this->hasMany(OfficeGuyDocument::class, 'subscription_id');
    }

    /**
     * Get only invoices (type 1)
     */
    public function invoices()
    {
        return $this->documents()->where('document_type', '1');
    }

    /**
     * Get the latest invoice
     */
    public function latestInvoice()
    {
        return $this->invoices()->latest('document_date')->first();
    }

    /**
     * Get total amount billed through documents
     */
    public function getTotalBilledAttribute(): float
    {
        return $this->documents()->sum('amount');
    }
}
```

#### Model: `OfficeGuyDocument.php`

```php
use OfficeGuy\LaravelSumitGateway\Models\Subscription;

class OfficeGuyDocument extends Model
{
    protected $fillable = [
        'document_id',
        'order_id',
        'order_type',
        'subscription_id',        // ← NEW
        'customer_id',
        'document_type',
        'is_draft',
        'is_closed',             // ← NEW
        'language',
        'currency',
        'amount',
        'description',
        'external_reference',    // ← NEW
        'document_download_url', // ← NEW
        'document_payment_url',  // ← NEW
        'document_number',       // ← NEW
        'document_date',         // ← NEW
        'emailed',
        'raw_response',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'is_closed' => 'boolean',
        'emailed' => 'boolean',
        'amount' => 'decimal:2',
        'document_date' => 'datetime',
        'raw_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the subscription this document belongs to
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Create document from SUMIT API List response
     */
    public static function createFromListResponse(
        array $doc,
        ?int $subscriptionId = null
    ): static {
        return static::create([
            'document_id' => $doc['DocumentID'],
            'document_number' => $doc['DocumentNumber'] ?? null,
            'document_date' => $doc['Date'] ?? now(),
            'subscription_id' => $subscriptionId,
            'customer_id' => $doc['CustomerID'] ?? null,
            'document_type' => $doc['Type'] ?? '1',
            'is_draft' => $doc['IsDraft'] ?? false,
            'is_closed' => $doc['IsClosed'] ?? false,
            'language' => $doc['Language'] ?? 'he',
            'currency' => $doc['Currency'] ?? 'ILS',
            'amount' => $doc['DocumentValue'] ?? 0,
            'description' => $doc['Description'] ?? null,
            'external_reference' => $doc['ExternalReference'] ?? null,
            'document_download_url' => $doc['DocumentDownloadURL'] ?? null,
            'document_payment_url' => $doc['DocumentPaymentURL'] ?? null,
            'raw_response' => $doc,
        ]);
    }
}
```

---

### שלב 3: Document Sync Service

#### Service: `DocumentService.php` (NEW METHOD)

```php
namespace OfficeGuy\LaravelSumitGateway\Services;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use Carbon\Carbon;

class DocumentService
{
    /**
     * Fetch documents from SUMIT API for a customer
     *
     * @param int $sumitCustomerId SUMIT customer ID
     * @param Carbon|null $dateFrom Optional start date
     * @param Carbon|null $dateTo Optional end date
     * @param bool $includeDrafts Include draft documents
     * @return array List of documents from SUMIT
     */
    public static function fetchFromSumit(
        int $sumitCustomerId,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        bool $includeDrafts = false
    ): array {
        $request = [
            'Credentials' => PaymentService::getCredentials(),
            'IncludeDrafts' => $includeDrafts,
        ];

        if ($dateFrom) {
            $request['DateFrom'] = $dateFrom->toIso8601String();
        }

        if ($dateTo) {
            $request['DateTo'] = $dateTo->toIso8601String();
        }

        $environment = config('officeguy.environment', 'www');
        $response = OfficeGuyApi::post(
            $request,
            '/accounting/documents/list/',
            $environment,
            false
        );

        if (!$response || ($response['Status'] ?? null) !== 0) {
            return [];
        }

        // Filter by customer ID (API doesn't support this filter directly)
        $documents = $response['Data']['Documents'] ?? [];

        return array_filter($documents, function ($doc) use ($sumitCustomerId) {
            return ($doc['CustomerID'] ?? null) === $sumitCustomerId;
        });
    }

    /**
     * Sync documents from SUMIT for a subscription
     *
     * @param Subscription $subscription
     * @param Carbon|null $dateFrom Optional start date (default: subscription created_at)
     * @return int Number of documents synced
     */
    public static function syncForSubscription(
        Subscription $subscription,
        ?Carbon $dateFrom = null
    ): int {
        // Get subscriber's SUMIT customer ID
        $subscriber = $subscription->subscriber;
        $sumitCustomerId = $subscriber->sumit_customer_id ?? null;

        if (!$sumitCustomerId) {
            return 0;
        }

        // Default to subscription creation date
        if (!$dateFrom) {
            $dateFrom = $subscription->created_at;
        }

        $sumitDocs = self::fetchFromSumit(
            (int) $sumitCustomerId,
            $dateFrom,
            now()
        );

        $syncedCount = 0;

        foreach ($sumitDocs as $doc) {
            // Try to match by external_reference (if we set it during charge)
            $externalRef = $doc['ExternalReference'] ?? null;
            $isMatch = false;

            if ($externalRef && str_contains($externalRef, $subscription->recurring_id)) {
                $isMatch = true;
            }

            // Fallback: match by description containing subscription name
            if (!$isMatch) {
                $description = $doc['Description'] ?? '';
                if (str_contains($description, $subscription->name)) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                OfficeGuyDocument::updateOrCreate(
                    [
                        'document_id' => $doc['DocumentID'],
                    ],
                    [
                        'document_number' => $doc['DocumentNumber'] ?? null,
                        'document_date' => $doc['Date'] ?? now(),
                        'subscription_id' => $subscription->id,
                        'customer_id' => $doc['CustomerID'] ?? null,
                        'document_type' => $doc['Type'] ?? '1',
                        'is_draft' => $doc['IsDraft'] ?? false,
                        'is_closed' => $doc['IsClosed'] ?? false,
                        'language' => $doc['Language'] ?? 'he',
                        'currency' => $doc['Currency'] ?? 'ILS',
                        'amount' => $doc['DocumentValue'] ?? 0,
                        'description' => $doc['Description'] ?? null,
                        'external_reference' => $doc['ExternalReference'] ?? null,
                        'document_download_url' => $doc['DocumentDownloadURL'] ?? null,
                        'document_payment_url' => $doc['DocumentPaymentURL'] ?? null,
                        'raw_response' => $doc,
                    ]
                );

                $syncedCount++;
            }
        }

        return $syncedCount;
    }
}
```

---

### שלב 4: Filament Integration

#### Resource: `ClientSubscriptionResource.php` - View Page

```php
namespace OfficeGuy\LaravelSumitGateway\Filament\Client\Resources\ClientSubscriptionResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components as Info;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;

class ViewClientSubscription extends ViewRecord
{
    protected static string $resource = ClientSubscriptionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Auto-sync documents before displaying
        try {
            DocumentService::syncForSubscription($this->record);
        } catch (\Exception $e) {
            \Log::error('Failed to sync documents for subscription', [
                'subscription_id' => $this->record->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $data;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // ... existing subscription details ...

                Info\Section::make('חשבוניות')
                    ->schema([
                        Info\RepeatableEntry::make('documents')
                            ->label('')
                            ->schema([
                                Info\TextEntry::make('document_number')
                                    ->label('מספר חשבונית'),

                                Info\TextEntry::make('document_date')
                                    ->label('תאריך')
                                    ->dateTime('d/m/Y'),

                                Info\TextEntry::make('amount')
                                    ->label('סכום')
                                    ->money(fn ($record) => $record->currency ?? 'ILS'),

                                Info\TextEntry::make('is_closed')
                                    ->label('סטטוס')
                                    ->badge()
                                    ->color(fn ($state) => $state ? 'success' : 'warning')
                                    ->formatStateUsing(fn ($state) => $state ? 'סגור' : 'פתוח'),

                                Info\Actions::make([
                                    Info\Actions\Action::make('download')
                                        ->label('הורדה')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->url(fn ($record) => $record->document_download_url)
                                        ->openUrlInNewTab()
                                        ->visible(fn ($record) => !empty($record->document_download_url)),

                                    Info\Actions\Action::make('pay')
                                        ->label('תשלום')
                                        ->icon('heroicon-o-credit-card')
                                        ->url(fn ($record) => $record->document_payment_url)
                                        ->openUrlInNewTab()
                                        ->color('warning')
                                        ->visible(fn ($record) => !$record->is_closed && !empty($record->document_payment_url)),
                                ]),
                            ])
                            ->columns(5),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record->documents()->count() > 0),
            ]);
    }
}
```

---

## 🔄 תהליך עבודה (Workflow)

### תרחיש 1: חיוב מנוי קיים

```
1. Cron Job מריץ: SubscriptionService::processDueSubscriptions()
   ↓
2. עבור כל מנוי שצריך חיוב:
   SubscriptionService::processRecurringCharge($subscription)
   ↓
3. שליחת בקשה ל-SUMIT:
   POST /billing/recurring/charge/
   {
     "RecurringPaymentID": 1126885960,
     "DocumentDescription": "חיוב עבור מנוי domain - netanel.kalfa.com",
     "Items": [...],
     "ExternalReference": "subscription_1126885960"  ← קישור!
   }
   ↓
4. SUMIT מחזיר:
   {
     "Status": 0,
     "Data": {
       "Payment": { "ValidPayment": true, ... },
       "DocumentID": 987654
     }
   }
   ↓
5. יצירת רשומת Document:
   OfficeGuyDocument::create([
     'document_id' => 987654,
     'subscription_id' => $subscription->id,
     'external_reference' => 'subscription_1126885960',
     ...
   ])
```

### תרחיש 2: צפייה במנוי בממשק הלקוח

```
1. משתמש נכנס ל: /client/subscriptions/{id}
   ↓
2. ViewClientSubscription::mutateFormDataBeforeFill()
   מריץ: DocumentService::syncForSubscription($subscription)
   ↓
3. שאילתה ל-SUMIT:
   POST /accounting/documents/list/
   {
     "Credentials": {...},
     "DateFrom": "2025-10-01T00:00:00"  (מתאריך יצירת המנוי)
   }
   ↓
4. סינון תוצאות:
   - לפי CustomerID = sumit_customer_id של המשתמש
   - לפי ExternalReference או Description המכילים recurring_id
   ↓
5. סנכרון לטבלה:
   OfficeGuyDocument::updateOrCreate(...)
   ↓
6. הצגה בממשק:
   Infolist עם RepeatableEntry של החשבוניות
```

---

## ⚡ אופטימיזציות

### 1. Cache לסנכרון
```php
public static function syncForSubscription(Subscription $subscription): int
{
    $cacheKey = "subscription_docs_synced_{$subscription->id}";

    // Sync only once per hour
    if (Cache::has($cacheKey)) {
        return 0;
    }

    $count = self::performSync($subscription);

    Cache::put($cacheKey, true, now()->addHour());

    return $count;
}
```

### 2. Eager Loading
```php
// In ClientSubscriptionResource
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['documents' => function ($query) {
            $query->latest('document_date')->limit(10);
        }]);
}
```

### 3. אינדקסים
```sql
-- Already in migration:
INDEX(subscription_id, created_at)
INDEX(customer_id, document_date)
INDEX(external_reference)
```

---

## ✅ יתרונות הפתרון

1. **קישור אוטומטי**: שימוש ב-`ExternalReference` בזמן חיוב
2. **סנכרון חכם**: התאמה לפי `recurring_id` או שם המנוי
3. **ביצועים**: Cache + Eager Loading + אינדקסים
4. **UX מצוין**: הורדה ישירה של PDF, קישור לתשלום
5. **היסטוריה מלאה**: כל החשבוניות בעמוד המנוי
6. **נתונים עשירים**: URLs, סטטוס, תאריכים מ-SUMIT

---

## 🚀 תכנית יישום

### Phase 1: Database (1 יום)
- [x] יצירת migration
- [ ] הרצת migration בסביבת dev
- [ ] בדיקת אינדקסים

### Phase 2: Models & Services (2 ימים)
- [ ] עדכון Subscription Model
- [ ] עדכון OfficeGuyDocument Model
- [ ] יצירת DocumentService methods
- [ ] Unit tests

### Phase 3: Filament UI (1 יום)
- [ ] עדכון ViewClientSubscription page
- [ ] הוספת Infolist section
- [ ] בדיקות ממשק

### Phase 4: Testing & Optimization (1 יום)
- [ ] בדיקות E2E
- [ ] אופטימיזציית שאילתות
- [ ] הוספת Cache
- [ ] תיעוד

---

## 📝 הערות חשובות

1. **ExternalReference**: צריך לוודא שאנחנו שולחים את זה בכל חיוב מנוי
2. **Matching Logic**: אם אין ExternalReference, התאמה לפי שם המנוי בתיאור
3. **Performance**: לשקול Lazy Loading עם pagination אם יש הרבה חשבוניות
4. **Permissions**: לוודא שרק המנוי רואה את החשבוניות שלו
5. **Error Handling**: Log errors בסנכרון אבל לא למנוע הצגת המנוי

---

**סטטוס**: ✅ מפורט ומוכן ליישום
**בעלים**: Development Team
**עדיפות**: High
