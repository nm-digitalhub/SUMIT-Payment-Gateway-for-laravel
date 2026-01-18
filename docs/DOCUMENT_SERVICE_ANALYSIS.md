# ניתוח מעמיק: DocumentService.php - Document Generation & Management

**תאריך:** 2025-01-13
**קובץ:** `src/Services/DocumentService.php`
**שורות:** 1,153
**תפקיד:** יצירת וניהול מסמכים (חשבוניות/קבלות/תרומות/זיכויים)

---

## 📋 סיכום מהיר

**DocumentService** הוא ה-Service המנהל את כל המסמכים החשבונאיים ב-SUMIT:

### סוגי מסמכים (Document Types):

```php
public const TYPE_INVOICE = '1';           // חשבונית
public const TYPE_RECEIPT = '2';           // קבלה
public const TYPE_CREDIT_NOTE = '3';       // תעודת זיכוי
public const TYPE_ORDER = '8';             // הזמנה
public const TYPE_DONATION_RECEIPT = '320'; // קבלה לתרומה
```

### מאפיינים עיקריים:
- ✅ **15 Methods** - Coverage מקיף של כל lifecycle המסמכים
- ✅ **Multi-Channel Support** - SUMIT, PayPal, BlueSnap, etc.
- ✅ **Subscription Integration** - Intelligent many-to-many mapping
- ✅ **Document Synchronization** - Fetch & sync from SUMIT API
- ✅ **Email Sending** - Direct email via SUMIT
- ✅ **Credit Notes** - Accounting credits + cancellations
- ✅ **PDF Generation** - Document PDF URLs
- ✅ **Draft Mode** - Create documents as drafts
- ✅ **Language & Currency Support** - Multi-currency, multi-language

---

## 🔧 מתודות (15 Methods)

### קבוצה 1: Document Creation (5 methods)

#### 1.1. `createOrderDocument()` - Create Invoice/Receipt for Order (שורות 40-116) ⭐⭐⭐

**תפקיד:** יצירת מסמך חשבונאי (חשבונית/קבלה/תרומה) עבור הזמנה

```php
public static function createOrderDocument(
    Payable $order,
    array $customer,
    ?string $originalDocumentId = null,
    bool $isDonation = false
): ?string
```

**Parameters:**
- `$order` - Payable entity (Order/Subscription/etc.)
- `$customer` - Customer data array מ-PaymentService::getOrderCustomer()
- `$originalDocumentId` - Optional: לקישור לזיכוי
- `$isDonation` - האם זה מסמך תרומה

**Return:**
- `null` - הצלחה ✅
- `string` - הודעת שגיאה ❌

#### תהליך העבודה:

**שלב 1: Determine Document Type**
```php
// Default: Order (8)
$documentType = $isDonation ? self::TYPE_DONATION_RECEIPT : self::TYPE_ORDER;

// Auto-detect donation from order items
if (!$isDonation) {
    try {
        $isDonation = DonationService::containsDonation($order) &&
                      !DonationService::containsNonDonation($order);
        if ($isDonation) {
            $documentType = self::TYPE_DONATION_RECEIPT;  // 320
        }
    } catch (\Throwable $e) {
        // DonationService not available, continue with default
    }
}
```

**אוטומציה חכמה:**
- בודק אם כל הפריטים הם תרומות
- אם כן → קבלה לתרומה (320)
- אם לא → הזמנה רגילה (8)

**שלב 2: Build Request Payload**
```php
$request = [
    'Credentials' => PaymentService::getCredentials(),
    'Items' => PaymentService::getDocumentOrderItems($order),  // Line items
    'VATIncluded' => 'true',
    'VATRate' => PaymentService::getOrderVatRate($order),
    'Details' => [
        'Customer' => $customer,  // From PaymentService
        'IsDraft' => config('officeguy.draft_document', false) ? 'true' : 'false',
        'Language' => PaymentService::getOrderLanguage(),  // he/en/fr
        'Currency' => $order->getPayableCurrency(),  // ILS/USD/EUR
        'Type' => $documentType,  // 1/2/3/8/320
        'Description' => __('Order number') . ': ' . $order->getPayableId() .
            (empty($order->getCustomerNote()) ? '' : "\r\n" . $order->getCustomerNote()),
    ],
];

if ($originalDocumentId) {
    $request['OriginalDocumentID'] = $originalDocumentId;  // For credit notes
}
```

