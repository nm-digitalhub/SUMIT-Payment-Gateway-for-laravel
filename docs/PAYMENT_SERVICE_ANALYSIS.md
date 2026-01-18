# ניתוח מעמיק: PaymentService.php

**מיקום:** `src/Services/PaymentService.php`  
**גודל:** 1,178 שורות  
**סוג:** Static Service Class (כל המתודות static)

---

## 🎯 תפקיד ראשי

**PaymentService הוא הלב של המערכת** - מטפל בכל תהליך התשלום מקצה לקצה:
- בניית בקשות תשלום ל-SUMIT API
- עיבוד תשלומים (כרטיס, redirect, tokens)
- ניהול שיטות תשלום שמורות
- החזרים (refunds)
- חישובי תשלומים (תשלומים, VAT, שפה)

---

## 📋 קטגוריות מתודות

### 1. Configuration & Helpers (4 מתודות)

#### `getCredentials()` ⭐
```php
public static function getCredentials(): array
{
    return [
        'CompanyID' => config('officeguy.company_id'),
        'APIKey' => config('officeguy.private_key'),
    ];
}
```
**תפקיד:** מחזיר credentials ל-SUMIT API  
**שימוש:** **בכל** קריאת API!

#### `getMaximumPayments($orderValue)`
```php
$maximumPayments = (int)config('officeguy.max_payments', 1);
$minAmountPerPayment = (float)config('officeguy.min_amount_per_payment', 0);
if ($minAmountPerPayment > 0) {
    $maximumPayments = min($maximumPayments, (int)floor($orderValue / $minAmountPerPayment));
}
```
**תפקיד:** חישוב מקסימום תשלומים מותר לפי סכום ההזמנה  
**לוגיקה:**
- מגבלה מוגדרת: `max_payments`
- סכום מינימלי לתשלום: `min_amount_per_payment`
- סכום מינימלי להפעלת תשלומים: `min_amount_for_payments`

**דוגמה:**
- Order: 500 ₪
- max_payments: 12
- min_amount_per_payment: 50 ₪
- → מקסימום: min(12, 500/50) = **10 תשלומים**

#### `getOrderVatRate(Payable $order)`
**תפקיד:** קבלת אחוז מע"מ מההזמנה  
**החזרה:** string (למשל: "17")

#### `getOrderLanguage()`
**תפקיד:** המרת locale ל-SUMIT language  
**מיפוי:**
- `he` → "Hebrew"
- `en` → "English"  
- `ar` → "Arabic"
- `es` → "Spanish"

---

### 2. Payment Methods Management (4 מתודות)

#### `setPaymentMethodForCustomer($sumitCustomerId, $token, $method = [])`
**Endpoint:** `/billing/paymentmethods/setforcustomer/`

**תפקיד:** הגדרת שיטת תשלום ברירת מחדל ללקוח ב-SUMIT

**Logic Flow:**
```php
if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $token)) {
    // Permanent token (UUID format) → Use PaymentMethod
    $payload['PaymentMethod'] = [
        'Type' => 1,  // CreditCard
        'CreditCard_Token' => $token,
        'CreditCard_ExpirationMonth' => ...,
        'CreditCard_ExpirationYear' => ...,
    ];
} else {
    // Single-use token → Use SingleUseToken field
    $payload['SingleUseToken'] = $token;
}
```

**Critical:** מזהה אוטומטי בין permanent token ל-single-use token!

#### `getPaymentMethodsForCustomer($sumitCustomerId, $includeInactive = false)`
**Endpoint:** `/billing/paymentmethods/listforcustomer/`

**תפקיד:** קבלת רשימת שיטות תשלום של לקוח

**Return:**
```php
[
    'success' => true,
    'payment_methods' => [
        [
            'ID' => 123,
            'Type' => 1,  // CreditCard
            'CreditCard_Token' => 'uuid...',
            'CreditCard_LastDigits' => '1234',
            'CreditCard_ExpirationMonth' => 12,
            'CreditCard_ExpirationYear' => 2025,
            'IsActive' => true,
        ]
    ]
]
```

