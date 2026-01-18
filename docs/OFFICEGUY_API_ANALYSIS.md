# ניתוח מעמיק: OfficeGuyApi.php - HTTP Client ל-SUMIT API

**תאריך:** 2025-01-13
**קובץ:** `src/Services/OfficeGuyApi.php`
**שורות:** 229
**תפקיד:** HTTP Client wrapper עבור כל התקשורת עם SUMIT API

---

## 📋 סיכום מהיר

**OfficeGuyApi** הוא static service class שמשמש כשכבת התקשורת היחידה עם SUMIT API. כל Service אחר בחבילה משתמש בו לביצוע קריאות HTTP.

### מאפיינים עיקריים:
- ✅ **Static Class** - כל המתודות הן static (ללא state)
- ✅ **Wrapper על Laravel HTTP Facade** - שימוש ב-`Illuminate\Support\Facades\Http`
- ✅ **Environment Switching** - תמיכה ב-3 סביבות (www, dev, test)
- ✅ **Security** - הסרת נתוני כרטיס מהלוגים
- ✅ **Configurable Logging** - לוג מותנה בהגדרות
- ✅ **SSL Verification** - ניתן להגדרה (production: true, dev: false)
- ✅ **Error Handling** - Graceful degradation עם null return

---

## 🔧 מתודות (6 Methods)

### 1. `getUrl()` - URL Builder (שורות 28-35)

**תפקיד:** בונה את ה-URL המלא לפי סביבה ונתיב

```php
public static function getUrl(string $path, string $environment): string
{
    if ($environment === 'dev') {
        return 'http://' . $environment . '.api.sumit.co.il' . $path;
    }

    return 'https://api.sumit.co.il' . $path;
}
```

**לוגיקה:**
- **Production/Test**: `https://api.sumit.co.il{$path}`
- **Development**: `http://dev.api.sumit.co.il{$path}` (HTTP, לא HTTPS!)

**דוגמאות:**
```php
getUrl('/creditguy/gateway/transaction/', 'www')
// → https://api.sumit.co.il/creditguy/gateway/transaction/

getUrl('/creditguy/gateway/transaction/', 'dev')
// → http://dev.api.sumit.co.il/creditguy/gateway/transaction/
```

**⚠️ שים לב:**
- סביבת 'dev' משתמשת ב-HTTP (לא HTTPS) - מתאים לפיתוח בלבד!
- אין בדיקת תקינות על `$environment` - כל ערך שאינו 'dev' ייחשב כ-production

---

### 2. `post()` - Main POST Wrapper (שורות 48-62)

**תפקיד:** Wrapper פשוט ל-`postRaw()`, מחזיר null במקרה של שגיאה

```php
public static function post(
    array $request,
    string $path,
    string $environment,
    bool $sendClientIp = false
): ?array {
    $response = self::postRaw($request, $path, $environment, $sendClientIp);

    if ($response === null) {
        return null;
    }

    return $response;
}
```

**שימוש עיקרי:**
- זהו ה-API הפומבי שכל ה-Services האחרים משתמשים בו
- מחזיר `?array` - או תשובה מהשרת או `null` בשגיאה
- הקוד הקורא אחראי לטיפול ב-`null`

**דוגמה מ-PaymentService:**
```php
$response = OfficeGuyApi::post($request, '/creditguy/gateway/transaction/', 'www');

if ($response === null) {
    throw new \Exception('API call failed');
}

if ($response['Status'] !== 'Success') {
    // Handle error
}
```

---

### 3. `postRaw()` - Core HTTP Implementation (שורות 75-135) ⭐

**תפקיד:** המתודה המרכזית שמבצעת את קריאת ה-HTTP בפועל