**שלב 3: Call SUMIT API**
```php
$environment = config('officeguy.environment', 'www');
$response = OfficeGuyApi::post(
    $request,
    '/accounting/documents/create/',  // ← SUMIT endpoint
    $environment,
    false
);
```

**שלב 4: Handle Response**
```php
if ($response && $response['Status'] === 0) {
    // SUCCESS
    $documentId = $response['Data']['DocumentID'];

    // Create local DB record
    OfficeGuyDocument::createFromApiResponse(
        $order->getPayableId(),
        $response,
        $request,
        get_class($order)  // ← CRITICAL: Polymorphic linking!
    );

    // Log
    OfficeGuyApi::writeToLog(
        'SUMIT order document created. Document ID: ' . $documentId,
        'info'
    );

    // Dispatch Event
    event(new \OfficeGuy\LaravelSumitGateway\Events\DocumentCreated(
        $order->getPayableId(),
        $documentId,
        $response['Data']['CustomerID'] ?? '',
        $response
    ));

    return null;  // ← Success!
}

// FAILURE
$errorMessage = __('Order creation failed.') . ' - ' .
                ($response['UserErrorMessage'] ?? 'Unknown error');
OfficeGuyApi::writeToLog($errorMessage, 'error');

return $errorMessage;
```

#### דוגמת שימוש:

```php
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;

// After successful payment:
$order = Order::find($orderId);
$customer = PaymentService::getOrderCustomer($order);

$error = DocumentService::createOrderDocument($order, $customer);

if ($error) {
    // Failed to create document
    Log::error('Document creation failed: ' . $error);
} else {
    // Document created successfully
    Log::info('Document created for order #' . $order->id);
}
```

---

#### 1.2. `createDocumentOnPaymentComplete()` - 3rd-Party Payment Documents (שורות 128-229)

**תפקיד:** יצירת מסמך עבור תשלומים שלא עברו דרך SUMIT (PayPal, BlueSnap, etc.)

```php
public static function createDocumentOnPaymentComplete(
    Payable $order,
    string $paymentMethod,
    ?string $transactionId = null
): ?string
```

**Parameters:**
- `$order` - Payable entity
- `$paymentMethod` - Payment method ID (paypal/bluesnap/etc.)
- `$transactionId` - Transaction ID from payment gateway

**Return:**
- `null` - Success OR Skipped (based on config)
- `string` - Error message

#### Payment Method Detection:

```php
$paymentDescription = 'Laravel';

// PayPal
if (in_array($paymentMethod, ['paypal', 'eh_paypal_express', 'ppec_paypal', 'ppcp-gateway'])) {
    if (config('officeguy.paypal_receipts') === 'no') {
        return null;  // ← Skip! PayPal receipts disabled
    }

    $paymentDescription = 'PayPal';
    if ($transactionId) {
        $paymentDescription .= ' - ' . $transactionId;
    }
}

// BlueSnap
elseif ($paymentMethod === 'bluesnap') {
    if (!config('officeguy.bluesnap_receipts', false)) {
        return null;  // ← Skip! BlueSnap receipts disabled
    }

    $paymentDescription = 'BlueSnap';
}

// Other (configurable)
elseif (config('officeguy.other_receipts') === $paymentMethod) {
    $paymentDescription = $paymentMethod;
}

// Unknown payment method
else {
    return null;  // ← Skip! Not configured
}
```

**Configuration Settings:**
```php
// config/officeguy.php
'paypal_receipts' => env('OFFICEGUY_PAYPAL_RECEIPTS', 'no'),      // 'no' or 'yes'
'bluesnap_receipts' => env('OFFICEGUY_BLUESNAP_RECEIPTS', false),  // true or false
'other_receipts' => env('OFFICEGUY_OTHER_RECEIPTS', ''),           // Method name or ''
```

