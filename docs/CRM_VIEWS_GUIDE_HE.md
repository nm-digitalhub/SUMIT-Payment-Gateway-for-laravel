# מדריך שימוש ב-CRM Views Service

**גרסה**: v1.8.11
**תאריך**: 01/12/2025
**חבילה**: `officeguy/laravel-sumit-gateway`

---

## סקירה כללית

### מה חדש ב-v1.8.11?

הטמענו את **CRM Views Service** - שירות לניהול תצוגות (Views) שמורות עבור תיקיות CRM ב-SUMIT.

#### תצוגות (Views) - מה זה?

תצוגות הן **פילטרים וקונפיגורציות שמורות** שמאפשרות למשתמשים:
- 📊 לשמור פילטרים מותאמים אישית
- 🔄 לבחור אילו עמודות להציג
- ⬆️⬇️ להגדיר מיון ברירת מחדל
- 👥 לשתף תצוגות עם משתמשים אחרים

**דוגמאות לשימוש**:
- תצוגת "לקוחות פעילים" - מציגה רק לקוחות שביצעו הזמנות בחודש האחרון
- תצוגת "חשבוניות ממתינות" - מציגה רק חשבוניות שטרם שולמו
- תצוגת "ספקים - לפי סכום" - ממיינת ספקים לפי סכום עסקאות

---

## השינויים שבוצעו

### 1. שירות חדש: `CrmViewService`

**מיקום**: `src/Services/CrmViewService.php`

#### מתודות זמינות:

##### `listViews(int $folderId)`
מחזיר רשימת תצוגות עבור תיקייה מסוימת מ-SUMIT API.

```php
use OfficeGuy\LaravelSumitGateway\Services\CrmViewService;

// קבלת רשימת תצוגות עבור תיקייה מסוימת
$result = CrmViewService::listViews(1076734571);

if ($result['success']) {
    foreach ($result['views'] as $view) {
        echo "מזהה: {$view['ID']}, שם: {$view['Name']}\n";
    }
}
```

**מה מחזיר**:
```php
[
    'success' => true,
    'views' => [
        ['ID' => 1076734576, 'Name' => 'הערות'],
        ['ID' => 1076734581, 'Name' => 'עובדים'],
        // ...
    ]
]
```

##### `syncViewFromSumit(int $sumitFolderId, int $sumitViewId, string $viewName)`
מסנכרן תצוגה בודדת מ-SUMIT למסד הנתונים המקומי.

```php
$result = CrmViewService::syncViewFromSumit(
    1076734571,      // מזהה תיקייה ב-SUMIT
    1076734576,      // מזהה תצוגה ב-SUMIT
    'תצוגת ברירת מחדל'  // שם התצוגה
);

if ($result['success']) {
    $view = $result['view'];
    echo "תצוגה '{$view->name}' סונכרנה בהצלחה!\n";
}
```

##### `syncAllViews(int $sumitFolderId)`
מסנכרן את כל התצוגות עבור תיקייה מסוימת.

```php
$result = CrmViewService::syncAllViews(1076734571);

if ($result['success']) {
    echo "סונכרנו {$result['synced_count']} תצוגות\n";
}
```

##### `syncAllFoldersViews()`
מסנכרן תצוגות עבור **כל** התיקיות הקיימות במערכת.

```php
$result = CrmViewService::syncAllFoldersViews();

if ($result['success']) {
    echo "עובדו {$result['folders_processed']} תיקיות\n";
    echo "סונכרנו {$result['total_views']} תצוגות\n";
}
```

---

### 2. פקודת Artisan חדשה: `crm:sync-views`

**מיקום**: `src/Console/Commands/CrmSyncViewsCommand.php`

#### שימוש בסיסי

