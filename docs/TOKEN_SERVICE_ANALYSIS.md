# ניתוח מעמיק: TokenService.php - Token Management (J2/J5)

**תאריך:** 2025-01-13
**קובץ:** `src/Services/TokenService.php`
**שורות:** 212
**תפקיד:** ניהול tokens לשיטות תשלום שמורות (J2/J5 tokenization)

---

## 📋 סיכום מהיר

**TokenService** הוא ה-Service שמנהל **שיטות תשלום שמורות** (saved payment methods) באמצעות SUMIT tokenization API.

### מושגי יסוד:

**Token = כרטיס אשראי שמור**
- במקום לשמור מספר כרטיס מלא (PCI violation!) ❌
- שומרים **Token** - מזהה ייחודי ש-SUMIT מחזיק ✅
- Token + CVV = אפשר לחייב ללא כרטיס מלא

**J2 vs J5:**
- **J2 (ParamJ=2)**: Token רב-שימושי בסיסי
- **J5 (ParamJ=5)**: Token רב-שימושי משופר (recommended)
- שניהם permanent tokens (valid עד תפוגת הכרטיס)

### מאפיינים עיקריים:
- ✅ **PCI Compliance** - כרטיסים לא עוברים דרך השרת (במצב no/redirect)
- ✅ **Single-Use Token Exchange** - PaymentsJS SDK → Single-use token → Permanent token
- ✅ **Direct API Mode** - כרטיס עובר דרך השרת (PCI=yes)
- ✅ **Token Ownership** - Polymorphic relationship (User/Client/Customer)
- ✅ **Token Synchronization** - סנכרון עם SUMIT API
- ✅ **Security Guards** - Token ownership validation

---

## 🔧 מתודות (6 Methods)

### 1. `getTokenRequest()` - Build Token Creation Request (שורות 13-38) ⭐

**תפקיד:** בונה את ה-request ליצירת token לפי PCI mode

```php
public static function getTokenRequest(string $pciMode = 'no'): array
{
    $req = [
        'ParamJ'      => config('officeguy.token_param', '5'),  // J2 or J5
        'Amount'      => 1,  // Test charge amount (₪1)
        'Credentials' => PaymentService::getCredentials(),
    ];

    if ($pciMode === 'yes') {
        // PCI Mode: Card data from form fields
        $month = (int) RequestHelpers::post('og-expmonth');

        $req += [
            'CardNumber'      => RequestHelpers::post('og-ccnum'),      // Full card number!
            'CVV'             => RequestHelpers::post('og-cvv'),
            'CitizenID'       => RequestHelpers::post('og-citizenid'),
            'ExpirationMonth' => $month < 10 ? '0' . $month : (string)$month,
            'ExpirationYear'  => RequestHelpers::post('og-expyear'),
        ];

    } else {
        // Hosted Fields Mode: Single-use token from PaymentsJS SDK
        $req['SingleUseToken'] = RequestHelpers::post('og-token');
    }

    return $req;
}
```

#### תרחישי שימוש:

**תרחיש 1: PCI Mode = 'no' (Hosted Fields) - RECOMMENDED**

```
User fills form → PaymentsJS SDK validates → SUMIT generates single-use token
       ↓
JavaScript sends token to server → TokenService::getTokenRequest('no')
       ↓
Request: {
    "ParamJ": "5",
    "Amount": 1,
    "Credentials": {...},
    "SingleUseToken": "sut_abc123def456"  ← Single-use token!
}
       ↓
SUMIT exchanges single-use token → Permanent token (J5_xyz789)
```

**יתרונות:**
- ✅ כרטיס **לא** עובר דרך השרת שלך
- ✅ PCI compliance אוטומטי
- ✅ JavaScript validation בזמן אמת
- ✅ תמיכה ב-all features (recurring, authorize, etc.)

**תרחיש 2: PCI Mode = 'yes' (Direct API)**

```
User fills form → Server receives card data → TokenService::getTokenRequest('yes')
       ↓
Request: {
    "ParamJ": "5",
    "Amount": 1,
    "Credentials": {...},
    "CardNumber": "4580123456789012",      ← Full card!
    "CVV": "123",
    "CitizenID": "123456789",
    "ExpirationMonth": "12",
    "ExpirationYear": "2025"
}
       ↓
SUMIT creates permanent token (J5_xyz789)
```