```php
public static function postRaw(
    array $request,
    string $path,
    string $environment,
    bool $sendClientIp = false
): ?array {
    // 1. Environment fallback
    if (empty($environment)) {
        $environment = 'www';
    }

    $url = self::getUrl($path, $environment);

    // 2. Security: Create sanitized copy for logging
    $requestLog = $request;
    if (isset($requestLog['PaymentMethod'])) {
        $requestLog['PaymentMethod']['CreditCard_Number'] = '';
        $requestLog['PaymentMethod']['CreditCard_CVV'] = '';
    }
    $requestLog['CardNumber'] = '';
    $requestLog['CVV'] = '';

    self::writeToLog('Request: ' . $url . "\r\n" . json_encode($requestLog, JSON_PRETTY_PRINT), 'debug');

    // 3. Build headers
    $headers = [
        'Content-Type' => 'application/json',
        'Content-Language' => app()->getLocale(),  // he, en, fr
        'User-Agent' => 'Laravel/12.0 SUMIT-Gateway/1.0',
        'X-OG-Client' => 'Laravel',
    ];

    if ($sendClientIp) {
        $headers['X-OG-ClientIP'] = request()->ip();
    }

    try {
        // 4. Send HTTP POST
        $response = Http::withHeaders($headers)
            ->timeout(180)  // 3 minutes timeout!
            ->withOptions([
                'verify' => config('officeguy.ssl_verify', true),
            ])
            ->post($url, $request);

        $responseData = $response->json();

        self::writeToLog('Response: ' . $url . "\r\n" . json_encode($responseData), 'debug');

        return $responseData;

    } catch (RequestException $e) {
        $errorMessage = __('Problem connecting to server at ') . $url . ' (' . $e->getMessage() . ')';
        self::writeToLog('Error: ' . $errorMessage, 'error');
        return null;

    } catch (\Exception $e) {
        self::writeToLog('Exception: ' . $e->getMessage(), 'error');
        return null;
    }
}
```

#### תהליך העבודה:

**שלב 1: Environment Fallback**
```php
if (empty($environment)) {
    $environment = 'www';
}
```
- אם `$environment` ריק → ברירת מחדל ל-'www' (production)

**שלב 2: 🔒 Security - Sanitization לוגים**
```php
$requestLog = $request;
if (isset($requestLog['PaymentMethod'])) {
    $requestLog['PaymentMethod']['CreditCard_Number'] = '';
    $requestLog['PaymentMethod']['CreditCard_CVV'] = '';
}
$requestLog['CardNumber'] = '';
$requestLog['CVV'] = '';
```

**קריטי!** מונע זליגת נתוני כרטיס אשראי ללוגים:
- ✅ מחיקת `CreditCard_Number`
- ✅ מחיקת `CreditCard_CVV`
- ✅ מחיקת `CardNumber` (direct fields)
- ✅ מחיקת `CVV`

**שלב 3: Build Headers**
```php
$headers = [
    'Content-Type' => 'application/json',
    'Content-Language' => app()->getLocale(),  // he, en, fr
    'User-Agent' => 'Laravel/12.0 SUMIT-Gateway/1.0',
    'X-OG-Client' => 'Laravel',
];

if ($sendClientIp) {
    $headers['X-OG-ClientIP'] = request()->ip();
}
```

**Headers מיוחדים:**
- `Content-Language` - מתוך Laravel locale (תומך עברית/אנגלית/צרפתית)
- `X-OG-Client` - מזהה שזה Laravel (לא WooCommerce)
- `X-OG-ClientIP` - **אופציונלי**, רק אם `$sendClientIp = true`

**שלב 4: HTTP POST עם Laravel HTTP Facade**
```php
$response = Http::withHeaders($headers)
    ->timeout(180)  // 3 minutes!
    ->withOptions([
        'verify' => config('officeguy.ssl_verify', true),
    ])
    ->post($url, $request);
```

**הגדרות:**
- ⏱️ **Timeout: 180 seconds (3 minutes)** - קריאות ארוכות (תשלומים, מסמכים)
- 🔐 **SSL Verify**: `config('officeguy.ssl_verify', true)` - ברירת מחדל: true
  - Production: `true` (חובה!)
  - Development: ניתן להגדיר `false` לבדיקות מקומיות