#### Request Payload (Different from regular documents!):

```php
$request = [
    'Credentials' => PaymentService::getCredentials(),
    'Items' => PaymentService::getDocumentOrderItems($order),
    'VATIncluded' => 'true',
    'VATRate' => PaymentService::getOrderVatRate($order),
    'Details' => [
        'IsDraft' => config('officeguy.draft_document', false) ? 'true' : 'false',
        'Customer' => PaymentService::getOrderCustomer($order),
        'Language' => PaymentService::getOrderLanguage(),
        'Currency' => $order->getPayableCurrency(),
        'Description' => __('Order number') . ': ' . $order->getPayableId(),
        'Type' => '1',  // ← Always INVOICE (not Order!)
    ],
    'Payments' => [  // ← Additional payments section!
        [
            'Details_Other' => [
                'Type' => 'Laravel',
                'Description' => $paymentDescription,  // "PayPal - TXN123"
                'DueDate' => now()->toIso8601String(),
            ],
        ],
    ],
];

// Auto-email if enabled
if (config('officeguy.email_document', true)) {
    $request['Details']['SendByEmail'] = [
        'Original' => 'true',
    ];
}
```

**⚠️ Key Differences:**
- Document Type = '1' (Invoice) - לא Order!
- Includes `Payments` array
- Auto-email by default

---

#### 1.3. `createDonationReceipt()` - Donation Receipt Wrapper (שורות 238-245)

**תפקיד:** Wrapper פשוט ליצירת קבלה לתרומה

```php
public static function createDonationReceipt(
    Payable $order,
    ?array $customer = null
): ?string {
    $customer = $customer ?? PaymentService::getOrderCustomer($order);

    return self::createOrderDocument($order, $customer, null, true);
                                                            // ↑ isDonation=true
}
```

**Convenience Method:**
- קורא ל-`createOrderDocument()` עם `$isDonation = true`
- פחות verbose בקוד

**דוגמה:**
```php
// Instead of:
$error = DocumentService::createOrderDocument($donation, $customer, null, true);

// Use:
$error = DocumentService::createDonationReceipt($donation);
```

---

#### 1.4. `createCreditNote()` - Create Accounting Credit (שורות 855-953) ⭐

**תפקיד:** יצירת תעודת זיכוי חשבונאית (לא החזר כספי!)

```php
public static function createCreditNote(
    \OfficeGuy\LaravelSumitGateway\Contracts\HasSumitCustomer $customer,
    float $amount,
    string $description = 'זיכוי',
    ?int $originalDocumentId = null
): array
```

**Parameters:**
- `$customer` - מימוש HasSumitCustomer (User/Client)
- `$amount` - סכום הזיכוי
- `$description` - תיאור (ברירת מחדל: "זיכוי")
- `$originalDocumentId` - Optional: מסמך מקור לקישור

**Return:**
```php
[
    'success' => true,
    'document_id' => 12345,
    'document_number' => 'CN-2025-001',
    'amount' => 100.0,
]
// OR
[
    'success' => false,
    'error' => 'Error message',
]
```

#### תהליך העבודה:

**שלב 1: Validate Customer**
```php
$sumitCustomerId = $customer->getSumitCustomerId();

if (!$sumitCustomerId) {
    return [
        'success' => false,
        'error' => 'Customer not synced with SUMIT',
    ];
}
```

**שלב 2: Build Payload**
```php
$payload = [
    'Credentials' => PaymentService::getCredentials(),
    'Details' => [
        'Type' => 3,  // ← CreditNote (תעודת זיכוי)
        'Customer' => [
            'ID' => (int) $sumitCustomerId,  // ← ID ONLY!
        ],
        'Description' => $description,
        'Currency' => 0,  // ← ILS = 0 (NOT 1!)
        'Language' => 0,  // ← Hebrew
        'SendByEmail' => [
            'EmailAddress' => $customer->getSumitCustomerEmail(),
            'Original' => true,
            'SendAsPaymentRequest' => false,
        ],
    ],
    'Items' => [
        [
            'Item' => [
                'Name' => $description,  // "זיכוי"
            ],
            'Quantity' => 1,
            'UnitPrice' => $amount,
            'TotalPrice' => $amount,
        ],
    ],
    'VATIncluded' => false,  // ← No VAT on credit notes
];

// Link to original document
if ($originalDocumentId) {
    $payload['Details']['OriginalDocumentID'] = (int) $originalDocumentId;
}
```

