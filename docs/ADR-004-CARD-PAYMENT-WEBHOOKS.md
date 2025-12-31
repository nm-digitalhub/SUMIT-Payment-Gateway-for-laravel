# ADR-004: Handling Card Payments via SUMIT CRM Webhooks

**סטטוס**: ✅ Accepted
**תאריך**: 2025-12-29
**Context Owner**: Billing / Payments
**Related ADRs**:
- ADR-001 – Client as Billing Owner
- ADR-003 – Order::markAsPaid() Deprecation

---

## Context

מערכת התשלומים עושה שימוש ב-SUMIT Gateway לצורך סליקות Bit וכרטיסי אשראי (Token).

ל-Bit קיימת נקודת קצה ייעודית (IPN Webhook) המאשרת תשלום בצורה חד-משמעית.
לעומת זאת, **SUMIT אינו מספק Webhook סליקה ייעודי לתשלומי כרטיס אשראי**.

בפועל, לאחר חיוב כרטיס אשראי:
1. SUMIT יוצר **Transaction Card** ב-CRM
2. נשלח **Webhook מסוג CreateOrUpdate** עבור אותו כרטיס
3. Webhook זה מכיל את כל נתוני התשלום (סטטוס, סכום, לקוח, אמצעי תשלום)

**Webhook זה הוא ה-Signal היחיד המעיד על אישור תשלום בכרטיס אשראי.**

---

## Problem

הארכיטקטורה המקורית הגדירה:
- **Bit Webhook** = אישור תשלום
- **CRM Webhooks** = סנכרון נתונים בלבד (לא תשלום)

בפועל:
- ❌ **אין Webhook אחר עבור Card Payments**
- ❌ הזמנות שבוצעו בכרטיס אשראי **אינן עוברות ל-Paid**
- ❌ `Order::onPaymentConfirmed()` **אינו נקרא**
- ❌ תהליכים עסקיים (Provisioning, Activation) **לא מופעלים**

נדרש פתרון שמכבד:
- Source of Truth חשבונאי
- Event-driven flow
- Idempotency
- הפרדה בין Bit ל-Card

---

## Decision

### החלטה עקרונית

**CRM Transaction Webhook ישמש כאישור תשלום עבור Card Payments בלבד**,
ובצורה **מבוקרת, מסוננת ואידמפוטנטית**.

```
CRM Webhook ≠ Payment Webhook

אבל:

CRM Transaction Webhook = Payment Confirmation לכרטיס אשראי בלבד
```

---

## Detailed Design

### הבחנה בין סוגי תשלומים

| Payment Type | Webhook Source | Calls Order::onPaymentConfirmed() |
|--------------|----------------|-----------------------------------|
| **Bit** | BitWebhookController | ✅ Yes |
| **Card (Token)** | CRM Transaction Webhook | ✅ Yes (guarded) |
| **Other CRM Events** | CRM Webhooks | ❌ No |

---

### Guard Conditions (חובה)

`Order::onPaymentConfirmed()` ייקרא **רק אם כל התנאים מתקיימים**:

1. ✅ `Folder` = Transactions Folder (`1076735286`)
2. ✅ `Type` = `CreateOrUpdate`
3. ✅ `Status` = `"מאושר"`
4. ✅ סכום ≠ 0
5. ✅ **אין OfficeGuyTransaction מאושר קודם** (Idempotency)
6. ✅ קיים `payable` מסוג Order / Invoice
7. ✅ Order נמצא במצב ממתין לתשלום

---

## Implementation

### New Component

**TransactionSyncListener**

**אחריות**:
- האזנה ל-`SumitWebhookReceived`
- סינון Webhooks של Transactions
- מיפוי ל-`OfficeGuyTransaction`
- סימון `is_webhook_confirmed`
- קריאה ל-`Order::onPaymentConfirmed()`

### Pseudocode

```php
class TransactionSyncListener
{
    public function handle(SumitWebhookReceived $event): void
    {
        $webhook = $event->webhook;

        // Guard: Transactions folder only
        if ($webhook->getCrmFolderId() !== TRANSACTIONS_FOLDER_ID) {
            return;
        }

        $payload = $webhook->payload;

        // Guard: Approved only
        $status = $payload['Properties']['Property_6'][0] ?? null;
        if ($status !== 'מאושר') {
            return;
        }

        // Idempotency
        $transaction = OfficeGuyTransaction::firstOrCreateFromWebhook($payload);

        if ($transaction->is_webhook_confirmed) {
            return;
        }

        $transaction->confirmFromWebhook();

        $order = $transaction->payable;
        if ($order && method_exists($order, 'onPaymentConfirmed')) {
            $order->onPaymentConfirmed();
        }
    }
}
```

---

## Consequences

### Positive
- ✅ תשלומי כרטיס אשראי מטופלים בצורה מלאה
- ✅ אין תלות ב-API response סינכרוני
- ✅ אחידות בין Bit ו-Card ברמת Domain
- ✅ Event-driven, Idempotent, Safe
- ✅ ללא שינוי ב-Order כ-Source of Accounting

### Trade-offs
- ⚠️ שימוש ב-CRM Webhook כאישור תשלום (מגבלה של SUMIT)
- ⚠️ דורש Guard מדויק למניעת false positives
- ⚠️ תלות ב-Schema של CRM Properties

---

## Rejected Alternatives

### ❌ להמתין ל-Card Webhook ייעודי
**נדחה** — לא קיים בפועל ב-SUMIT.

### ❌ לאשר תשלום לפי API response
**נדחה** — אינו מקור אמת, פגיע ל-Race Conditions.

### ❌ לטפל בכל CRM Webhook כתשלום
**נדחה** — הפרת הפרדת אחריות וסיכון גבוה.

---

## Status

**Accepted and Implemented**
נדרש Listener ייעודי בהתאם למסמך זה.

---

## Notes

מסמך זה מעדכן ומחדד את `WEBHOOK_ENDPOINTS_ARCHITECTURE.md` ביחס לתשלומי כרטיס אשראי.

---

## Related Files

- `vendor/officeguy/laravel-sumit-gateway/src/Listeners/TransactionSyncListener.php`
- `vendor/officeguy/laravel-sumit-gateway/src/Models/OfficeGuyTransaction.php`
- `app/Models/Order.php` (onPaymentConfirmed method)
- `docs/BILLING_SOURCE_OF_TRUTH.md`
- `docs/WEBHOOK_ENDPOINTS_ARCHITECTURE.md`