**⚠️ חסרונות:**
- ❌ כרטיס עובר דרך השרת (PCI Level 1 required!)
- ❌ SSL חובה
- ❌ Security audit חובה
- ❌ אחריות מלאה על אבטחה

#### Expiration Month Formatting:

```php
$month = (int) RequestHelpers::post('og-expmonth');
'ExpirationMonth' => $month < 10 ? '0' . $month : (string)$month
```

**דוגמאות:**
- Input: "1" → Output: "01" ✅
- Input: "9" → Output: "09" ✅
- Input: "12" → Output: "12" ✅
- Input: 1 (int) → Output: "01" ✅

---

### 2. `getTokenFromResponse()` - Parse API Response (שורות 40-46)

**תפקיד:** Wrapper ל-`OfficeGuyToken::createFromApiResponse()`

```php
public static function getTokenFromResponse(
    mixed $owner,
    array $response,
    string $gatewayId = 'officeguy'
): OfficeGuyToken {
    return OfficeGuyToken::createFromApiResponse($owner, $response, $gatewayId);
}
```

**Parameters:**
- `$owner` - Polymorphic owner (User/Client/Customer)
- `$response` - SUMIT API response
- `$gatewayId` - Gateway identifier (default: 'officeguy')

**דוגמת Response:**
```php
[
    'Status' => 0,
    'Data' => [
        'Success' => true,
        'CreditCard_Token' => 'J5_abc123def456',
        'CreditCard_LastDigits' => '9012',
        'CreditCard_ExpirationMonth' => '12',
        'CreditCard_ExpirationYear' => '2025',
        'CreditCard_CitizenID' => '123456789',
        'Type' => 1,  // Credit card
    ]
]
```

**דוגמת Token שנוצר:**
```php
OfficeGuyToken {
    id: 123,
    owner_type: 'App\Models\User',
    owner_id: 456,
    token: 'J5_abc123def456',
    last_four: '9012',
    expiry_month: '12',
    expiry_year: '2025',
    citizen_id: '123456789',
    card_type: '1',
    gateway_id: 'officeguy',
    metadata: { /* full response */ },
    is_default: true,
    created_at: '2025-01-13 10:00:00',
}
```

---

### 3. `processToken()` - Complete Token Creation Flow (שורות 48-103) ⭐⭐⭐

**תפקיד:** המתודה המרכזית - מבצעת את כל תהליך יצירת ה-Token

```php
public static function processToken(mixed $owner, string $pciMode = 'no'): array
{
    // 1. Build request
    $req = self::getTokenRequest($pciMode);

    $env = config('officeguy.environment', 'www');

    // 2. Call SUMIT API
    $response = OfficeGuyApi::post(
        $req,
        '/creditguy/gateway/transaction/',
        $env,
        false
    );

    // 3. No response?
    if (!$response) {
        return [
            'success' => false,
            'message' => __('No response from payment gateway'),
        ];
    }

    $status = $response['Status'] ?? null;
    $data   = $response['Data'] ?? null;

    // 4. SUCCESS: Status = 0, Success = true
    if ($status === 0 && is_array($data) && ($data['Success'] ?? false)) {
        try {
            $token = self::getTokenFromResponse($owner, $response);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Failed to parse token: ' . $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'token'   => $token,  // OfficeGuyToken model
        ];
    }

    // 5. API ERROR (Status !== 0)
    if ($status !== 0) {
        return [
            'success' => false,
            'message' => __('Payment method update failed') . ' - ' .
                ($response['UserErrorMessage'] ?? 'Gateway error'),
        ];
    }

    // 6. DECLINE (Status = 0 but Success = false)
    return [
        'success' => false,
        'message' => __('Payment method update failed') . ' - ' .
            ($data['ResultDescription'] ?? 'Unknown decline'),
    ];
}
```

#### תהליך העבודה:

**שלב 1: Build Request**
```php
$req = self::getTokenRequest($pciMode);
```
- אם PCI='no' → SingleUseToken
- אם PCI='yes' → Card details

**שלב 2: Call SUMIT API**
```php
$response = OfficeGuyApi::post(
    $req,
    '/creditguy/gateway/transaction/',  // ← Same endpoint as payments!
    $env,
    false
);
```