**⚠️ Critical Notes:**
- **Currency: 0 = ILS** (not 1!)
- **Language: 0 = Hebrew** (not 1!)
- **Customer: ID only** (not full object)
- **VATIncluded: false** for credit notes

**שלב 3: Call API**
```php
$environment = config('officeguy.environment', 'www');
$response = OfficeGuyApi::post(
    $payload,
    '/accounting/documents/create/',
    $environment,
    false
);
```

**שלב 4: Handle Response**
```php
$status = $response['Status'] ?? null;

if ($status === 0 || $status === '0') {
    return [
        'success' => true,
        'document_id' => $response['Data']['DocumentID'] ?? null,
        'document_number' => $response['Data']['DocumentNumber'] ?? null,
        'amount' => $amount,
    ];
}

return [
    'success' => false,
    'error' => $response['UserErrorMessage'] ?? 'Unknown error',
];
```

#### דוגמת שימוש:

```php
$user = User::find(123);

// Create ₪50 credit note
$result = DocumentService::createCreditNote(
    $user,
    50.0,
    'זיכוי בגין ביטול הזמנה',
    $originalDocumentId  // Optional
);

if ($result['success']) {
    Log::info("Credit note created: {$result['document_number']}");

    // Email sent automatically by SUMIT ✅
} else {
    Log::error("Credit note failed: {$result['error']}");
}
```

---

### קבוצה 2: Document Retrieval & Sync (6 methods)

#### 2.1. `fetchFromSumit()` - Fetch Documents from SUMIT API (שורות 358-429) ⭐⭐

**תפקיד:** שליפת מסמכים מ-SUMIT API עם **pagination** אוטומטית

```php
public static function fetchFromSumit(
    int $sumitCustomerId,
    ?\Carbon\Carbon $dateFrom = null,
    ?\Carbon\Carbon $dateTo = null,
    bool $includeDrafts = false
): array
```

**Parameters:**
- `$sumitCustomerId` - SUMIT customer ID
- `$dateFrom` - תאריך התחלה (optional)
- `$dateTo` - תאריך סיום (optional)
- `$includeDrafts` - האם לכלול טיוטות

**Return:**
- `array` - רשימת מסמכים מ-SUMIT

#### Pagination Logic:

```php
$allDocuments = [];
$startIndex = 0;
$pageSize = 1000;  // 1000 documents per page
$hasMoreResults = true;
$environment = config('officeguy.environment', 'www');

while ($hasMoreResults) {
    $request = [
        'Credentials' => PaymentService::getCredentials(),
        'DocumentTypes' => null,  // All types
        'DocumentNumberFrom' => null,
        'DocumentNumberTo' => null,
        'DateFrom' => $dateFrom?->format('Y-m-d'),
        'DateTo' => $dateTo?->format('Y-m-d'),
        'IncludeDrafts' => $includeDrafts,
        'Paging' => [
            'StartIndex' => $startIndex,
            'PageSize' => $pageSize,
        ],
    ];

    $response = OfficeGuyApi::post(
        $request,
        '/accounting/documents/list/',  // ← SUMIT list endpoint
        $environment,
        false
    );

    if (!$response || ($response['Status'] ?? null) !== 0) {
        break;  // Error - stop pagination
    }

    $documents = $response['Data']['Documents'] ?? [];

    if (empty($documents)) {
        break;  // No more documents
    }

    $allDocuments = array_merge($allDocuments, $documents);

    // Check if there are more results
    $hasMoreResults = ($response['Data']['HasNextPage'] ?? false) === true;
    $startIndex += count($documents);

    // Safety limit (prevent infinite loop)
    if ($startIndex > 100000) {  // ← Max 100K documents
        break;
    }
}

// Filter by customer ID (server-side filter not reliable)
$filtered = array_filter($allDocuments, function ($doc) use ($sumitCustomerId) {
    return ($doc['CustomerID'] ?? null) === $sumitCustomerId;
});

return array_values($filtered);  // Reindex array
```

