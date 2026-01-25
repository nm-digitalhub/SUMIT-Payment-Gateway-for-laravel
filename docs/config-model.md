להלן דוח מסודר (בפורמט שאפשר להדביק ל־PR/Issue), כולל פסיקה ארכיטקטונית, סיכון, ו־Plan לתיקון. בסוף עונה ישירות על השאלה “האם להמשיך לתקן את ההפרות”.

⸻

דוח סיווג: שימושים של App\Models\* בתוך ה־Package

עקרון היעד

ה־Package צריך לעבוד מול Bindings / DI / Repositories, ולא לבצע Hard-code ל־App\Models\* (למעט “Reference UI/Examples” שמתועדים היטב).

⸻

1) ✅ Handlers – Reference Implementations

ממצאים
	•	GenericFulfillmentHandler.php
	•	InfrastructureFulfillmentHandler.php
	•	DigitalProductFulfillmentHandler.php

פסיקה
✅ תקין בתנאי שזה מתועד כ־Reference Implementation וניתן להחלפה באמצעות bindings.

המלצה
	•	להשאיר
	•	להוסיף/לוודא PHPDoc חד וברור בראש כל Handler:

/**
 * REFERENCE IMPLEMENTATION ONLY.
 * Applications SHOULD bind their own handlers via configuration/container.
 * This implementation may reference App\Models\* intentionally as an example.
 */

סיכון
נמוך.

⸻

2) 🔴 Controllers – Violations (דורש תיקון)

ממצאים
	•	GithubWebhookController.php – use App\Models\User
	•	CheckEmailController.php – use App\Models\User
	•	PublicCheckoutController.php – User::where(...), User::create(...)

פסיקה
🔴 זו הפרה קריטית של boundary, כי Controllers הם “פני החבילה” והם מכתיבים coupling לאפליקציה.

מה צריך להיות במקום
ה־Controller לא אמור להכיר App\Models\User אלא לפנות ל־abstraction:

אפשרות A (מהירה ופשוטה): resolve class-string מה־config/container

$userModel = app('officeguy.customer_model');
$user = $userModel::query()->where('email', $email)->first();

אפשרות B (מומלצת): Repository Interface

$customers = app(CustomerRepositoryInterface::class);
$user = $customers->findOrCreateByEmail($email, $payload);

סיכון
גבוה (שובר שימוש בפרויקטים שלא משתמשים ב-App\Models\User).

עדיפות
P0 – לתקן עכשיו.

⸻

3) ✅ Models – Legitimate Dynamic Resolution

ממצאים
	•	OfficeGuyTransaction.php
	•	SumitWebhook.php
	•	OfficeGuyDocument.php

שימוש של:
	•	app('officeguy.customer_model') עם fallback

פסיקה
✅ נכון, זה בדיוק pattern היעד.

המלצה
	•	להשאיר
	•	רק לוודא שהתיעוד של ה-bindings קיים בקונפיג.

סיכון
נמוך.

⸻

4) 🟠 Filament Resources – תלוי כוונה (לא “אוטומטית acceptable”)

כאן אני מתקן נקודה מהניסוח שלך:
אמרת “Resources הם UI layer ומוגדרים ב־app, לא ב־package” — אבל לפי הממצאים שלך נראה שחלקם נמצאים בתוך ה־package. אם הם בתוך החבילה, הם כן חלק מהתחייבות החבילה ולכן זה נהיה “questionable”.

פסיקה
🟠 שני מצבים אפשריים:

מצב 4A: ה־Resources בתוך ה־Package ונחשבים מוצר מוגמר

אז זה מותר, אבל חייבים:
	•	להצהיר שהם “Opinionated UI”
	•	ולספק bindings ל-models שהם צורכים (כמו שעשית ב־config)

מצב 4B: ה־Resources בתוך ה־Package הם דוגמאות בלבד

אז צריך:
	•	להעביר אותם ל־/stubs או /examples
	•	או לתעד שהם optional ולא חלק מה-core

המלצה
לא P0. תיעוד + החלטה מוצרית מסודרת (ADR קצר).

⸻

5) 🟠/🔴 Services & Listeners – Violations (תיקון מדורג)

ממצאים
	•	CrmDataService.php – שימוש ישיר ב-Client::where(...)
	•	AutoCreateUserListener.php – שימוש ישיר במודל אפליקציה
	•	DocumentSyncListener.php – שימוש ישיר ב-User/Client

פסיקה
🟠 אם אלו רכיבים שנמצאים על “הקצה” (אינטגרציות אופציונליות) אפשר לתקן מדורג.
🔴 אם הם core flow (למשל checkout), זה כבר P0/P1.

המלצה טכנית
להעביר את כולם לשימוש באותם bindings/repositories כמו בסעיף Controllers, כדי שלא יהיו 3 דרכים שונות להשיג “Customer”.

⸻

המלצה: האם להמשיך ולתקן את ה־Violations?

✅ כן — אבל בצורה מדורגת וברורה:

שלב 1 (P0 – חובה לפני Release/merge משמעותי)
	•	לתקן את Controllers כך שלא ישתמשו ב־App\Models\User ישירות.

למה קודם Controllers?
כי הם Boundary ציבורי, וזה המקום הכי רגיש לקאפלינג.

שלב 2 (P1 – מיד לאחר P0, או ב־2.4.1)
	•	Refactor של Services/Listeners ל־bindings/repositories אחידים.

שלב 3 (P2 – החלטה מוצרית + תיעוד)
	•	להחליט מה הסטטוס של Filament Resources: product UI vs examples, ולהתאים תיעוד/מבנה בהתאם.

⸻

תוצר מומלץ להמשך (כדי לסגור את זה נקי)
	1.	לפתוח Issue: “Remove App\Models coupling from Controllers”
	2.	להוסיף ADR קצר: “Model access via bindings/repositories”
	3.	לנסח contract אחד:
	•	officeguy.customer_model
	•	CustomerRepositoryInterface