**שלב 5: Error Handling**
```php
} catch (RequestException $e) {
    // HTTP errors (4xx, 5xx)
    return null;
} catch (\Exception $e) {
    // General exceptions
    return null;
}
```

**Graceful Degradation:**
- כל שגיאה מחזירה `null`
- הקוד הקורא חייב לבדוק `if ($response === null)`
- Errors נכתבים ללוג לפני return null

---

### 4. `checkCredentials()` - Private Key Validation (שורות 146-169)

**תפקיד:** בודק אם CompanyID + APIKey תקינים

```php
public static function checkCredentials(int $companyId, string $apiKey): ?string
{
    $credentials = [
        'CompanyID' => $companyId,
        'APIKey' => $apiKey,
    ];

    $request = [
        'Credentials' => $credentials,
    ];

    $environment = config('officeguy.environment', 'www');
    $response = self::post($request, '/website/companies/getdetails/', $environment, false);

    if ($response === null) {
        return 'No response';
    }

    if ($response['Status'] === 'Success') {
        return null;  // Success = no error
    }

    return $response['UserErrorMessage'] ?? 'Unknown error';
}
```

**Return Values:**
- `null` - האישורים תקינים ✅
- `string` - הודעת שגיאה ❌

**שימוש:**
- מתוך Filament Settings Page → "Test Connection" button
- מתוך setup wizard

**דוגמה:**
```php
$error = OfficeGuyApi::checkCredentials(1082100759, 'sk_test_abc123');

if ($error === null) {
    // Credentials valid!
} else {
    // Show error: $error
}
```

---

### 5. `checkPublicCredentials()` - Public Key Validation (שורות 180-208)

**תפקיד:** בודק אם CompanyID + APIPublicKey תקינים

```php
public static function checkPublicCredentials(int $companyId, string $apiPublicKey): ?string
{
    $credentials = [
        'CompanyID' => $companyId,
        'APIPublicKey' => $apiPublicKey,
    ];

    $request = [
        'Credentials' => $credentials,
        'CardNumber' => '12345678',
        'ExpirationMonth' => '01',
        'ExpirationYear' => '2030',
        'CVV' => '123',
        'CitizenID' => '123456789',
    ];

    $environment = config('officeguy.environment', 'www');
    $response = self::post($request, '/creditguy/vault/tokenizesingleusejson/', $environment, false);

    if ($response === null) {
        return 'No response';
    }

    if ($response['Status'] === 'Success') {
        return null;
    }

    return $response['UserErrorMessage'] ?? 'Unknown error';
}
```

**📌 שים לב:**
- משתמש בנתוני כרטיס **דמה** לבדיקה
- קורא ל-`/creditguy/vault/tokenizesingleusejson/` - endpoint ל-tokenization
- אם ה-Public Key תקין, SUMIT יקבל את הבקשה (גם עם כרטיס דמה)

**Return Values:**
- `null` - Public key תקין ✅
- `string` - הודעת שגיאה ❌

---

### 6. `writeToLog()` - Logging Wrapper (שורות 219-228)

**תפקיד:** כותב ללוג **רק אם הלוגים מופעלים**

```php
public static function writeToLog(string $text, string $type = 'debug'): void
{
    if (!config('officeguy.logging', false)) {
        return;  // Logging disabled
    }

    $channel = config('officeguy.log_channel', 'stack');

    Log::channel($channel)->log($type, $type . ': ' . $text);
}
```

**Configuration:**
```php
// config/officeguy.php
'logging' => env('OFFICEGUY_LOGGING', false),  // Default: OFF
'log_channel' => env('OFFICEGUY_LOG_CHANNEL', 'stack'),
```

**Log Levels:**
- `debug` - Request/Response (כולל payload מלא)
- `info` - General info
- `warning` - Warnings
- `error` - Errors + Exceptions