**⚠️ שים לב:**
- משתמש ב-**אותו endpoint** כמו תשלומים רגילים!
- SUMIT מזהה שזה tokenization לפי `ParamJ`
- Amount=1 → זה לא תשלום אמיתי, רק tokenization

**שלב 3: Check Response**
```php
if (!$response) {
    return ['success' => false, 'message' => 'No response'];
}
```
- null = connection failed

**שלב 4: Success Path**
```php
$status = $response['Status'] ?? null;
$data   = $response['Data'] ?? null;

if ($status === 0 && is_array($data) && ($data['Success'] ?? false)) {
    $token = self::getTokenFromResponse($owner, $response);

    return [
        'success' => true,
        'token'   => $token,  // ← OfficeGuyToken model saved to DB
    ];
}
```

**תנאי הצלחה:**
- `Status = 0` (no API error)
- `Data` is array
- `Data['Success'] = true` (card validated)

**שלב 5: API Error**
```php
if ($status !== 0) {
    return [
        'success' => false,
        'message' => $response['UserErrorMessage'] ?? 'Gateway error',
    ];
}
```

**דוגמאות API Errors:**
- Status=1: Authentication failed (bad CompanyID/APIKey)
- Status=2: Invalid parameters
- Status=3: Rate limit exceeded

**שלב 6: Card Decline**
```php
return [
    'success' => false,
    'message' => $data['ResultDescription'] ?? 'Unknown decline',
];
```

**דוגמאות Declines:**
- "Invalid card number"
- "Expired card"
- "Insufficient funds"
- "Card blocked"

#### Return Format:

**Success:**
```php
[
    'success' => true,
    'token' => OfficeGuyToken {
        id: 123,
        token: 'J5_abc123',
        last_four: '9012',
        // ...
    }
]
```

**Failure:**
```php
[
    'success' => false,
    'message' => 'Human-readable error message'
]
```

#### שימושים בפועל:

**מ-Filament Resource:**
```php
use OfficeGuy\LaravelSumitGateway\Services\TokenService;

// User clicked "Save Payment Method"
$result = TokenService::processToken(
    $user,             // Owner
    'no'               // PCI mode (hosted fields)
);

if ($result['success']) {
    $token = $result['token'];

    Notification::make()
        ->title('Payment method saved')
        ->body("Card ending in {$token->last_four}")
        ->success()
        ->send();
} else {
    Notification::make()
        ->title('Failed')
        ->body($result['message'])
        ->danger()
        ->send();
}
```

---

### 4. `getPaymentMethodFromToken()` - Build PaymentMethod from Existing Token (שורות 105-115)

**תפקיד:** בונה `PaymentMethod` array לתשלום מתוך **Token קיים**

```php
public static function getPaymentMethodFromToken(
    OfficeGuyToken $token,
    ?string $cvv = null
): array {
    return [
        'CreditCard_Token'           => $token->token,           // J5_abc123
        'CreditCard_CVV'             => $cvv ?? RequestHelpers::post('og-cvv'),
        'CreditCard_CitizenID'       => $token->citizen_id,      // 123456789
        'CreditCard_ExpirationMonth' => $token->expiry_month,    // 12
        'CreditCard_ExpirationYear'  => $token->expiry_year,     // 2025
        'Type'                       => 1,                       // Credit card
    ];
}
```

#### Parameters:

- `$token` - OfficeGuyToken model (from DB)
- `$cvv` - CVV code (אופציונלי, ברירת מחדל מה-request)

**⚠️ CVV:**
- **לא** נשמר ב-DB (PCI violation!)
- חייב לבקש מהלקוח בכל תשלום
- ברירת מחדל: קורא מה-POST request (`og-cvv`)

#### שימוש ב-PaymentService:

```php
// User selected saved payment method
$token = OfficeGuyToken::find($tokenId);

// Validate ownership
if ($token->owner_id !== $user->id) {
    throw new \Exception('Token does not belong to user');
}

// Build PaymentMethod
$paymentMethod = TokenService::getPaymentMethodFromToken($token);

// Add to payment request
$request['PaymentMethod'] = $paymentMethod;

// Process payment
$response = OfficeGuyApi::post($request, '/creditguy/gateway/transaction/', 'www');
```

