# ניתוח זרימת Locale - תהליך שלם מקצה לקצה

> **תאריך**: 2025-12-07
> **מטרה**: הבנת הבעיה בשינוי שפה
> **סטטוס**: 🔍 בחקירה

---

## 🔄 הזרימה הצפויה (איך זה אמור לעבוד)

```
1. משתמש לוחץ על דגל שפה (אנגלית)
   ↓
2. Alpine.js: switchLanguage('en')
   ↓
3. JavaScript: יוצר form עם locale=en
   ↓
4. JavaScript: שולח POST /locale
   ↓
5. Route locale.change: מקבל את הבקשה
   ↓
6. Route logic:
   - session(['locale' => 'en'])
   - app()->setLocale('en')
   - Log: "✅ Locale Changed Successfully"
   ↓
7. return back() → redirect ל-/officeguy/checkout/2044
   ↓
8. SetLocaleMiddleware רץ על הבקשה החדשה:
   - קורא: $locale = session('locale')  // צריך להיות 'en'
   - קורא: app()->setLocale('en')
   ↓
9. PublicCheckoutController::show() רץ:
   - מחזיר view עם app()->getLocale()  // צריך להיות 'en'
   ↓
10. checkout.blade.php נטען:
    - בשורה 14: $rtl = app()->getLocale() === 'he'  // false
    - בשורה 43: <html lang="en" dir="ltr">
    ↓
11. ✅ העמוד באנגלית!
```

---

## 🐛 הזרימה הנוכחית (מה קורה בפועל)

```
1. ✅ משתמש לוחץ על דגל שפה
2. ✅ Alpine.js: switchLanguage('en')
3. ✅ JavaScript: יוצר form
4. ✅ JavaScript: שולח POST /locale
5. ✅ Route מקבל בקשה
6. ❓ Route logic - צריך לבדוק logs
7. ❓ return back() - האם redirect נכון?
8. ❓ SetLocaleMiddleware - האם רץ?
9. ❓ Controller - מה app()->getLocale() מחזיר?
10. ❌ העמוד נשאר בעברית!
```

---

## 📊 מה בדקנו עד כה

### ✅ בדיקות שעברו

1. **Alpine.js initialization**
   - ✅ Alpine נטען
   - ✅ רכיבים מאותחלים
   - ✅ Console logs מופיעים

2. **JavaScript בצד לקוח**
   - ✅ switchLanguage() נקרא
   - ✅ Form נוצר עם CSRF token
   - ✅ POST request נשלח

3. **Routes**
   - ✅ `POST /locale` קיים (route.change)
   - ✅ ה-route רשום נכון

4. **Middleware**
   - ✅ SetLocaleMiddleware קיים
   - ✅ רשום ב-bootstrap/app.php (שורה 32)
   - ✅ משתמש ב-`session('locale')`

5. **Blade Templates**
   - ✅ checkout.blade.php משתמש ב-`app()->getLocale()`
   - ✅ language-selector משתמש ב-`app()->getLocale()`

### ❓ מה עוד לא בדקנו

1. **Session persistence**
   - ❓ האם session נשמר בין בקשות?
   - ❓ האם session driver מוגדר נכון?

2. **Logs**
   - ❓ מה מופיע ב-laravel.log?
   - ❓ האם "🌍 Locale Change Request" מופיע?
   - ❓ האם "✅ Locale Changed Successfully" מופיע?
   - ❓ האם "🔧 SetLocaleMiddleware" מופיע?

3. **Route execution**
   - ❓ האם ה-route באמת רץ?
   - ❓ האם `in_array($locale, $availableLocales)` true?
   - ❓ מה `config('app.available_locales')` מחזיר?

---

## 🔬 תרחישי בעיה אפשריים

### תרחיש 1: Session לא נשמר

**סימפטום**: locale נשמר ב-session אבל לא persists בין בקשות

**סיבות אפשריות**:
- Session driver = 'array' (in-memory only)
- Session file permissions
- Cookie domain/path mismatch
- HTTPS/HTTP mismatch

**איך לבדוק**:
```php
// בתוך route locale.change, לפני return back():
\Log::info('Session ID', ['session_id' => session()->getId()]);
\Log::info('Session data', ['all' => session()->all()]);

// אחרי return back(), בתוך SetLocaleMiddleware:
\Log::info('New request session', [
    'session_id' => session()->getId(),
    'locale_from_session' => session('locale'),
]);
```

### תרחיש 2: Middleware לא רץ

**סימפטום**: SetLocaleMiddleware לא מופיע ב-logs

**סיבות אפשריות**:
- Middleware לא רשום נכון
- Route לא עובר דרך web middleware group
- Exception נזרק לפני שMiddleware רץ

**איך לבדוק**:
```bash
# בדוק את הרישום:
grep -n "SetLocaleMiddleware" bootstrap/app.php

# בדוק שהroute עובר דרך web:
php artisan route:list | grep "officeguy.public.checkout"
```

### תרחיש 3: available_locales לא מוגדר

**סימפטום**: `in_array($locale, $availableLocales)` = false