**דוגמה מלוג:**
```
[2025-01-13 10:15:30] debug: Request: https://api.sumit.co.il/creditguy/gateway/transaction/
{
    "Credentials": {
        "CompanyID": 1082100759,
        "APIKey": "..."
    },
    "PaymentMethod": {
        "CreditCard_Number": "",  ← Sanitized!
        "CreditCard_CVV": ""      ← Sanitized!
    },
    "Amount": 100
}

[2025-01-13 10:15:32] debug: Response: https://api.sumit.co.il/creditguy/gateway/transaction/
{
    "Status": "Success",
    "TransactionID": "12345"
}
```

---

## 🔗 תלויות (Dependencies)

### Laravel Facades:
```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
```

- **Http** - Laravel HTTP Client (Guzzle wrapper)
- **Log** - Laravel Logging

### Laravel Exceptions:
```php
use Illuminate\Http\Client\RequestException;
```
- נזרק על HTTP errors (4xx, 5xx)

### Configuration:
```php
config('officeguy.environment')     // www, dev, test
config('officeguy.ssl_verify')      // true/false
config('officeguy.logging')         // true/false
config('officeguy.log_channel')     // stack, single, daily, etc.
```

### Laravel Helpers:
```php
app()->getLocale()  // he, en, fr
request()->ip()     // Client IP address
__('message')       // Translations
```

---

## 🚀 מי משתמש ב-OfficeGuyApi?

**כל ה-Services בחבילה!**

### 1. PaymentService
```php
$response = OfficeGuyApi::post($request, '/creditguy/gateway/transaction/', 'www');
```

### 2. TokenService
```php
$response = OfficeGuyApi::post($request, '/creditguy/vault/tokenizesingleuse', 'www');
```

### 3. DocumentService
```php
$response = OfficeGuyApi::post($request, '/creditguy/document/', 'www');
```

### 4. BitPaymentService
```php
$response = OfficeGuyApi::post($request, '/creditguy/bit/transaction/', 'www');
```

### 5. SubscriptionService
```php
$response = OfficeGuyApi::post($request, '/creditguy/subscription/', 'www');
```

### 6. CustomerMergeService
```php
$response = OfficeGuyApi::post($request, '/creditguy/customer/', 'www');
```

**פטרן משותף:**
```php
$response = OfficeGuyApi::post($data, $endpoint, config('officeguy.environment', 'www'));

if ($response === null) {
    throw new \Exception('API call failed');
}

if ($response['Status'] !== 'Success') {
    // Handle SUMIT error
}

// Success - use $response data
```

---

## 🛡️ Security Features

### 1. Credential Sanitization בלוגים
```php
$requestLog = $request;
if (isset($requestLog['PaymentMethod'])) {
    $requestLog['PaymentMethod']['CreditCard_Number'] = '';
    $requestLog['PaymentMethod']['CreditCard_CVV'] = '';
}
$requestLog['CardNumber'] = '';
$requestLog['CVV'] = '';
```

**מה זה מגן עליו:**
- ✅ מונע זליגת מספרי כרטיס ללוגים
- ✅ מונע זליגת CVV ללוגים
- ✅ עדיין כותב את כל המבנה (debug friendly)

### 2. SSL Verification (Configurable)
```php
->withOptions([
    'verify' => config('officeguy.ssl_verify', true),
])
```

**Production:** `true` - חובה!
**Development:** ניתן ל-`false` לבדיקות מקומיות

### 3. Client IP Header (Optional)
```php
if ($sendClientIp) {
    $headers['X-OG-ClientIP'] = request()->ip();
}
```

**מתי להשתמש?**
- כאשר SUMIT צריך לדעת את ה-IP של הלקוח הסופי
- לצרכי Fraud Detection
- **לא** בשימוש רגיל (default: `false`)

### 4. Environment Isolation
```php
if ($environment === 'dev') {
    return 'http://dev.api.sumit.co.il' . $path;
}
```

