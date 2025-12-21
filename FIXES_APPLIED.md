# 🔧 PaymentsJS Integration Fix - 18/12/2025

## 🎯 הבעיה שזוהתה

בעת ניסיון לחייב כרטיס אשראי דרך `checkout.blade.php`, SUMIT החזיר שגיאה:

```
Missing CreditCard_Number/CreditCard_Token
```

**לוג SUMIT (17:28):**
```json
{
  "PaymentMethod": {
    "Type": 1
    // ❌ חסר: "SingleUseToken" או "CreditCard_Token"
  }
}
```

---

## 🔍 ניתוח השורש

לפי [תיעוד SUMIT הרשמי](https://docs.sumit.co.il):

### דרישות PaymentsJS SDK:

1. ✅ טעינת הסקריפט: `<script src="https://app.sumit.co.il/scripts/payments.js"></script>`
2. ✅ אתחול: `OfficeGuy.Payments.BindFormSubmit({ CompanyID, APIPublicKey })`
3. ❌ **טופס עם `data-og="form"`** - **חסר בקוד**
4. ❌ **שימוש נכון ב-submit event** - **נעקף בקוד**

### הבעיות שנמצאו ב-`checkout.blade.php`:

#### בעיה #1: חסר `data-og="form"` על ה-form tag

**שורה 332 (לפני):**
```html
<form id="og-checkout-form" method="POST" action="{{ $checkoutUrl }}" @submit.prevent="submitForm">
```

**לפי תיעוד SUMIT:**
```html
<form data-og="form" method="post">
```

**בלי `data-og="form"`**, PaymentsJS לא מזהה את הטופס ולא מוסיף את `og-token`!

#### בעיה #2: `form.submit()` עוקף את PaymentsJS

**שורה 1239 (לפני):**
```javascript
async submitForm() {
    // ...validation...
    this.processing = true;
    await new Promise(resolve => setTimeout(resolve, 200));  // ❌ Wait שלא עובד
    document.getElementById('og-checkout-form').submit();    // ❌ עוקף event handlers!
}
```

**הבעיה:**
- `form.submit()` שולח את הטופס **ישירות** בלי לירות את ה-submit event
- PaymentsJS מאזין ל-submit event → לא מקבל אותו → לא יוצר token!
- ה-`setTimeout(200)` לא עוזר כי PaymentsJS יוצר את ה-token **רק** כשה-submit event מתרחש

---

## ✅ הפתרון (פשוט ונכון)

### תיקון #1: הוספת `data-og="form"`

**קובץ:** `resources/views/pages/checkout.blade.php`
**שורה:** 332

```diff
- <form id="og-checkout-form" method="POST" action="{{ $checkoutUrl }}" @submit.prevent="submitForm">
+ <form id="og-checkout-form" data-og="form" method="POST" action="{{ $checkoutUrl }}" @submit.prevent="submitForm">
```

### תיקון #2: שימוש ב-`requestSubmit()` במקום `submit()`

**קובץ:** `resources/views/pages/checkout.blade.php`
**שורות:** 1224-1241

```diff
  async submitForm() {
      if (this.userExists) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
      }

      if (!this.validate()) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          return;
      }
      this.processing = true;

-     @if($settings['pci_mode'] === 'no')
-     await new Promise(resolve => setTimeout(resolve, 200));
-     @endif
-     document.getElementById('og-checkout-form').submit();
+     // ✅ FIX: Use requestSubmit() instead of submit()
+     // requestSubmit() triggers submit event → PaymentsJS can intercept and add token
+     // submit() bypasses event handlers → PaymentsJS never gets called
+     document.getElementById('og-checkout-form').requestSubmit();
  }
```

---

## 🎓 הסבר טכני

### למה `requestSubmit()` עובד ו-`submit()` לא?

| שיטה | מה קורה | PaymentsJS |
|------|---------|------------|
| **`submit()`** | שולח את הטופס **ישירות** לשרת, **בלי** לירות submit event | ❌ לא מקבל event → לא יוצר token |
| **`requestSubmit()`** | ירה את submit event **קודם**, אז שולח | ✅ מקבל event → יוצר token → מוסיף לטופס |

### זרימת עבודה תקינה עם `requestSubmit()`:

```
1. User clicks "Submit" button
   ↓
2. Alpine.js: @submit.prevent="submitForm"
   ↓
3. submitForm(): Validation
   ↓
4. submitForm(): requestSubmit()
   ↓
5. Submit Event fired
   ↓
6. PaymentsJS: Intercepts submit event
   ↓
7. PaymentsJS: Calls tokenizeSingleUse API
   ↓
8. SUMIT: Returns SingleUseToken
   ↓
9. PaymentsJS: Adds <input name="og-token" value="...">
   ↓
10. PaymentsJS: Allows form submission
   ↓
11. Form submitted to server with token
```

---

## 📊 תוצאות לאחר התיקון

### ✅ לפני (17:28 - נכשל):
```json
// Tokenization API
{
  "SingleUseToken": "ff3d10eb-90e4-4b3e-8917-6775a00c04ba"
}

// Charge API
{
  "PaymentMethod": {
    "Type": 1
    // ❌ חסר token
  }
}
→ שגיאה: "Missing CreditCard_Number/CreditCard_Token"
```

### ✅ אחרי (צפוי):
```json
// Tokenization API
{
  "SingleUseToken": "ff3d10eb-90e4-4b3e-8917-6775a00c04ba"
}

// Charge API
{
  "PaymentMethod": {
    "SingleUseToken": "ff3d10eb-90e4-4b3e-8917-6775a00c04ba",  // ✅ Token נשלח!
    "Type": 1
  }
}
→ הצלחה: "Status": 0, "ValidPayment": true
```

---

## 🚀 סיכום התיקון

|   | מה תוקן | למה זה קריטי |
|---|---------|---------------|
| **1** | הוספת `data-og="form"` | PaymentsJS **חייב** את זה כדי לזהות את הטופס |
| **2** | `submit()` → `requestSubmit()` | כדי ש-PaymentsJS **יקבל** את ה-submit event ויוסיף token |
| **3** | הסרת `setTimeout(200)` | לא נדרש - PaymentsJS מטפל בזמן בעצמו |

### קבצים ששונו:
- ✅ `vendor/officeguy/laravel-sumit-gateway/resources/views/pages/checkout.blade.php`
- ✅ `SUMIT-Payment-Gateway-for-laravel/resources/views/pages/checkout.blade.php` (העתקה)

### צעדים הבאים:
1. ✅ העתקה לחבילה המקורית - **הושלם**
2. ⏳ Git commit + tag - **ממתין**
3. ⏳ `composer update` - **ממתין**
4. ⏳ בדיקת תשלום בפועל - **ממתין**

---

**תאריך תיקון:** 18/12/2025 21:40
**גרסה:** v1.1.7 (מתוכנן)
**מתוחזק ע"י:** NM-DigitalHub