#### דוגמת Output:

```php
[
    'CreditCard_Token' => 'J5_abc123def456',
    'CreditCard_CVV' => '123',                // ← From user input
    'CreditCard_CitizenID' => '123456789',
    'CreditCard_ExpirationMonth' => '12',
    'CreditCard_ExpirationYear' => '2025',
    'Type' => 1,
]
```

---

### 5. `getPaymentMethodPCI()` - Build PaymentMethod from Form (שורות 117-129)

**תפקיד:** בונה `PaymentMethod` array מ**שדות טופס** (PCI='yes' mode)

```php
public static function getPaymentMethodPCI(): array
{
    $month = (int) RequestHelpers::post('og-expmonth');

    return [
        'CreditCard_Number'          => RequestHelpers::post('og-ccnum'),      // Full card!
        'CreditCard_CVV'             => RequestHelpers::post('og-cvv'),
        'CreditCard_CitizenID'       => RequestHelpers::post('og-citizenid'),
        'CreditCard_ExpirationMonth' => $month < 10 ? '0' . $month : (string) $month,
        'CreditCard_ExpirationYear'  => RequestHelpers::post('og-expyear'),
        'Type'                       => 1,
    ];
}
```

#### ⚠️ Security Warning:

**PCI Mode = 'yes' אומר:**
- כרטיס מלא עובר דרך השרת שלך ❌
- **חובה** PCI DSS Level 1 certification
- **חובה** SSL
- **חובה** Security audit
- **אחריות מלאה** על נתוני כרטיס

**מתי להשתמש:**
- רק אם יש לך PCI certification!
- לשרתים מאובטחים בלבד
- ברירת מחדל צריכה להיות `pci='no'`

#### דוגמת Output:

```php
[
    'CreditCard_Number' => '4580123456789012',        // ← Full card number!
    'CreditCard_CVV' => '123',
    'CreditCard_CitizenID' => '123456789',
    'CreditCard_ExpirationMonth' => '12',
    'CreditCard_ExpirationYear' => '2025',
    'Type' => 1,
]
```

---

### 6. `syncTokenFromSumit()` - Sync Token from SUMIT API (שורות 138-211) ⭐

**תפקיד:** מסנכרן token מקומי עם נתונים מ-SUMIT API (refresh token data)

```php
public static function syncTokenFromSumit(OfficeGuyToken $token): array
{
    try {
        // 1. Get token owner
        $owner = $token->owner;
        if (!$owner) {
            return [
                'success' => false,
                'error' => 'Token owner not found',
            ];
        }

        // 2. Get owner's SUMIT customer ID
        $sumitCustomerId = $owner->sumit_customer_id ?? null;

        if (!$sumitCustomerId) {
            return [
                'success' => false,
                'error' => 'SUMIT customer ID not found for token owner',
            ];
        }

        // 3. Fetch all payment methods from SUMIT
        $result = PaymentService::getPaymentMethodsForCustomer($sumitCustomerId, true);

        if (!$result['success']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to fetch payment methods from SUMIT',
            ];
        }

        $methods = $result['payment_methods'] ?? [];
        $updated = false;

        // 4. Find matching token in SUMIT response
        foreach ($methods as $method) {
            $apiToken = $method['CreditCard_Token'] ?? null;
            if ($apiToken === $token->token) {
                // 5. Update token with fresh data from SUMIT
                $token->update([
                    'card_type' => (string) ($method['Type'] ?? '1'),
                    'last_four' => $method['CreditCard_LastDigits']
                        ?? substr((string) ($method['CreditCard_CardMask'] ?? ''), -4),
                    'citizen_id' => $method['CreditCard_CitizenID'] ?? null,
                    'expiry_month' => str_pad(
                        (string) ($method['CreditCard_ExpirationMonth'] ?? '1'),
                        2, '0', STR_PAD_LEFT
                    ),
                    'expiry_year' => (string) ($method['CreditCard_ExpirationYear'] ?? date('Y')),
                    'metadata' => $method,  // Store full SUMIT response
                ]);

                $updated = true;
                break;
            }
        }

        // 6. Token not found in SUMIT?
        if (!$updated) {
            return [
                'success' => false,
                'error' => 'Token not found in SUMIT (may have been deleted)',
            ];
        }

        return [
            'success' => true,
            'updated' => true,
        ];

    } catch (\Throwable $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

#### תרחישי שימוש:

**תרחיש 1: Token קיים ב-SUMIT**
```php
$token = OfficeGuyToken::find(123);
$result = TokenService::syncTokenFromSumit($token);