- Development: `http://dev.api.sumit.co.il`
- Production: `https://api.sumit.co.il`

**מונע:** קריאות לפרודקשן מסביבת dev בטעות

---

## ⚙️ Configuration Points

| Setting | .env Variable | Default | Purpose |
|---------|---------------|---------|---------|
| `environment` | `OFFICEGUY_ENVIRONMENT` | `'www'` | www/dev/test |
| `ssl_verify` | `OFFICEGUY_SSL_VERIFY` | `true` | SSL cert verification |
| `logging` | `OFFICEGUY_LOGGING` | `false` | Enable API logging |
| `log_channel` | `OFFICEGUY_LOG_CHANNEL` | `'stack'` | Laravel log channel |

**דוגמה .env:**
```env
OFFICEGUY_ENVIRONMENT=www
OFFICEGUY_SSL_VERIFY=true
OFFICEGUY_LOGGING=true
OFFICEGUY_LOG_CHANNEL=daily
```

---

## 🔍 Error Handling Patterns

### Pattern 1: Null Return על כל שגיאה
```php
} catch (RequestException $e) {
    self::writeToLog('Error: ' . $errorMessage, 'error');
    return null;
} catch (\Exception $e) {
    self::writeToLog('Exception: ' . $e->getMessage(), 'error');
    return null;
}
```

**יתרונות:**
- ✅ Graceful degradation
- ✅ הקוד הקורא שולט בטיפול בשגיאה
- ✅ לא זורק exceptions ישירות

**חסרונות:**
- ❌ הקוד הקורא **חייב** לבדוק null
- ❌ אין מידע מפורט על השגיאה (רק בלוג)

### Pattern 2: הקוד הקורא אחראי
```php
$response = OfficeGuyApi::post($data, $endpoint, 'www');

if ($response === null) {
    // Option 1: Throw exception
    throw new \Exception('API call failed - check logs');

    // Option 2: Return error to user
    return ['success' => false, 'message' => 'Connection failed'];

    // Option 3: Retry logic
    $response = OfficeGuyApi::post($data, $endpoint, 'www');
}
```

---

## 🎯 Best Practices

### ✅ DO:

1. **Always check for null:**
```php
$response = OfficeGuyApi::post($data, $endpoint, 'www');
if ($response === null) {
    // Handle error!
}
```

2. **Use logging in development:**
```env
OFFICEGUY_LOGGING=true
OFFICEGUY_LOG_CHANNEL=daily
```

3. **Use ssl_verify in production:**
```env
OFFICEGUY_SSL_VERIFY=true
```

4. **Use environment switching:**
```php
$env = config('officeguy.environment', 'www');
OfficeGuyApi::post($data, $endpoint, $env);
```

### ❌ DON'T:

1. **Don't assume success:**
```php
// ❌ BAD
$response = OfficeGuyApi::post($data, $endpoint, 'www');
$transactionId = $response['TransactionID'];  // Crash if null!

// ✅ GOOD
$response = OfficeGuyApi::post($data, $endpoint, 'www');
if ($response === null || $response['Status'] !== 'Success') {
    throw new \Exception('Payment failed');
}
$transactionId = $response['TransactionID'];
```

2. **Don't disable SSL in production:**
```env
# ❌ NEVER in production
OFFICEGUY_SSL_VERIFY=false
```

3. **Don't hardcode environment:**
```php
// ❌ BAD
OfficeGuyApi::post($data, $endpoint, 'www');

// ✅ GOOD
$env = config('officeguy.environment', 'www');
OfficeGuyApi::post($data, $endpoint, $env);
```

---

## 🔄 API Endpoints Used