#### `removePaymentMethodForCustomer($sumitCustomerId)`
**Endpoint:** `/billing/paymentmethods/deleteforcustomer/`

**תפקיד:** מחיקת שיטת תשלום ברירת מחדל

#### `testPayment($token, $sumitCustomerId)`
**Endpoint:** `/billing/payments/charge/`

**תפקיד:** בדיקת token בתשלום של 1 ₪ (מבוטל אוטומטית)

---

### 3. Items & Customer Data (3 מתודות)

#### `getPaymentOrderItems(Payable $order)` ⭐
**תפקיד:** המרת items מההזמנה לפורמט SUMIT

**Return Format:**
```php
[
    [
        'Quantity' => 2.0,
        'UnitPrice' => 100.00,
        'Currency' => 'ILS',
        'Item' => [
            'Name' => 'Product Name',
            'Description' => 'Product Description',
            'Price' => 100.00,
            'Currency' => 'ILS',
            'SKU' => 'PROD-123',
        ]
    ]
]
```

**Logic:**
- אם `$order->getItems()` קיים → map על items
- אחרת → יצירת item יחיד מכל ההזמנה

#### `getDocumentOrderItems(Payable $order)`
**דומה ל-`getPaymentOrderItems`** אבל למסמכים (יותר detailed)

#### `getOrderCustomer(Payable $order, ?string $citizenId = null)` ⭐⭐⭐
**תפקיד:** בניית Customer payload ל-SUMIT

**Critical Logic:**
```php
// Priority 1: אם לקוח כבר קיים ב-SUMIT (יש SUMIT customer ID)
if ($sumitCustomerId = $order->getSumitCustomerId()) {
    return [
        'ID' => (int) $sumitCustomerId,  // ← ID בלבד! אין שדות נוספים!
    ];
}

// Priority 2: לקוח חדש - שלח פרטים מלאים
return [
    'Name' => $order->getCustomerName(),
    'EmailAddress' => $order->getCustomerEmail(),
    'Phone' => $order->getCustomerPhone(),
    'City' => $order->getCustomerCity(),
    'Address' => $order->getCustomerAddress(),
    'ZipCode' => $order->getCustomerZip(),
    'CompanyNumber' => $order->getCustomerCompanyNumber(),
    'ID' => $citizenId,  // ת.ז / דרכון
];
```

**🚨 CRITICAL SAFETY GUARD:**  
כאשר לקוח קיים (יש ID), **חובה** לשלוח רק `ID` ללא שדות נוספים!  
אחרת SUMIT עשוי ליצור לקוח כפול!

---

### 4. Core Payment Processing (4 מתודות) ⭐⭐⭐

#### `buildChargeRequest(...)` ⭐⭐⭐
**הפונקציה הכי חשובה!**

**Parameters:**
```php
buildChargeRequest(
    Payable $order,                    // ההזמנה לתשלום
    int $paymentsCount = 1,            // מספר תשלומים
    bool $recurring = false,           // חיוב חוזר?
    bool $redirectMode = false,        // redirect או direct?
    ?OfficeGuyToken $token = null,     // token שמור
    array $extra = [],                 // פרמטרים נוספים
    ?array $paymentMethodPayload = null, // כרטיס ישיר (PCI mode = yes)
    ?string $singleUseToken = null,    // token חד-פעמי מ-PaymentsJS
    ?string $customerCitizenId = null  // ת.ז לקוח
): array
```