// Result:
[
    'success' => true,
    'updated' => true,
]

// Token updated with fresh data from SUMIT ✅
```

**תרחיש 2: Token נמחק מ-SUMIT**
```php
$result = TokenService::syncTokenFromSumit($token);

// Result:
[
    'success' => false,
    'error' => 'Token not found in SUMIT (may have been deleted)',
]

// Consider soft-deleting local token ❌
```

**תרחיש 3: Owner אין לו SUMIT customer ID**
```php
$result = TokenService::syncTokenFromSumit($token);

// Result:
[
    'success' => false,
    'error' => 'SUMIT customer ID not found for token owner',
]
```

#### מתי לקרוא?

**אוטומטי:**
- בטעינת payment methods בFilament Resource
- לפני שימוש ב-token לתשלום (validation)
- ב-background job (cron) - sync all tokens

**ידני:**
- User clicks "Refresh" על payment method
- אחרי שגיאה "Token invalid"

---

## 🔗 תלויות (Dependencies)

### Models:
```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
```
- `OfficeGuyToken::createFromApiResponse()` - Parse API response
- `$token->owner` - Polymorphic relationship
- `$token->update()` - Update token data

### Services:
```php
PaymentService::getCredentials()           // Get CompanyID + APIKey
PaymentService::getPaymentMethodsForCustomer($customerId, true)  // Fetch tokens
OfficeGuyApi::post($req, $endpoint, $env)  // HTTP calls
```

### Helpers:
```php
use OfficeGuy\LaravelSumitGateway\Support\RequestHelpers;

RequestHelpers::post('og-ccnum')      // Get POST data
RequestHelpers::post('og-cvv')
RequestHelpers::post('og-expmonth')
```

### Configuration:
```php
config('officeguy.token_param')       // J2 or J5 (default: '5')
config('officeguy.environment')       // www, dev, test
```

---

## 🔐 Security Considerations

### 1. Token Ownership Validation ⚠️

**בעיה:** TokenService **לא** מוודא שה-Token שייך ל-Owner!

```php
// ❌ NO VALIDATION in TokenService
public static function getPaymentMethodFromToken(OfficeGuyToken $token, ?string $cvv = null): array
{
    // Anyone can pass any token!
    return [
        'CreditCard_Token' => $token->token,
        // ...
    ];
}
```

**פתרון:** הקוד הקורא חייב לוודא!

```php
// ✅ GOOD - Validate ownership in PaymentService
$token = OfficeGuyToken::find($tokenId);

if ($token->owner_id !== $user->id || $token->owner_type !== get_class($user)) {
    throw new \Exception('Token does not belong to user');  // Security violation!
}

$paymentMethod = TokenService::getPaymentMethodFromToken($token);
```

### 2. CVV Never Stored
```php
'CreditCard_CVV' => $cvv ?? RequestHelpers::post('og-cvv'),
```

**✅ Correct:**
- CVV קורא מהמשתמש בכל תשלום
- CVV **לא** נשמר ב-DB
- PCI compliance ✅

### 3. PCI Mode Awareness

**PCI='no' (Recommended):**
```php
// Card data never reaches server
'SingleUseToken' => RequestHelpers::post('og-token')  // Safe!
```

**PCI='yes' (Dangerous):**
```php
// Full card number on server!
'CardNumber' => RequestHelpers::post('og-ccnum')      // Requires PCI certification!
```

---

## 🎯 Best Practices

### ✅ DO:

1. **Always validate token ownership:**
```php
// ✅ GOOD
if ($token->owner_id !== $user->id) {
    abort(403, 'Unauthorized');
}