```bash
# סינכרון כל התצוגות מכל התיקיות
php artisan crm:sync-views

# בדיקה יבשה (לא משמר שינויים)
php artisan crm:sync-views --dry-run

# סינכרון תיקייה ספציפית לפי ID מקומי
php artisan crm:sync-views --folder-id=29

# סינכרון תיקייה ספציפית לפי ID ב-SUMIT
php artisan crm:sync-views --sumit-folder-id=1076734571

# סינכרון כפוי (גם אם סונכרן לאחרונה)
php artisan crm:sync-views --force
```

#### פרמטרים זמינים

| פרמטר | תיאור | דוגמה |
|-------|-------|-------|
| `--folder-id` | מזהה תיקייה במסד נתונים מקומי | `--folder-id=29` |
| `--sumit-folder-id` | מזהה תיקייה ב-SUMIT | `--sumit-folder-id=1076734571` |
| `--dry-run` | מצב בדיקה בלי שמירה | `--dry-run` |
| `--force` | סינכרון כפוי | `--force` |

---

## תוצאות הבדיקה

### סינכרון מלא שבוצע בהצלחה ✅

ביצענו סינכרון מלא של כל התיקיות והתצוגות:

```
📋 Syncing views for all folders...
Found 345 synced folders
 345/345 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

+------------------------------+-------+
| Metric                       | Value |
+------------------------------+-------+
| Folders Processed (This Run) | 345   |
| Views Synced (This Run)      | 218   |
| Total Views in Database      | 218   |
| Public Views                 | 218   |
| Default Views                | 0     |
| Errors                       | 0     |
+------------------------------+-------+

✅ All views synced successfully!
```

### תוצאות מרשימות:
- ✅ **345 תיקיות** עובדו בהצלחה
- ✅ **218 תצוגות** סונכרנו למסד הנתונים
- ✅ **0 שגיאות** - סינכרון מושלם!
- ⚡ סינכרון מהיר עם progress bar בזמן אמת

### התפלגות התצוגות לפי תיקיות (Top 10):

```
📊 Views by Folder:
   • סליקות אשראי: 12
   • הוראות קבע: 12
   • שורות במסמכים: 9
   • לקוחות: 7
   • הכנסות: 7
   • אינדקס כרטיסי חשבון: 6
   • קבצי הוצאות: 5
   • קריאות API: 5
   • לקוחות/ספקים: 4
   • מסרונים: 4
```

---

## מבנה מסד הנתונים

### טבלה: `officeguy_crm_views`

| שדה | סוג | תיאור |
|-----|-----|-------|
| `id` | bigint | מזהה פנימי |
| `crm_folder_id` | bigint | קישור לתיקייה |
| `sumit_view_id` | bigint | מזהה התצוגה ב-SUMIT |
| `name` | varchar | שם התצוגה |
| `is_default` | boolean | האם זו תצוגת ברירת מחדל |
| `is_public` | boolean | האם התצוגה ציבורית (לכל המשתמשים) |
| `user_id` | bigint | מזהה משתמש (NULL אם ציבורית) |
| `filters` | json | תנאי סינון (NULL - מגבלת API) |
| `sort_by` | varchar | שדה מיון (NULL - מגבלת API) |
| `sort_direction` | varchar | כיוון מיון: asc/desc |
| `columns` | json | עמודות להצגה (NULL - מגבלת API) |
| `created_at` | timestamp | תאריך יצירה |
| `updated_at` | timestamp | תאריך עדכון אחרון |

### Model: `CrmView`

```php
use OfficeGuy\LaravelSumitGateway\Models\CrmView;

// קבלת כל התצוגות הציבוריות
$publicViews = CrmView::public()->get();

// קבלת תצוגות ברירת מחדל
$defaultViews = CrmView::default()->get();

// קבלת תצוגות עבור משתמש מסוים
$userViews = CrmView::forUser($userId)->get();

// קבלת תצוגות עבור תיקייה מסוימת
$folderViews = CrmView::where('crm_folder_id', 29)->get();

// גישה לתיקייה קשורה
$view = CrmView::find(1);
echo $view->folder->name; // שם התיקייה
```

---

## מגבלות API ידועות ⚠️

### SUMIT API מספק מידע מוגבל