**Return Structure:**
```php
[
    'Credentials' => [...],
    'Customer' => [...],  // מ-getOrderCustomer()
    'Items' => [...],     // מ-getPaymentOrderItems()
    'VATIncluded' => 'true',
    'VATRate' => '17',
    'Payments_Count' => 3,
    'MaximumPayments' => 12,
    'DocumentLanguage' => 'Hebrew',
    'AuthoriseOnly' => 'false',
    'DraftDocument' => 'false',
    'SendDocumentByEmail' => 'true',
    'DocumentDescription' => 'Order number: 12345...',
    'MerchantNumber' => '...',
    
    // Payment Method (one of):
    'SingleUseToken' => 'token...',        // Option 1: PaymentsJS
    'PaymentMethod' => [                   // Option 2: Saved token
        'CreditCard_Token' => 'uuid...',
        'CreditCard_CitizenID' => '123456789',
        'CreditCard_ExpirationMonth' => 12,
        'CreditCard_ExpirationYear' => 2025,
        'Type' => 1,
    ],
    // Option 3: Direct card (PCI = yes) → paymentMethodPayload
]
```

**Critical Decision Flow:**
```php
if ($singleUseToken !== null) {
    // PaymentsJS → Single-use token
    $request['SingleUseToken'] = $singleUseToken;
} 
elseif ($token !== null) {
    // Saved token → PaymentMethod with token details
    $request['PaymentMethod'] = [
        'CreditCard_Token' => $token->token,
        'CreditCard_CitizenID' => $token->citizen_id,  // ← מה-token, לא מהלקוח!
        ...
    ];
} 
elseif (!$redirectMode && !empty($paymentMethodPayload)) {
    // Direct card details (PCI = yes)
    $request['PaymentMethod'] = $paymentMethodPayload;
}
```

**🚨 SAFETY GUARD:**
```php
// אם Customer.ID קיים, מחק את כל השדות האחרים!
if (isset($request['Customer']['ID'])) {
    $request['Customer'] = [
        'ID' => $request['Customer']['ID'],
    ];
}
```

#### `processCharge(...)` ⭐⭐⭐
**הפונקציה שמבצעת את התשלום בפועל!**

**Flow:**
```
1. Build request → buildChargeRequest()
2. Choose endpoint:
   - Recurring: /billing/recurring/charge/
   - Redirect: /billing/payments/beginredirect/
   - Direct: /billing/payments/charge/
3. Log customer payload (debug)
4. Call SUMIT API → OfficeGuyApi::post()
5. Handle response:
   - Redirect mode → return redirect_url
   - Success → create OfficeGuyTransaction
   - Failure → return error message
```

**Response Handling:**
```php
// Redirect mode
if ($redirectMode) {
    return [
        'success' => true,
        'redirect_url' => $response['Data']['RedirectURL'],
        'response' => $response,
    ];
}

// Direct mode - Success
if ($response['Status'] === 0) {
    // Create OfficeGuyTransaction record
    $transaction = OfficeGuyTransaction::create([...]);
    
    // Dispatch PaymentCompleted event
    event(new PaymentCompleted($transaction));
    
    return [
        'success' => true,
        'payment' => $transaction,
        'response' => $response,
    ];
}

// Failure
return [
    'success' => false,
    'message' => $response['UserErrorMessage'] ?? 'שגיאה',
    'response' => $response,
];
```

#### `processResolvedIntent(ResolvedPaymentIntent $intent)` ⭐⭐
**Bridge between CheckoutIntent → Payment**

**Flow:**
```php
1. Resolve saved token (if exists):
   - Search by token ID (not UUID!)
   - Validate owner (security!)
   - Get token model or null

2. Build extra parameters:
   - RedirectURL (if redirect mode)
   - CancelRedirectURL (if redirect mode)

3. Call processCharge() with resolved data
```

**Critical Security:**
```php
// Search by DATABASE ID, not UUID!
$tokenModel = OfficeGuyToken::query()
    ->where('id', $intent->token)       // ← $intent->token = ID (integer)
    ->where('owner_type', 'client')     // Security: owner validation
    ->where('owner_id', $customerId)    // Security: owner validation
    ->first();
```

#### `processRefund(...)` ⭐
**החזר כספי ללקוח**

**Endpoint:** `/billing/payments/charge/`