**⚡ Performance:**
- **PageSize: 1000** - Large pages for efficiency
- **Safety Limit: 100K** - Prevent infinite loops
- **Client-side filtering** - SUMIT API filter not reliable

---

#### 2.2. `getDocumentDetails()` - Get Full Document with Items (שורות 437-457)

**תפקיד:** שליפת פרטי מסמך מלאים כולל **Items**

```php
public static function getDocumentDetails(string|int $documentId): ?array
```

**תהליך:**
```php
$request = [
    'Credentials' => PaymentService::getCredentials(),
    'DocumentID' => $documentId,
];

$environment = config('officeguy.environment', 'www');
$response = OfficeGuyApi::post(
    $request,
    '/accounting/documents/getdetails/',  // ← Different endpoint!
    $environment,
    false
);

if (!$response || ($response['Status'] ?? null) !== 0) {
    return null;
}

return $response['Data'] ?? null;
```

**דוגמת Response:**
```php
[
    'DocumentID' => 12345,
    'DocumentNumber' => 'INV-2025-001',
    'CustomerID' => 67890,
    'Type' => 1,
    'IsDraft' => false,
    'IsClosed' => true,
    'Items' => [  // ← Full items list!
        [
            'Item' => [
                'ID' => 111,
                'Name' => 'Product A',
            ],
            'Quantity' => 2,
            'UnitPrice' => 50.0,
            'TotalPrice' => 100.0,
        ],
        // ... more items
    ],
    'DocumentDownloadURL' => 'https://...',
    'DocumentPaymentURL' => null,  // null = paid
]
```

**שימוש:**
- קבלת Items מלאים למסמך
- בדיקת סטטוס תשלום (IsClosed, DocumentPaymentURL)
- קישור מסמכים ל-subscriptions

---

#### 2.3. `syncForClient()` - Sync Documents for Client (שורות 279-347)

**תפקיד:** סנכרון מסמכים עבור Client וקישור אוטומטי ל-Orders

```php
public static function syncForClient(
    Client $client,
    ?Carbon $dateFrom = null,
    ?Carbon $dateTo = null
): int  // ← Returns number of synced documents
```

#### תהליך:

**שלב 1: Validate Client**
```php
if (!$client->sumit_customer_id) {
    return 0;  // No SUMIT customer ID
}
```

**שלב 2: Fetch from SUMIT**
```php
$documents = self::fetchFromSumit(
    (int) $client->sumit_customer_id,
    $dateFrom,
    $dateTo
);
$synced = 0;
```

**שלב 3: Save + Link to Orders**
```php
foreach ($documents as $doc) {
    // Save document
    $document = OfficeGuyDocument::updateOrCreate(
        ['document_id' => $doc['DocumentID'] ?? null],
        [
            'document_number' => $doc['DocumentNumber'] ?? null,
            'document_date' => $doc['Date'] ?? now(),
            'customer_id' => $doc['CustomerID'] ?? null,
            'document_type' => $doc['Type'] ?? self::TYPE_INVOICE,
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

    // Link to order by ExternalReference or DocumentNumber
    $order = null;
    $ext = $doc['ExternalReference'] ?? null;

    if ($ext) {
        $order = Order::where('client_id', $client->id)
            ->where(function ($q) use ($ext) {
                $q->where('order_number', $ext)
                    ->orWhere('id', is_numeric($ext) ? (int) $ext : 0);
            })
            ->latest('id')
            ->first();
    }

    // Fallback: Match by DocumentNumber
    if (!$order && !empty($doc['DocumentNumber'])) {
        $order = Order::where('client_id', $client->id)
            ->where('order_number', $doc['DocumentNumber'])
            ->latest('id')
            ->first();
    }

    // Update polymorphic relationship
    if ($order) {
        $document->order_id = $order->id;
        $document->order_type = Order::class;
    }

    // Fetch items/details (best effort)
    if (empty($document->items) && !empty($doc['DocumentID'])) {
        $details = self::getDocumentDetails($doc['DocumentID']);
        if ($details && isset($details['Items'])) {
            $document->items = $details['Items'];
        }
    }

    $document->save();
    $synced++;
}

return $synced;
```