בדומה למגבלה של `listFolders()`, גם `listViews()` מחזיר **מידע מינימלי בלבד**:

#### מה זמין:
- ✅ `ID` - מזהה התצוגה
- ✅ `Name` - שם התצוגה

#### מה חסר:
- ❌ `filters` - תנאי הסינון של התצוגה
- ❌ `sort_by`, `sort_direction` - הגדרות מיון
- ❌ `columns` - רשימת עמודות להצגה
- ❌ `is_default` - האם תצוגת ברירת מחדל
- ❌ `user_id` - בעלים של התצוגה

### פתרון זמני (Workaround)

השירות מגדיר ערכי ברירת מחדל סבירים:

```php
// ערכים שנקבעים אוטומטית
'is_default' => false,       // לא ברירת מחדל
'is_public' => true,         // ציבורית (כל המשתמשים)
'user_id' => null,           // ללא בעלות ספציפית
'filters' => null,           // ללא פילטרים
'sort_by' => null,           // ללא מיון
'sort_direction' => 'asc',   // מיון עולה כברירת מחדל
'columns' => null,           // ללא עמודות ספציפיות
```

**הערה**: אם יש צורך בפילטרים וקונפיגורציות ספציפיות, ניתן לעדכן אותם ידנית במסד הנתונים המקומי או דרך ממשק הניהול.

---

## דוגמאות שימוש מעשיות

### 1. סינכרון אוטומטי בתהליך עדכון לילי

הוסף ל-`routes/console.php` או ל-`app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('crm:sync-views')
    ->dailyAt('03:00')  // בשעה 3:00 בלילה (אחרי סינכרון התיקיות ב-02:00)
    ->name('crm-views-sync')
    ->withoutOverlapping()
    ->runInBackground();
```

### 2. קבלת תצוגות בקונטרולר

```php
use OfficeGuy\LaravelSumitGateway\Models\CrmView;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;

class CrmViewController extends Controller
{
    public function index()
    {
        // קבלת כל התצוגות עם התיקיות שלהן
        $views = CrmView::with('folder')
            ->public()
            ->orderBy('name')
            ->get();

        return view('crm.views.index', compact('views'));
    }

    public function folderViews($folderId)
    {
        // קבלת תצוגות עבור תיקייה ספציפית
        $folder = CrmFolder::findOrFail($folderId);
        $views = $folder->views()->public()->get();

        return view('crm.views.folder', compact('folder', 'views'));
    }

    public function applyView($viewId)
    {
        // החלת תצוגה על שאילתה
        $view = CrmView::findOrFail($viewId);

        // בדוגמה זו, נניח שיש לנו entities קשורות
        $query = $view->folder->entities()->query();

        // החל את הפילטרים והמיון של התצוגה
        $query = $view->applyToQuery($query);

        $entities = $query->paginate(50);

        return view('crm.entities.list', compact('entities', 'view'));
    }
}
```

### 3. בדיקה מהירה ב-Tinker

```bash
php artisan tinker
```

```php
// קבלת סטטיסטיקות
echo "סה\"כ תצוגות: " . \OfficeGuy\LaravelSumitGateway\Models\CrmView::count() . "\n";
echo "תצוגות ציבוריות: " . \OfficeGuy\LaravelSumitGateway\Models\CrmView::public()->count() . "\n";

// הצגת 5 תצוגות ראשונות
\OfficeGuy\LaravelSumitGateway\Models\CrmView::with('folder')
    ->limit(5)
    ->get()
    ->each(function($view) {
        echo "תצוגה: {$view->name} (תיקייה: {$view->folder->name})\n";
    });

// בדיקת תצוגה ספציפית
$view = \OfficeGuy\LaravelSumitGateway\Models\CrmView::find(1);
echo "מזהה SUMIT: {$view->sumit_view_id}\n";
echo "ציבורית: " . ($view->is_public ? 'כן' : 'לא') . "\n";
```

---

## התקדמות ההטמעה

