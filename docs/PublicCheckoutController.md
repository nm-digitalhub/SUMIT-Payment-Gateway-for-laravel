להלן תכנית Refactor “בטוח” step-by-step, עם עקרון מוביל: כל צעד קטן, ללא שינוי התנהגות (או שינוי מינימלי ומבוקר), עם נקודות בדיקה ברורות.
אני מניח שאתה עובד בלאראבל עם PHPUnit/Pest; אם אין—עדיין אפשר לבצע את השלבים, פשוט עם בדיקות ידניות.

⸻

עקרונות עבודה לפני שמתחילים
	1.	אל תשנה חתימות routes / views בשלב הראשון.
	2.	כל צעד = commit קטן (או PR קטן).
	3.	תמיד להשאיר fallback להתנהגות הקיימת.
	4.	לוגיקה חדשה עטופה במתודות קטנות בתוך אותו Controller לפני שמוציאים לשירותים.

⸻

שלב 0 – “Safety Net” (מומלץ מאוד)

0.1 הוספת בדיקות אינטגרציה מינימליות (או תרחישי QA כתובים)

אם יש לך בדיקות:
	•	Test: GET public checkout show מחזיר 200 כשמופעל.
	•	Test: GET מחזיר 404 כשמכובה.
	•	Test: POST עם card במצב non-redirect בלי token → חוזר עם error.
	•	Test: POST עם bit → מפנה ל־redirect_url כשיש.
	•	Test: idempotency: אם קיימת OfficeGuyTransaction completed → redirect success עם הודעת info.

אם אין לך בדיקות כרגע:
	•	תכתוב מסמך QA קצר (5 תרחישים) ותבצע ידנית אחרי כל שלב.

Acceptance criteria:
	•	שום רגרסיה בזרימה הקיימת.

⸻

שלב 1 – מקור אמת יחיד ל־PCI Mode (High Priority)

1.1 הוסף מתודה בקונטרולר

protected function getPciMode(): string
{
    return (string) $this->settings()->get(
        'pci',
        config('officeguy.pci_mode', config('officeguy.pci', 'no'))
    );
}

המטרה: ריכוז הלוגיקה במקום אחד. אין שינוי התנהגות בפועל, רק איחוד.

1.2 החלף את כל המקומות בקונטרולר לשימוש ב־getPciMode()

Acceptance criteria:
	•	כל הזרימות עובדות אותו דבר.
	•	לוגים עדיין נכונים.

⸻

שלב 2 – בידוד וליטוש ולידציית “Card token / og-token” (High Priority)

2.1 צור מתודה ייעודית

protected function ensureCardTokenPresent(Request $request, array $validated): ?\Illuminate\Http\RedirectResponse
{
    $paymentMethod = $validated['payment_method'] ?? null;
    $pciMode = $this->getPciMode();
    $paymentToken = $validated['payment_token'] ?? null;

    $isCard = $paymentMethod === 'card';
    $isNewToken = empty($paymentToken) || $paymentToken === 'new';
    $isNonRedirect = $pciMode !== 'redirect';
    $missingOgToken = ! $request->filled('og-token');

    if ($isCard && $isNewToken && $isNonRedirect && $missingOgToken) {
        return back()
            ->withInput()
            ->withErrors(['payment' => __('Card token was not generated. Please try again.')]);
    }

    return null;
}

2.2 בתוך process() החלף את הבלוק הקיים בקריאה למתודה

if ($response = $this->ensureCardTokenPresent($request, $validated)) {
    return $response;
}

Acceptance criteria:
	•	אותה התנהגות למשתמש.
	•	הקונטרולר קריא יותר.
	•	אין שינוי לוגי “נסתר”.

⸻

שלב 3 – חיזוק Idempotency בצורה מינימלית (High Priority)

כרגע אתה בודק completed לפי order_id בלבד. שינוי “בטוח” הוא להוסיף סינון נוסף שלא יפגע בזרימות קיימות.

3.1 מינימום שינוי: הוסף סינון amount/currency (אם קיימים בעמודות)

$existingTransaction = OfficeGuyTransaction::where('order_id', $payable->getPayableId())
    ->where('status', 'completed')
    ->where('amount', $payable->getPayableAmount())
    ->first();

אם אין amount בעמודה (או לא אמין), אל תוסיף. במקום זה:
	•	בדוק payment_id is not null
	•	או gateway_transaction_id אם קיים
	•	או השאר כמו היום (אבל אז זה נשאר חוב טכני).

Acceptance criteria:
	•	עדיין מונע double charge.
	•	לא חוסם תשלומים לגיטימיים (למשל שינוי סכום / re-price).

⸻

שלב 4 – Prefill Refactor בלי להוציא Service עדיין (Medium)

היעד: לקצר את show().

4.1 בנה מתודה שמחזירה מערך prefill

protected function buildPrefill(Request $request, Payable $payable): array
{
    // כל הלוגיקה הקיימת של user/client + query params
    // תחזיר array עם אותם keys בדיוק: prefillName, prefillEmail, ...
}

4.2 ב־show() החלף את כל בלוק prefill ל:

$prefill = $this->buildPrefill($request, $payable);

return view($view, array_merge([
   // הקיים
], $prefill));

Acceptance criteria:
	•	View מקבל בדיוק אותם משתנים.
	•	אין שינוי בערכים בפועל (אותו precedence).

⸻

שלב 5 – Payable resolution: להוציא לשירות פנימי “בתוך החבילה” (Medium/Optional)

כרגע resolvePayable() עושה הרבה. נעשה את זה בהדרגה.

5.1 צור class חדש בחבילה

OfficeGuy\LaravelSumitGateway\Services\PayableResolver

עם:

public function resolve(Request $request, string|int $id): ?Payable

העתק פנימה את הלוגיקה של resolvePayable() as-is.

5.2 בקונטרולר:

$payable = app(PayableResolver::class)->resolve($request, $id);

Acceptance criteria:
	•	אין שינוי התנהגות.
	•	לא נשבר custom resolver ב-route.
	•	ModelPayableWrapper עדיין עובד.

⸻

שלב 6 – שיפור maintainability: “showPackage/showEsim” להפוך ל-DRY (Optional)

כרגע יש כפילות: showPackage/processPackage/showEsim/processEsim.

6.1 צור helper שמגדיר resolver לפי model class

protected function setRouteResolver(Request $request, string $modelClass): void
{
    $request->route()->setParameter('resolver', function($id) use ($modelClass) {
        return class_exists($modelClass) ? $modelClass::find($id) : null;
    });
}

ואז:

public function showPackage(Request $request, string|int $id): View
{
    $this->setRouteResolver($request, \App\Models\Package::class);
    return $this->show($request, $id);
}

Acceptance criteria:
	•	endpoints נשארים זהים.
	•	resolver עובד זהה.

⸻

שלב 7 – “Hardening” נקודתי (Optional אבל מומלץ)
	•	לנקות לוגים “DEBUG” או להעביר ל־Log::debug ולשמור info רק לאירועים עסקיים.
	•	לוודא שלא נכתב מידע רגיש (אפילו masked) בסביבה רגישה.

⸻

סדר מומלץ לביצוע בפועל (כמו Checklist)
	1.	Step 0 (tests/QA)
	2.	Step 1 (PCI source of truth)
	3.	Step 2 (extract ensureCardTokenPresent)
	4.	Step 3 (idempotency minimal hardening)
	5.	Step 4 (extract buildPrefill)
	6.	Step 5 (PayableResolver service)
	7.	Step 6–7 (Optional cleanups)