**Payload:**
```php
[
    'Credentials' => [...],
    'Customer' => ['ID' => $sumitCustomerId],
    'PaymentMethod' => [
        'CreditCard_AuthNumber' => $transactionId,  // ← Auth number מקורי
    ],
    'Items' => [
        [
            'Quantity' => 1,
            'UnitPrice' => -$amount,  // ← סכום שלילי!
            'Item' => ['Name' => $reason],
        ]
    ],
    'SupportCredit' => 'true',  // ← מאפשר החזר
]
```

**🚨 Critical:** סכום **שלילי** + `SupportCredit = true`

---

## 🔄 תלויות קריטיות

### Services שנקראים
- `OfficeGuyApi::post()` ← **כל** קריאות API
- `config()` ← **המון** קריאות להגדרות

### DTOs משומשים
- `ResolvedPaymentIntent` ← Input ל-`processResolvedIntent()`
- `Payable` (Contract) ← Interface לכל הפונקציות

### Models משומשים
- `OfficeGuyTransaction` ← יצירה ב-`processCharge()`
- `OfficeGuyToken` ← קריאה ב-`processResolvedIntent()`

### Events שנשלחים
- `PaymentCompleted` ← כשתשלום מצליח
- `PaymentFailed` ← כשתשלום נכשל

---

## 🚨 נקודות קריטיות לזכור

### 1. Customer Duplication Prevention
```php
// ✅ CORRECT: Existing customer
['Customer' => ['ID' => 123456789]]

// ❌ WRONG: Causes duplicates!
['Customer' => ['ID' => 123456789, 'Name' => '...', 'Email' => '...']]
```

### 2. Token Security
```php
// ✅ CORRECT: Use token's citizen_id
'CreditCard_CitizenID' => $token->citizen_id,

// ❌ WRONG: User can fake citizen_id!
'CreditCard_CitizenID' => $request->input('citizen_id'),
```

### 3. Refunds Use Negative Amounts
```php
// ✅ CORRECT
'UnitPrice' => -100.00,
'SupportCredit' => 'true',

// ❌ WRONG
'UnitPrice' => 100.00,  // Charges instead of refunds!
```

### 4. Token ID vs Token UUID
```php
// ✅ CORRECT: Search by ID (integer)
->where('id', $intent->token)  // $intent->token = 42

// ❌ WRONG: Search by UUID
->where('token', $intent->token)  // Won't find!
```

---

## 📈 חומרת השימוש

**High Traffic Methods:**
- ✅ `buildChargeRequest()` - כל תשלום
- ✅ `processCharge()` - כל תשלום  
- ✅ `getCredentials()` - כל קריאת API
- ✅ `getOrderCustomer()` - כל תשלום
- ✅ `getPaymentOrderItems()` - כל תשלום

**Medium Traffic:**
- ⚠️ `processResolvedIntent()` - תשלומי checkout
- ⚠️ `processRefund()` - החזרים
- ⚠️ `setPaymentMethodForCustomer()` - שמירת tokens

**Low Traffic:**
- 🔵 `getPaymentMethodsForCustomer()` - רשימת כרטיסים
- 🔵 `testPayment()` - בדיקות
- 🔵 `removePaymentMethodForCustomer()` - מחיקות

---

## ✅ לסיכום

**PaymentService = ה-Service הכי קריטי בחבילה!**

**תפקיד ראשי:**
- בניית payloads ל-SUMIT API
- עיבוד תשלומים end-to-end
- ניהול tokens + payment methods
- החזרים

**נקודות חוזק:**
- ✅ Comprehensive - מכסה את כל סוגי התשלומים
- ✅ Security guards - מונע לקוחות כפולים
- ✅ Token security - validation מלא
- ✅ Flexible - תומך ב-3 PCI modes

**נקודות לשיפור:**
- ⚠️ 1,178 שורות - אולי לפצל?
- ⚠️ כל המתודות static - לא testable בקלות
- ⚠️ הרבה logic ב-`buildChargeRequest()` - לשקול refactor

---

**Generated:** $(date)