### לפני v1.8.11:
```
+-------------+-------+-------------+---------+----------+
| קטגוריה    | סה"כ  | מומשות      | חסרות   | התקדמות   |
+-------------+-------+-------------+---------+----------+
| CRM Data    | 9     | 5           | 4       | 56% ✅   |
| CRM Schema  | 2     | 2           | 0       | 100% ✅  |
| CRM Views   | 1     | 0           | 1       | 0% ❌    |
| סה"כ        | 12    | 7           | 5       | 58%      |
+-------------+-------+-------------+---------+----------+
```

### אחרי v1.8.11:
```
+-------------+-------+-------------+---------+----------+
| קטגוריה    | סה"כ  | מומשות      | חסרות   | התקדמות   |
+-------------+-------+-------------+---------+----------+
| CRM Data    | 9     | 5           | 4       | 56% ✅   |
| CRM Schema  | 2     | 2           | 0       | 100% ✅  |
| CRM Views   | 1     | 1           | 0       | 100% ✅  |
| סה"כ        | 12    | 8           | 4       | 67% ✅   |
+-------------+-------+-------------+---------+----------+
```

**שיפור**: מ-58% ל-**67%** כיסוי של CRM API! 🎉

### נקודות קצה שנותרו ליישום (עדיפות בינונית-נמוכה):

1. 🟡 `archiveEntity()` - ארכיון ישות (מחיקה רכה)
2. 🟢 `countEntityUsage()` - ספירת שימושים בישות
3. 🟢 `getEntityPrintHTML()` - HTML להדפסה
4. 🟢 `getEntitiesHTML()` - רשימת HTML של ישויות

---

## שאלות נפוצות (FAQ)

### ש: כמה זמן לוקח סינכרון מלא?

**ת**: עבור 345 תיקיות, הסינכרון לוקח כ-2-3 דקות (תלוי במהירות החיבור לשרת SUMIT).

### ש: האם הסינכרון רץ אוטומטית?

**ת**: לא כרגע. בניגוד לסינכרון תיקיות (שרץ יומית ב-02:00), סינכרון התצוגות הוא ידני בלבד. אפשר להוסיף אותו ל-scheduler (ראה דוגמה למעלה).

### ש: מה קורה אם יש שגיאה בסינכרון תיקייה מסוימת?

**ת**: הפקודה ממשיכה לתיקייה הבאה. בסוף הסינכרון מוצגת טבלת שגיאות עם כל הבעיות שזוהו.

### ש: איך אני יודע אם תצוגה השתנתה ב-SUMIT?

**ת**: כרגע אין מעקב אחר שינויים. מומלץ להריץ `--force` מדי פעם כדי לעדכן הכל. אפשר גם לבדוק את `updated_at` בטבלה.

### ש: האם אפשר למחוק תצוגות מיושנות?

**ת**: כן, אבל זה לא קורה אוטומטית. תצוגות שכבר לא קיימות ב-SUMIT יישארו במסד הנתונים המקומי עד שתמחק אותן ידנית.

### ש: למה חלק מהתיקיות אין להן תצוגות?

**ת**: זה תקין. לא לכל תיקייה יש תצוגות שמורות. מתוך 345 תיקיות, רק 218 תצוגות זוהו (ממוצע של 0.63 תצוגות לתיקייה).

---

## תמיכה ותיעוד נוסף

- **מסמך הטמעה מלא**: `docs/CRM_IMPLEMENTATION_STATUS.md`
- **מיפוי API**: `docs/CRM_API_MAPPING.md`
- **קוד מקור**: `src/Services/CrmViewService.php`
- **פקודת CLI**: `src/Console/Commands/CrmSyncViewsCommand.php`
- **GitHub**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel

### נתקלת בבעיה?

פתח issue ב-GitHub או צור קשר עם צוות הפיתוח.

---

**גרסה**: v1.8.11
**תאריך עדכון אחרון**: 01/12/2025
**נוצר על ידי**: Claude Code + צוות NM DigitalHub