| Endpoint | Purpose | Service |
|----------|---------|---------|
| `/creditguy/gateway/transaction/` | Process payments | PaymentService |
| `/creditguy/vault/tokenizesingleuse` | Create token (J2) | TokenService |
| `/creditguy/vault/tokenizesingleusejson/` | Validate public key | checkPublicCredentials() |
| `/creditguy/bit/transaction/` | Bit payments | BitPaymentService |
| `/creditguy/document/` | Generate documents | DocumentService |
| `/creditguy/customer/` | Customer management | CustomerMergeService |
| `/creditguy/subscription/` | Recurring billing | SubscriptionService |
| `/website/companies/getdetails/` | Validate private key | checkCredentials() |

---

## 📊 Performance Characteristics

### Timeout:
- **180 seconds (3 minutes)** - ארוך מאוד!
- מתאים ל:
  - ✅ עיבוד תשלומים (עד 30 שניות)
  - ✅ יצירת מסמכים (עד 60 שניות)
  - ✅ סנכרון מלאי גדול
- ⚠️ **לא** מתאים לקריאות סנכרוניות מהר

### Retry Logic:
- ❌ **אין!** - הקוד הקורא אחראי על retries
- אפשרות: להוסיף exponential backoff בעתיד

### Connection Pooling:
- Laravel HTTP Client (Guzzle) מנהל connection pooling אוטומטית
- ✅ Efficient HTTP/1.1 keep-alive

---

## 🐛 Known Issues & Limitations

### 1. אין Retry Logic
**בעיה:** קריאה שנכשלה לא מנסה שוב אוטומטית

**פתרון אפשרי:**
```php
public static function postWithRetry(array $request, string $path, int $maxRetries = 3): ?array
{
    for ($i = 0; $i < $maxRetries; $i++) {
        $response = self::post($request, $path, config('officeguy.environment'));

        if ($response !== null) {
            return $response;
        }

        sleep(pow(2, $i));  // Exponential backoff: 1s, 2s, 4s
    }

    return null;
}
```

### 2. אין Rate Limiting Protection
**בעיה:** אפשר לשלוח בקשות ללא הגבלה

**פתרון אפשרי:**
- להוסיף Laravel Rate Limiter
- להוסיף throttling middleware

### 3. אין Response Validation
**בעיה:** לא בודק אם התגובה תקינה מבחינת מבנה

**פתרון אפשרי:**
```php
if (!isset($response['Status'])) {
    throw new \Exception('Invalid API response structure');
}
```

### 4. Timeout קבוע (180s)
**בעיה:** לא ניתן להגדיר timeout שונה לפי סוג הקריאה

**פתרון אפשרי:**
```php
public static function post(array $request, string $path, string $environment, bool $sendClientIp = false, int $timeout = 180): ?array
```

---

## 📝 Recommended Improvements

### Priority 1: Add Retry Logic
```php
use Illuminate\Support\Facades\Http;

Http::retry(3, 100, function ($exception, $request) {
    return $exception instanceof RequestException;
})
->post($url, $request);
```

### Priority 2: Add Response Validation
```php
if (!is_array($responseData) || !isset($responseData['Status'])) {
    throw new \Exception('Invalid API response');
}
```

### Priority 3: Add Request Timeout Configuration
```php
->timeout(config('officeguy.api_timeout', 180))
```

### Priority 4: Add Circuit Breaker Pattern
- אם API נכשל X פעמים → הפסק לנסות ל-Y זמן
- מונע overload על SUMIT servers

---

## 🎓 Summary

**OfficeGuyApi** הוא HTTP Client פשוט אך אפקטיבי:

**✅ Strengths:**
- Static class - קל לשימוש מכל מקום
- Wrapper נקי על Laravel HTTP
- Security: credential sanitization
- Environment switching
- Configurable logging
- Graceful error handling

**⚠️ Weaknesses:**
- אין retry logic
- אין rate limiting
- אין response validation
- Timeout קבוע
- אין circuit breaker

**🎯 Role:**
- שכבת תקשורת יחידה עם SUMIT API
- כל Service אחר משתמש בו
- Critical infrastructure component

---

**Generated:** 2025-01-13