**Intelligent Linking:**
1. By `ExternalReference` (order number or ID)
2. By `DocumentNumber` (fallback)
3. Polymorphic: `order_id` + `order_type`

---

#### 2.4. `syncAllForCustomer()` - Full Customer Document Sync (שורות 555-656) ⭐⭐⭐

**תפקיד:** סנכרון **כל** המסמכים עבור לקוח כולל **many-to-many subscription mapping**

```php
public static function syncAllForCustomer(
    int $sumitCustomerId,
    ?\Carbon\Carbon $dateFrom = null,
    ?\Carbon\Carbon $dateTo = null
): int
```

#### Key Features:

**1. Default 5-Year History:**
```php
// Catch ALL historical documents
if (!$dateFrom) {
    $dateFrom = now()->subYears(5);
}

if (!$dateTo) {
    $dateTo = now();
}
```

**2. Currency Code Conversion:**
```php
$currencyCode = $doc['Currency'] ?? 0;
$currency = match ((int)$currencyCode) {
    0 => 'ILS',  // ← SUMIT uses 0 for ILS!
    1 => 'USD',
    2 => 'EUR',
    default => 'ILS',
};
```

**3. Language Code Conversion:**
```php
$languageCode = $doc['Language'] ?? 0;
$language = match ((int)$languageCode) {
    0 => 'he',  // ← SUMIT uses 0 for Hebrew!
    1 => 'en',
    default => 'he',
};
```

**4. Payment Status Detection:**
```php
'is_closed' => ($fullDetails['IsClosed'] ?? $doc['IsClosed'] ?? false) === true ||
               ($fullDetails['IsClosed'] ?? $doc['IsClosed'] ?? false) === 'true' ||
               // SUMIT Logic: DocumentPaymentURL = null → Paid ✅
               ($fullDetails ? empty($fullDetails['DocumentPaymentURL']) : empty($doc['DocumentPaymentURL'])),
```

**5. Intelligent Subscription Mapping (Many-to-Many):**
```php
// Identify ALL subscriptions in document
if ($fullDetails && $sumitCustomerId) {
    $subscriptionsInDoc = self::identifySubscriptionsInDocument($fullDetails, $sumitCustomerId);

    // Sync to pivot table (many-to-many)
    if (!empty($subscriptionsInDoc)) {
        foreach ($subscriptionsInDoc as $subData) {
            $document->subscriptions()->syncWithoutDetaching([
                $subData['subscription']->id => [
                    'amount' => $subData['amount'],          // ← Amount per subscription
                    'item_data' => json_encode($subData['items']),  // ← Items per subscription
                ],
            ]);
        }

        // Update legacy subscription_id (backward compatibility)
        if ($document->subscription_id === null) {
            $document->update(['subscription_id' => $subscriptionsInDoc[0]['subscription']->id]);
        }
    }
}
```

---

#### 2.5. `identifySubscriptionsInDocument()` - Intelligent Subscription Matching (שורות 469-542) ⭐

**תפקיד:** זיהוי **כל** ה-subscriptions במסמך לפי פריטים (many-to-many)

```php
protected static function identifySubscriptionsInDocument(
    array $fullDetails,
    int $sumitCustomerId
): array
```

**Return:**
```php
[
    [
        'subscription' => Subscription,  // Model instance
        'amount' => 10.0,
        'items' => [ /* item data */ ],
    ],
    // ... more subscriptions
]
```

#### Matching Logic:

**1. Get ALL Subscriptions (No Status Filter!):**
```php
// IMPORTANT: Include cancelled/paused subscriptions too!
// Documents can contain charges for later-cancelled subscriptions
$subscriptions = \OfficeGuy\LaravelSumitGateway\Models\Subscription::query()
    ->where(function ($q) use ($sumitCustomerId) {
        // For User subscribers
        $q->where('subscriber_type', 'App\\Models\\User')
          ->whereIn('subscriber_id', function ($subQ) use ($sumitCustomerId) {
              $subQ->select('id')
                   ->from('users')
                   ->where('sumit_customer_id', $sumitCustomerId);
          });

        // TODO: Add other subscriber types (e.g., 'App\Models\Client')
    })
    // NO status filter! ← All subscriptions
    ->get();
```