**סיבות אפשריות**:
- `config/app.php` לא מכיל `available_locales`
- Cache לא נוקה
- המפתח שונה/שגוי

**איך לבדוק**:
```php
\Log::info('Available locales', [
    'available' => config('app.available_locales'),
    'keys' => array_keys(config('app.available_locales', [])),
]);
```

### תרחיש 4: Browser Cache

**סימפטום**: הכל עובד בצד שרת אבל הדפדפן מציג cache ישן

**סיבות אפשריות**:
- Browser cache
- Service worker
- CDN cache

**איך לבדוק**:
- נסה Incognito/Private mode
- Hard refresh (Ctrl+Shift+R)
- בדוק Network tab ב-DevTools

---

## 🧪 תוכנית בדיקה מפורטת

### שלב 1: בדיקת Session Configuration

```bash
# 1. בדוק session driver
grep "SESSION_DRIVER" /var/www/vhosts/nm-digitalhub.com/httpdocs/.env

# 2. בדוק session config
cat config/session.php | grep -A5 "driver"

# 3. בדוק הרשאות session directory
ls -la storage/framework/sessions/
```

**תוצאה צפויה**:
- Driver: `file` או `database` (לא `array`!)
- Directory: `storage/framework/sessions/` עם הרשאות write
- קבצי session נוצרים כשיש פעילות

### שלב 2: בדיקת Logs בזמן אמת

```bash
# נקה logs
> storage/logs/laravel.log

# עקוב בזמן אמת
tail -f storage/logs/laravel.log | grep -E "🌍|🔧|✅|❌"
```

**תבצע**: לחץ על language selector ובחר English

**תוצאה צפויה**:
```
🌍 Locale Change Request {"requested_locale":"en",...}
✅ Locale Changed Successfully {"new_locale":"en",...}
🔧 SetLocaleMiddleware {"session_locale":"en",...}
```

### שלב 3: בדיקת available_locales

```bash
cd /var/www/vhosts/nm-digitalhub.com/httpdocs
php artisan tinker
```

```php
config('app.available_locales');
// צריך להחזיר: ['he' => [...], 'en' => [...], 'fr' => [...]]

array_keys(config('app.available_locales', []));
// צריך להחזיר: ['he', 'en', 'fr']

in_array('en', array_keys(config('app.available_locales', [])));
// צריך להחזיר: true
```

### שלב 4: בדיקת Session Persistence

הוסף debug log ב-`routes/web.php` לפני `return back()`:

```php
Route::post('locale', function () {
    $locale = request('locale');
    // ... existing code ...

    // DEBUG: Test session persistence
    session(['test_key' => 'test_value_' . now()]);
    \Log::info('🧪 Before redirect', [
        'locale_in_session' => session('locale'),
        'test_key' => session('test_key'),
        'session_id' => session()->getId(),
    ]);

    return back();
});
```

הוסף debug log ב-`SetLocaleMiddleware.php` בתחילת `handle()`:

```php
\Log::info('🧪 After redirect', [
    'locale_from_session' => session('locale'),
    'test_key' => session('test_key'),
    'session_id' => session()->getId(),
]);
```

**תוצאה צפויה**:
```
🧪 Before redirect: session_id=abc123, locale_in_session=en, test_key=test_value_...
🧪 After redirect:  session_id=abc123, locale_from_session=en, test_key=test_value_...
```

אם session_id שונה → **בעיית session**!
אם locale_from_session = null → **session לא persists**!

---

## 🎯 הבעיה הסבירה ביותר

בהתבסס על הסימפטומים:
1. ✅ Alpine.js עובד
2. ✅ Route קיים
3. ✅ Middleware רשום
4. ❌ השפה לא משתנה

**הבעיה הסבירה ביותר**: **Session לא נשמר בין בקשות**

זה יכול לקרות אם:
- Session driver = 'array'
- Session cookies לא נשמרים (SameSite/Secure issues)
- Session path/domain mismatch

---

## 🔨 פתרונות אפשריים

### פתרון 1: וודא Session Driver נכון

```bash
# בדוק .env
grep SESSION_DRIVER .env

# אם לא קיים או =array, תקן:
SESSION_DRIVER=file
SESSION_LIFETIME=120

# נקה config cache
php artisan config:clear
```

### פתרון 2: בדוק Session Cookie Settings

```php
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', false),
'same_site' => 'lax',  // לא 'strict'!
```

### פתרון 3: אם Session לא עובד - שמור בקובץ זמני

במקום להסתמך על session, שמור locale ב-cookie ישירות:

```php
// במקום:
session(['locale' => $locale]);

// השתמש:
cookie()->queue('locale', $locale, 60 * 24 * 365); // שנה

// ואז ב-SetLocaleMiddleware:
$locale = request()->cookie('locale') ?? session('locale') ?? config('app.locale');
```

---

## 📝 Next Steps

1. **הרץ בדיקת logs בזמן אמת** (שלב 2)
2. **בדוק session configuration** (שלב 1)
3. **הוסף debug logs** (שלב 4)
4. **נתח תוצאות** והחלט על פתרון

---

**עודכן**: 2025-12-07
**סטטוס**: ממתין לבדיקות