$paymentMethod = TokenService::getPaymentMethodFromToken($token);
```

2. **Use hosted fields (PCI='no'):**
```php
// ✅ GOOD
$result = TokenService::processToken($user, 'no');  // Hosted fields
```

3. **Request CVV for every payment:**
```php
// ✅ GOOD
$paymentMethod = TokenService::getPaymentMethodFromToken($token);
// CVV from user input: $paymentMethod['CreditCard_CVV']
```

4. **Sync tokens periodically:**
```php
// ✅ GOOD - Background job
foreach ($tokens as $token) {
    TokenService::syncTokenFromSumit($token);
}
```

### ❌ DON'T:

1. **Don't skip ownership validation:**
```php
// ❌ BAD - Security vulnerability!
$token = OfficeGuyToken::find($request->token_id);  // No validation!
$paymentMethod = TokenService::getPaymentMethodFromToken($token);
```

2. **Don't use PCI='yes' without certification:**
```php
// ❌ BAD - Requires PCI DSS Level 1!
$result = TokenService::processToken($user, 'yes');
```

3. **Don't store CVV:**
```php
// ❌ BAD - PCI violation!
$token->cvv = $cvv;
$token->save();
```

---

## 🔍 Known Issues & Limitations

### 1. אין Token Ownership Validation בService
**בעיה:** TokenService לא בודק שה-Token שייך ל-Owner

**פתרון:**
```php
// Add to TokenService:
protected static function validateTokenOwnership(OfficeGuyToken $token, mixed $owner): void
{
    if ($token->owner_id !== $owner->id || $token->owner_type !== get_class($owner)) {
        throw new \RuntimeException('Token does not belong to owner');
    }
}

// Use in processToken:
public static function processToken(mixed $owner, string $pciMode = 'no'): array
{
    // ... existing code ...
}
```

### 2. אין Expiry Validation
**בעיה:** לא בודק אם Token פג תוקף

**פתרון:**
```php
// Add to OfficeGuyToken model:
public function isExpired(): bool
{
    $expiryDate = Carbon::createFromFormat('Y-m', $this->expiry_year . '-' . $this->expiry_month)->endOfMonth();
    return $expiryDate->isPast();
}

// Use before payment:
if ($token->isExpired()) {
    throw new \Exception('Payment method has expired');
}
```

### 3. אין Retry Logic
**בעיה:** אם tokenization נכשל, לא מנסה שוב

**פתרון אפשרי:**
```php
public static function processTokenWithRetry(mixed $owner, string $pciMode = 'no', int $maxRetries = 3): array
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $result = self::processToken($owner, $pciMode);

        if ($result['success']) {
            return $result;
        }

        sleep(pow(2, $i));  // Exponential backoff
    }

    return $result;  // Last attempt result
}
```

---

## 📝 Recommended Improvements

### Priority 1: Add Token Ownership Validation
```php
protected static function validateOwnership(OfficeGuyToken $token, mixed $owner): void
{
    if ($token->owner_id !== $owner->id || $token->owner_type !== get_class($owner)) {
        throw new \RuntimeException('Security violation: Token does not belong to owner');
    }
}
```

### Priority 2: Add Token Expiry Check
```php
protected static function checkExpiry(OfficeGuyToken $token): void
{
    if ($token->isExpired()) {
        throw new \RuntimeException('Token has expired');
    }
}
```

### Priority 3: Add Rate Limiting
```php
// Prevent brute-force tokenization attacks
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::attempt(
    'create-token:' . $owner->id,
    5,  // Max 5 attempts
    function() use ($owner, $pciMode) {
        return self::processToken($owner, $pciMode);
    },
    60  // Per 60 seconds
);
```

### Priority 4: Add Token Usage Logging
```php
// Log every token usage for audit
activity()
    ->causedBy($user)
    ->performedOn($token)
    ->log('Token used for payment');
```

---

## 🎓 Summary

**TokenService** הוא ה-Service שמנהל saved payment methods באמצעות SUMIT tokenization:

**✅ Strengths:**
- PCI-compliant token management
- Support for J2/J5 tokens
- Single-use token exchange (hosted fields)
- Token synchronization with SUMIT
- Clean API for payment method building

**⚠️ Weaknesses:**
- No token ownership validation in Service
- No expiry check before usage
- No retry logic on failures
- Security relies on calling code

**🎯 Role:**
- Core tokenization service
- Saved payment methods management
- PCI compliance enabler
- Bridge between PaymentsJS SDK and SUMIT API

**🔒 Critical Security Rule:**
**ALWAYS validate token ownership before using TokenService methods!**

---

**Generated:** 2025-01-13