**2. Match Items to Subscriptions:**
```php
$matches = [];

foreach ($items as $itemData) {
    $itemName = $itemData['Item']['Name'] ?? '';
    $itemAmount = (float)($itemData['TotalPrice'] ?? 0);
    $itemId = $itemData['Item']['ID'] ?? null;

    if (empty($itemName)) {
        continue;
    }

    $matchedSubs = [];

    foreach ($subscriptions as $subscription) {
        // Match by name (case-insensitive)
        $nameMatch = strtolower(trim($itemName)) === strtolower(trim($subscription->name));

        if ($nameMatch) {
            $metadataItemId = $subscription->metadata['sumit_item_id'] ?? null;

            // Stricter matching: Check Item ID too (if available)
            if ($itemId && $metadataItemId) {
                if ((int)$itemId === (int)$metadataItemId) {
                    $matchedSubs[] = $subscription;
                }
            } else {
                // Name-only matching (fallback)
                $matchedSubs[] = $subscription;
            }
        }
    }

    // Add ALL matched subscriptions
    foreach ($matchedSubs as $subscription) {
        $matches[] = [
            'subscription' => $subscription,
            'amount' => $itemAmount,
            'items' => [$itemData],
        ];
    }
}

return $matches;
```

**תרחיש דוגמה:**
```
Document Items:
- "דומיין .com" (₪10) → Subscription #1
- "דומיין .co.il" (₪15) → Subscription #2
- "דומיין .com" (₪10) → Subscription #3
- "דומיין .com" (₪10) → Subscription #4
- "דומיין .com" (₪10) → Subscription #5

Result: 5 subscriptions mapped to 1 document! ✅
```

---

### קבוצה 3: Document Operations (4 methods)

#### 3.1. `getDocumentPDF()` - Get PDF URL (שורות 961-1000)

**תפקיד:** קבלת URL להורדת PDF של מסמך

```php
public static function getDocumentPDF(int $documentId): array
```

**דוגמה:**
```php
$result = DocumentService::getDocumentPDF(12345);

if ($result['success']) {
    $pdfUrl = $result['pdf_url'];
    // https://...
}
```

---

#### 3.2. `sendByEmail()` - Send Document by Email (שורות 1014-1087) ⭐

**תפקיד:** שליחת מסמך באימייל דרך SUMIT

```php
public static function sendByEmail(
    int|OfficeGuyDocument $document,
    ?string $email = null,
    ?string $personalMessage = null,
    bool $original = true
): array
```

**⚠️ CRITICAL:**
```php
// SUMIT API requires DocumentType + DocumentNumber
// NOT DocumentID! Using DocumentID fails with "Document not found"

$payload = [
    'Credentials' => PaymentService::getCredentials(),
    'DocumentType' => (int) $document->document_type,        // ← NOT DocumentID!
    'DocumentNumber' => (int) $document->document_number,    // ← Critical!
    'Original' => $original,
];

// Optional: Override email
if ($email) {
    $payload['EmailAddress'] = $email;
}

// Optional: Personal message
if ($personalMessage) {
    $payload['PersonalMessage'] = $personalMessage;
}
```

**דוגמה:**
```php
$document = OfficeGuyDocument::find(123);

$result = DocumentService::sendByEmail(
    $document,
    'customer@example.com',  // Override email
    'תודה על הרכישה!',        // Personal message
    true                     // Send original (not copy)
);

if ($result['success']) {
    // Email sent ✅
}
```

---

#### 3.3. `cancelDocument()` - Cancel Document (שורות 1098-1152)

**תפקיד:** ביטול מסמך (יוצר תעודת זיכוי אוטומטית)

```php
public static function cancelDocument(
    int $documentId,
    string $description = 'ביטול מסמך'
): array
```

**Return:**
```php
[
    'success' => true,
    'original_document_id' => 12345,
    'credit_document_id' => 67890,            // New credit note
    'credit_document_number' => 'CN-001',
    'credit_document_url' => 'https://...',
    'description' => 'ביטול מסמך',
    'cancelled_at' => '2025-01-13 10:00:00',
    'gateway_response' => [ /* full response */ ],
]
```

**דוגמה:**
```php
$result = DocumentService::cancelDocument(
    12345,
    'ביטול בגין החזרת מוצר'
);

if ($result['success']) {
    Log::info("Document cancelled, credit note: {$result['credit_document_number']}");
}
```

---

### קבוצה 4: Helper Methods (3 methods)

#### 4.1. `getDocumentTypeName()` - Human-Readable Type Name (שורות 253-263)

```php
public static function getDocumentTypeName(string $type): string
{
    return match ($type) {
        self::TYPE_INVOICE, '1' => __('Invoice'),           // חשבונית
        self::TYPE_RECEIPT, '2' => __('Receipt'),           // קבלה
        self::TYPE_CREDIT_NOTE, '3' => __('Credit Note'),   // תעודת זיכוי
        self::TYPE_ORDER, '8' => __('Order'),               // הזמנה
        self::TYPE_DONATION_RECEIPT, '320' => __('Donation Receipt'), // קבלה לתרומה
        default => __('Document'),
    };
}
```

**שימוש בFilament:**
```php
Tables\Columns\TextColumn::make('document_type')
    ->formatStateUsing(fn ($state) => DocumentService::getDocumentTypeName($state))
```

---

## 🔗 תלויות (Dependencies)

### Services:
```php
PaymentService::getCredentials()         // CompanyID + APIKey
PaymentService::getOrderCustomer($order) // Customer data array
PaymentService::getDocumentOrderItems($order)  // Line items
PaymentService::getOrderVatRate($order)  // VAT rate
PaymentService::getOrderLanguage()       // he/en/fr
PaymentService::getPaymentMethodsForCustomer() // For token sync
```

### Models:
```php
OfficeGuyDocument::createFromApiResponse() // Parse & save document
OfficeGuyDocument::updateOrCreate()        // Sync document
$document->subscriptions()                 // Many-to-many relationship
```

### Events:
```php
new \OfficeGuy\LaravelSumitGateway\Events\DocumentCreated(
    $orderId,
    $documentId,
    $customerId,
    $response
);
```

### External Services:
```php
DonationService::containsDonation($order)     // Check if order has donations
DonationService::containsNonDonation($order)  // Check if order has non-donations
```

---

## 🎯 Best Practices

### ✅ DO:

1. **Always link documents to orders:**
```php
OfficeGuyDocument::createFromApiResponse(
    $orderId,
    $response,
    $request,
    get_class($order)  // ← CRITICAL for polymorphic linking!
);
```

2. **Use document type constants:**
```php
// ✅ GOOD
$type = DocumentService::TYPE_INVOICE;

// ❌ BAD
$type = '1';
```

3. **Check for configuration before creating documents:**
```php
if (config('officeguy.paypal_receipts') === 'no') {
    return null;  // Skip
}
```

4. **Handle errors gracefully:**
```php
$error = DocumentService::createOrderDocument($order, $customer);

if ($error) {
    Log::error('Document creation failed: ' . $error);
    // Don't fail the entire order!
}
```

---

## 📝 Summary

**DocumentService** הוא Service מקיף לניהול מסמכים חשבונאיים:

**✅ Strengths:**
- Comprehensive document lifecycle management
- Intelligent subscription mapping (many-to-many)
- Multi-channel support (SUMIT, PayPal, BlueSnap)
- Automatic pagination for large datasets
- Currency & language conversion
- Email sending via SUMIT
- Credit note & cancellation support

**⚠️ Complexity:**
- 1,153 lines (largest service)
- 15 public methods
- Complex subscription matching logic
- Many configuration options

**🎯 Role:**
- Central document management
- Bridge between payments and accounting
- Subscription invoice tracking
- Tax document generation

---

**Generated:** 2025-01-13
