# מדריך הטמעה מלא - SUMIT CRM API

**גרסה**: v1.9.0
**תאריך**: 01/12/2025
**חבילה**: `officeguy/laravel-sumit-gateway`
**סטטוס**: ✅ **100% CRM API Coverage!**

---

## 🎉 סיכום ההטמעה

**הושגה כיסוי מלא של 100% מכל נקודות הקצה של SUMIT CRM API!**

| קטגוריה | נקודות קצה | מומשות | התקדמות |
|----------|-------------|--------|----------|
| **CRM Data** | 9 | 9 | **100% ✅** |
| **CRM Schema** | 2 | 2 | **100% ✅** |
| **CRM Views** | 1 | 1 | **100% ✅** |
| **סה"כ** | **12** | **12** | **100% 🎉** |

---

## 📚 תוכן עניינים

1. [CRM Data - 9 נקודות קצה](#1-crm-data---9-נקודות-קצה)
2. [CRM Schema - 2 נקודות קצה](#2-crm-schema---2-נקודות-קצה)
3. [CRM Views - 1 נקודת קצה](#3-crm-views---1-נקודת-קצה)
4. [Filament Resources - פאנל ניהול](#4-filament-resources---פאנל-ניהול)
5. [דוגמאות שימוש מעשיות](#5-דוגמאות-שימוש-מעשיות)
6. [שאלות נפוצות](#6-שאלות-נפוצות)

---

## 1. CRM Data - 9 נקודות קצה

### 1.1 createEntity() - יצירת ישות חדשה

**נקודת קצה**: `POST /crm/data/createentity/`
**Service**: `CrmDataService::createEntity()`
**מיקום בקוד**: `src/Services/CrmDataService.php:27-144`

#### תיאור
יוצר ישות חדשה (לקוח, עובד, חברה וכו') בתיקיית CRM מסוימת.

#### שימוש בקוד

```php
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

$result = CrmDataService::createEntity(
    $folderId,  // מזהה תיקייה מקומי (officeguy_crm_folders.id)
    [
        'name' => 'חברת הדוגמה בע"מ',
        'email' => 'info@example.com',
        'phone' => '03-1234567',
        'address' => 'רחוב הדוגמה 123, תל אביב',
    ]
);

if ($result['success']) {
    $entity = $result['entity'];  // CrmEntity model
    $sumitId = $result['sumit_entity_id'];  // מזהה ב-SUMIT
    echo "ישות נוצרה: {$entity->name} (SUMIT ID: {$sumitId})";
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי - טרם מומש)

**מיקום צפוי**: `src/Filament/Admin/Resources/CrmEntityResource.php`

**כפתורי פעולה**:
- ✅ **Create** - כפתור "ישות חדשה" בראש הטבלה
- טופס יצירה עם כל השדות הנדרשים
- בדיקת תקינות לפי סוג התיקייה

**איך להוסיף**:
```php
// בדף CreateCrmEntity
protected function mutateFormDataBeforeCreate(array $data): array
{
    $result = CrmDataService::createEntity(
        $data['crm_folder_id'],
        $data['fields']
    );

    if (!$result['success']) {
        throw new \Exception($result['error']);
    }

    return $result['entity']->toArray();
}
```

---

### 1.2 updateEntity() - עדכון ישות קיימת

**נקודת קצה**: `POST /crm/data/updateentity/`
**Service**: `CrmDataService::updateEntity()`
**מיקום בקוד**: `src/Services/CrmDataService.php:210-302`

#### תיאור
מעדכן שדות של ישות קיימת ב-SUMIT ובמסד הנתונים המקומי.

#### שימוש בקוד

```php
$result = CrmDataService::updateEntity(
    $entityId,  // מזהה ישות מקומי (officeguy_crm_entities.id)
    [
        'name' => 'חברת הדוגמה החדשה בע"מ',
        'email' => 'new@example.com',
        'phone' => '03-9876543',
    ]
);

if ($result['success']) {
    echo "ישות עודכנה בהצלחה!";
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ✅ **Edit** - כפתור עריכה בכל שורה בטבלה
- ✅ **Bulk Edit** - עריכה מרובה של ישויות

**איך להוסיף**:
```php
// בדף EditCrmEntity
protected function mutateFormDataBeforeSave(array $data): array
{
    $result = CrmDataService::updateEntity(
        $this->record->id,
        $data['fields']
    );

    if (!$result['success']) {
        Notification::make()
            ->title('שגיאה בעדכון')
            ->body($result['error'])
            ->danger()
            ->send();

        $this->halt();
    }

    return $data;
}
```

---

### 1.3 deleteEntity() - מחיקה קשה של ישות

**נקודת קצה**: `POST /crm/data/deleteentity/`
**Service**: `CrmDataService::deleteEntity()`
**מיקום בקוד**: `src/Services/CrmDataService.php:312-384`

#### תיאור
מוחק לצמיתות ישות מ-SUMIT ומהמסד הנתונים המקומי.

⚠️ **אזהרה**: פעולה בלתי הפיכה! שקול להשתמש ב-`archiveEntity()` במקום.

#### שימוש בקוד

```php
$result = CrmDataService::deleteEntity($entityId);

if ($result['success']) {
    echo "ישות נמחקה לצמיתות";
} else {
    echo "שגיאה: {$result['error']}";
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ❌ **Delete** - כפתור מחיקה (מוסתר - מומלץ להשתמש ב-Archive)
- ✅ **Force Delete** - מחיקה כפויה (רק למנהלים, עם אזהרה)

**איך להוסיף**:
```php
use Filament\Tables\Actions\DeleteAction;

public static function table(Table $table): Table
{
    return $table
        ->actions([
            // מחיקה רכה (מומלץ)
            Action::make('archive')
                ->label('ארכיון')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (CrmEntity $record) {
                    CrmDataService::archiveEntity($record->sumit_entity_id);

                    Notification::make()
                        ->title('ישות הועברה לארכיון')
                        ->success()
                        ->send();
                }),

            // מחיקה קשה (רק למנהלים)
            DeleteAction::make()
                ->label('מחיקה לצמיתות')
                ->requiresConfirmation()
                ->modalHeading('אזהרה: מחיקה לצמיתות')
                ->modalDescription('פעולה זו בלתי הפיכה! האם אתה בטוח?')
                ->visible(fn() => auth()->user()->isAdmin())
                ->before(function (CrmEntity $record) {
                    $result = CrmDataService::deleteEntity($record->id);

                    if (!$result['success']) {
                        Notification::make()
                            ->title('שגיאה במחיקה')
                            ->body($result['error'])
                            ->danger()
                            ->send();

                        $this->halt();
                    }
                }),
        ]);
}
```

---

### 1.4 archiveEntity() - ארכיון ישות (מחיקה רכה) 🆕

**נקודת קצה**: `POST /crm/data/archiveentity/`
**Service**: `CrmDataService::archiveEntity()`
**מיקום בקוד**: `src/Services/CrmDataService.php:622-668`
**גרסה**: v1.9.0

#### תיאור
מעביר ישות לארכיון במקום למחוק אותה לצמיתות. הישות המקומית מסומנת כ-`is_active = false`.

✅ **מומלץ**: שימוש ב-archive במקום delete לשמירת היסטוריה.

#### שימוש בקוד

```php
$result = CrmDataService::archiveEntity($sumitEntityId);

if ($result['success']) {
    echo "ישות הועברה לארכיון (ניתן לשחזר)";
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ✅ **Archive** - כפתור ארכיון ראשי (מומלץ)
- ✅ **Restore** - שחזור מארכיון
- ✅ **Bulk Archive** - ארכיון מרובה

**דוגמה מלאה**:
```php
use Filament\Tables\Filters\TernaryFilter;

public static function table(Table $table): Table
{
    return $table
        ->filters([
            // פילטר לתצוגת ישויות מארכיון
            TernaryFilter::make('is_active')
                ->label('סטטוס')
                ->placeholder('הכל')
                ->trueLabel('פעיל')
                ->falseLabel('בארכיון')
                ->default(true),  // ברירת מחדל: רק פעילים
        ])
        ->actions([
            // ארכיון
            Action::make('archive')
                ->label('העבר לארכיון')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn(CrmEntity $record) => $record->is_active)
                ->action(function (CrmEntity $record) {
                    $result = CrmDataService::archiveEntity($record->sumit_entity_id);

                    if ($result['success']) {
                        Notification::make()
                            ->title('הועבר לארכיון')
                            ->body("הישות '{$record->name}' הועברה לארכיון")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('שגיאה')
                            ->body($result['error'])
                            ->danger()
                            ->send();
                    }
                }),

            // שחזור
            Action::make('restore')
                ->label('שחזר')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn(CrmEntity $record) => !$record->is_active)
                ->action(function (CrmEntity $record) {
                    $record->update(['is_active' => true]);

                    Notification::make()
                        ->title('ישות שוחזרה')
                        ->success()
                        ->send();
                }),
        ])
        ->bulkActions([
            // ארכיון מרובה
            BulkAction::make('archive')
                ->label('העבר לארכיון')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (Collection $records) {
                    $archived = 0;
                    $failed = 0;

                    foreach ($records as $record) {
                        $result = CrmDataService::archiveEntity($record->sumit_entity_id);
                        $result['success'] ? $archived++ : $failed++;
                    }

                    Notification::make()
                        ->title("הועברו לארכיון: {$archived}")
                        ->body($failed > 0 ? "נכשלו: {$failed}" : null)
                        ->success()
                        ->send();
                }),
        ]);
}
```

---

### 1.5 getEntity() - קבלת פרטי ישות

**נקודת קצה**: `POST /crm/data/getentity/`
**Service**: `CrmDataService::getEntity()`
**מיקום בקוד**: `src/Services/CrmDataService.php:154-199`

#### תיאור
מחזיר את כל הפרטים של ישות מסוימת מ-SUMIT.

#### שימוש בקוד

```php
$result = CrmDataService::getEntity($sumitEntityId);

if ($result['success']) {
    $entityData = $result['entity'];
    echo "שם: {$entityData['Name']}";
    echo "אימייל: {$entityData['Email']}";
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ✅ **View** - תצוגת פרטים מלאה
- ✅ **Refresh** - רענון נתונים מ-SUMIT

**דוגמה**:
```php
Action::make('refresh')
    ->label('רענן מ-SUMIT')
    ->icon('heroicon-o-arrow-path')
    ->action(function (CrmEntity $record) {
        $result = CrmDataService::getEntity($record->sumit_entity_id);

        if ($result['success']) {
            // עדכון הנתונים המקומיים
            $record->update([
                'name' => $result['entity']['Name'] ?? $record->name,
                'email' => $result['entity']['Email'] ?? $record->email,
                // ... שאר השדות
                'last_synced_at' => now(),
            ]);

            Notification::make()
                ->title('נתונים רוענו')
                ->success()
                ->send();
        }
    }),
```

---

### 1.6 listEntities() - רשימת ישויות

**נקודת קצה**: `POST /crm/data/listentities/`
**Service**: `CrmDataService::listEntities()`
**מיקום בקוד**: `src/Services/CrmDataService.php:395-459`

#### תיאור
מחזיר רשימה מסוננת של ישויות מתיקייה מסוימת.

#### שימוש בקוד

```php
$result = CrmDataService::listEntities(
    $folderId,
    $page = 1,
    $pageSize = 50,
    $filters = ['Status' => 'Active']
);

if ($result['success']) {
    foreach ($result['entities'] as $entity) {
        echo "- {$entity['Name']}\n";
    }
    echo "סה\"כ: {$result['total']} ישויות";
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**תצוגת טבלה**:
- ✅ עמודות מותאמות לפי סוג התיקייה
- ✅ פילטרים דינמיים
- ✅ חיפוש מלא
- ✅ מיון לפי כל עמודה

**דוגמה**:
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('sumit_entity_id')
                ->label('מזהה SUMIT')
                ->searchable()
                ->sortable(),

            TextColumn::make('name')
                ->label('שם')
                ->searchable()
                ->sortable(),

            TextColumn::make('email')
                ->label('אימייל')
                ->searchable(),

            TextColumn::make('folder.name')
                ->label('תיקייה')
                ->sortable(),

            BadgeColumn::make('is_active')
                ->label('סטטוס')
                ->boolean()
                ->trueLabel('פעיל')
                ->falseLabel('בארכיון')
                ->colors([
                    'success' => true,
                    'danger' => false,
                ]),

            TextColumn::make('created_at')
                ->label('נוצר ב')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])
        ->defaultSort('created_at', 'desc');
}
```

---

### 1.7 countEntityUsage() - ספירת שימושים בישות 🆕

**נקודת קצה**: `POST /crm/data/countentityusage/`
**Service**: `CrmDataService::countEntityUsage()`
**מיקום בקוד**: `src/Services/CrmDataService.php:681-726`
**גרסה**: v1.9.0

#### תיאור
מחזיר ספירה של כמה פעמים הישות מצוינת במקומות אחרים במערכת (מסמכים, ישויות אחרות וכו').

✅ **שימושי**: בדיקת תלויות לפני מחיקה/ארכיון.

#### שימוש בקוד

```php
$result = CrmDataService::countEntityUsage($sumitEntityId);

if ($result['success']) {
    $count = $result['usage_count'];

    if ($count > 0) {
        echo "אזהרה: ישות זו משומשת ב-{$count} מקומות!";
    } else {
        echo "בטוח למחוק - אין תלויות";
    }
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ✅ **Usage Check** - בדיקת תלויות לפני מחיקה
- ✅ **Smart Delete** - מחיקה חכמה עם בדיקה אוטומטית

**דוגמה מתקדמת**:
```php
DeleteAction::make()
    ->label('מחק')
    ->requiresConfirmation()
    ->before(function (CrmEntity $record, DeleteAction $action) {
        // בדוק תלויות לפני מחיקה
        $result = CrmDataService::countEntityUsage($record->sumit_entity_id);

        if ($result['success'] && $result['usage_count'] > 0) {
            $count = $result['usage_count'];

            // הצג אזהרה עם ספירת התלויות
            Notification::make()
                ->title('אזהרה: קיימות תלויות')
                ->body("ישות זו משומשת ב-{$count} מקומות נוספים במערכת. המחיקה תפסיק קישורים אלו.")
                ->warning()
                ->duration(10000)
                ->send();

            // שנה את הודעת האישור
            $action->modalHeading("מחק ישות עם {$count} תלויות?");
            $action->modalDescription(
                "ישות זו מקושרת ל-{$count} אובייקטים אחרים. " .
                "המחיקה תפסיק קישורים אלו ועלולה לגרום לבעיות."
            );
        }
    })
    ->action(function (CrmEntity $record) {
        $result = CrmDataService::deleteEntity($record->id);

        if ($result['success']) {
            Notification::make()
                ->title('ישות נמחקה')
                ->success()
                ->send();
        }
    }),

// או: הצג כפתור מידע נפרד
Action::make('showUsage')
    ->label('הצג תלויות')
    ->icon('heroicon-o-information-circle')
    ->color('info')
    ->action(function (CrmEntity $record) {
        $result = CrmDataService::countEntityUsage($record->sumit_entity_id);

        if ($result['success']) {
            $count = $result['usage_count'];

            Notification::make()
                ->title('מידע על תלויות')
                ->body(
                    $count > 0
                        ? "ישות זו משומשת ב-{$count} מקומות במערכת"
                        : "אין תלויות - בטוח למחוק"
                )
                ->info()
                ->send();
        }
    }),
```

---

### 1.8 getEntityPrintHTML() - הדפסת ישות 🆕

**נקודת קצה**: `POST /crm/data/getentityprinthtml/`
**Service**: `CrmDataService::getEntityPrintHTML()`
**מיקום בקוד**: `src/Services/CrmDataService.php:741-791`
**גרסה**: v1.9.0

#### תיאור
מחזיר HTML או PDF מעוצב להדפסה של ישות בודדת.

#### פרמטרים
- `$sumitEntityId` - מזהה הישות ב-SUMIT
- `$schemaId` - מזהה התיקייה/סכמה
- `$pdf` - (אופציונלי) `true` = PDF, `false` = HTML

#### שימוש בקוד

```php
// קבלת HTML
$result = CrmDataService::getEntityPrintHTML($sumitEntityId, $schemaId, false);

if ($result['success']) {
    echo $result['html'];  // HTML מעוצב להדפסה
}

// קבלת PDF
$result = CrmDataService::getEntityPrintHTML($sumitEntityId, $schemaId, true);

if ($result['success']) {
    $pdfData = $result['pdf'];  // Base64 encoded PDF
    $pdf = base64_decode($pdfData);
    file_put_contents('entity.pdf', $pdf);
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ✅ **Print** - הדפסה ישירה
- ✅ **Download PDF** - הורדת PDF
- ✅ **Email PDF** - שליחת PDF במייל

**דוגמה מלאה**:
```php
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\Alignment;

// כפתור הדפסה/ייצוא
Action::make('export')
    ->label('ייצוא')
    ->icon('heroicon-o-document-arrow-down')
    ->form([
        Radio::make('format')
            ->label('פורמט')
            ->options([
                'html' => 'HTML (תצוגה מקדימה)',
                'pdf' => 'PDF (הורדה)',
            ])
            ->default('pdf')
            ->inline()
            ->required(),
    ])
    ->action(function (CrmEntity $record, array $data) {
        $isPdf = $data['format'] === 'pdf';

        $result = CrmDataService::getEntityPrintHTML(
            $record->sumit_entity_id,
            $record->folder->sumit_folder_id,
            $isPdf
        );

        if (!$result['success']) {
            Notification::make()
                ->title('שגיאה בייצוא')
                ->body($result['error'])
                ->danger()
                ->send();
            return;
        }

        if ($isPdf) {
            // הורדת PDF
            $pdfData = base64_decode($result['pdf']);
            $filename = "entity-{$record->sumit_entity_id}.pdf";

            return response()->streamDownload(function () use ($pdfData) {
                echo $pdfData;
            }, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } else {
            // תצוגת HTML במודל
            Notification::make()
                ->title('תצוגה מקדימה')
                ->body(new HtmlString($result['html']))
                ->info()
                ->duration(null)  // לא נעלם אוטומטית
                ->send();
        }
    }),

// או: כפתור הדפסה מהיר
Action::make('printPdf')
    ->label('הורד PDF')
    ->icon('heroicon-o-document-text')
    ->color('success')
    ->action(function (CrmEntity $record) {
        $result = CrmDataService::getEntityPrintHTML(
            $record->sumit_entity_id,
            $record->folder->sumit_folder_id,
            true  // PDF
        );

        if ($result['success']) {
            $pdfData = base64_decode($result['pdf']);
            $filename = Str::slug($record->name) . '-' . now()->format('Y-m-d') . '.pdf';

            return response()->streamDownload(
                fn() => print($pdfData),
                $filename,
                ['Content-Type' => 'application/pdf']
            );
        }
    }),
```

---

### 1.9 getEntitiesHTML() - הדפסת רשימת ישויות 🆕

**נקודת קצה**: `POST /crm/data/getentitieshtml/`
**Service**: `CrmDataService::getEntitiesHTML()`
**מיקום בקוד**: `src/Services/CrmDataService.php:806-856`
**גרסה**: v1.9.0

#### תיאור
מחזיר HTML או PDF מעוצב להדפסה של רשימת ישויות מסוננת לפי תצוגה (View).

#### פרמטרים
- `$schemaId` - מזהה התיקייה/סכמה
- `$viewId` - מזהה התצוגה (לסינון ומיון)
- `$pdf` - (אופציונלי) `true` = PDF, `false` = HTML

#### שימוש בקוד

```php
// קבלת רשימה כ-HTML
$result = CrmDataService::getEntitiesHTML($schemaId, $viewId, false);

if ($result['success']) {
    echo $result['html'];  // טבלה מעוצבת להדפסה
}

// קבלת רשימה כ-PDF
$result = CrmDataService::getEntitiesHTML($schemaId, $viewId, true);

if ($result['success']) {
    $pdfData = base64_decode($result['pdf']);
    file_put_contents('entities-report.pdf', $pdfData);
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmEntityResource` (עתידי)

**כפתורי פעולה**:
- ✅ **Export Current View** - ייצוא התצוגה הנוכחית
- ✅ **Bulk Print** - הדפסת ישויות נבחרות
- ✅ **Scheduled Report** - דוח תקופתי אוטומטי

**דוגמה מלאה**:
```php
// כפתור ראשי בראש הטבלה
use Filament\Tables\Actions\HeaderAction;

public static function table(Table $table): Table
{
    return $table
        ->headerActions([
            HeaderAction::make('exportList')
                ->label('ייצוא רשימה')
                ->icon('heroicon-o-document-arrow-down')
                ->form([
                    Select::make('view_id')
                        ->label('תצוגה')
                        ->options(function () {
                            return CrmView::where('crm_folder_id', request('folder_id'))
                                ->pluck('name', 'sumit_view_id');
                        })
                        ->required()
                        ->default(function () {
                            // תצוגת ברירת מחדל
                            return CrmView::where('crm_folder_id', request('folder_id'))
                                ->where('is_default', true)
                                ->first()?->sumit_view_id;
                        }),

                    Radio::make('format')
                        ->label('פורמט')
                        ->options([
                            'pdf' => 'PDF',
                            'html' => 'HTML',
                        ])
                        ->default('pdf')
                        ->inline(),
                ])
                ->action(function (array $data) {
                    $folderId = request('folder_id');
                    $folder = CrmFolder::find($folderId);

                    $result = CrmDataService::getEntitiesHTML(
                        $folder->sumit_folder_id,
                        $data['view_id'],
                        $data['format'] === 'pdf'
                    );

                    if (!$result['success']) {
                        Notification::make()
                            ->title('שגיאה בייצוא')
                            ->body($result['error'])
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($data['format'] === 'pdf') {
                        $pdfData = base64_decode($result['pdf']);
                        $filename = Str::slug($folder->name) . '-' . now()->format('Y-m-d') . '.pdf';

                        return response()->streamDownload(
                            fn() => print($pdfData),
                            $filename,
                            ['Content-Type' => 'application/pdf']
                        );
                    } else {
                        // הצג HTML
                        return response($result['html'], 200, [
                            'Content-Type' => 'text/html',
                        ]);
                    }
                }),
        ]);
}

// Bulk Action - ייצוא ישויות נבחרות
BulkAction::make('exportSelected')
    ->label('ייצוא נבחרים')
    ->icon('heroicon-o-document-duplicate')
    ->action(function (Collection $records) {
        // איסוף מזהי SUMIT
        $entityIds = $records->pluck('sumit_entity_id')->toArray();

        // יצירת תצוגה זמנית או שימוש בקיימת
        // (כאן נדרש לוגיקה נוספת ליצירת view מסונן)

        Notification::make()
            ->title('ייצוא החל')
            ->body('הפעולה עשויה לקחת מספר רגעים...')
            ->info()
            ->send();
    }),
```

---

## 2. CRM Schema - 2 נקודות קצה

### 2.1 listFolders() - רשימת תיקיות

**נקודת קצה**: `POST /crm/schema/listfolders/`
**Service**: `CrmSchemaService::listFolders()`
**מיקום בקוד**: `src/Services/CrmSchemaService.php:25-69`

#### תיאור
מחזיר רשימה של כל תיקיות ה-CRM הזמינות ב-SUMIT.

#### שימוש בקוד

```php
use OfficeGuy\LaravelSumitGateway\Services\CrmSchemaService;

$result = CrmSchemaService::listFolders();

if ($result['success']) {
    foreach ($result['folders'] as $folder) {
        echo "תיקייה: {$folder['Name']} (ID: {$folder['ID']})\n";
    }
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmFolderResource` (עתידי)

**מיקום צפוי**: `src/Filament/Admin/Resources/CrmFolderResource.php`

**תצוגה**:
- רשימת כל התיקיות
- סינון לפי סוג (contact, company, deal וכו')
- חיפוש לפי שם
- סנכרון אוטומטי יומי

**Command קיים**: `php artisan crm:sync-folders`

---

### 2.2 getFolder() - קבלת פרטי תיקייה

**נקודת קצה**: `POST /crm/schema/getfolder/`
**Service**: `CrmSchemaService::getFolder()`
**מיקום בקוד**: `src/Services/CrmSchemaService.php:80-125`

⚠️ **מגבלת API**: נקודת קצה זו מחזירה `null` מ-SUMIT. השירות משתמש ב-workaround דרך `listFolders()`.

#### שימוש בקוד

```php
// השירות מטפל אוטומטית במגבלה
$result = CrmSchemaService::syncFolderSchema($folderId, $folderName);

if ($result['success']) {
    $folder = $result['folder'];  // CrmFolder model
    echo "תיקייה סונכרנה: {$folder->name}";
}
```

---

## 3. CRM Views - 1 נקודת קצה

### 3.1 listViews() - רשימת תצוגות

**נקודת קצה**: `POST /crm/views/listviews/`
**Service**: `CrmViewService::listViews()`
**מיקום בקוד**: `src/Services/CrmViewService.php:27-71`
**גרסה**: v1.8.11

#### תיאור
מחזיר רשימת תצוגות שמורות עבור תיקייה מסוימת.

#### שימוש בקוד

```php
use OfficeGuy\LaravelSumitGateway\Services\CrmViewService;

$result = CrmViewService::listViews($sumitFolderId);

if ($result['success']) {
    foreach ($result['views'] as $view) {
        echo "תצוגה: {$view['Name']} (ID: {$view['ID']})\n";
    }
}
```

#### הטמעה בפאנל ניהול

**Resource**: `CrmViewResource` (עתידי)

**תצוגה**:
- רשימת תצוגות לפי תיקייה
- הצגת מספר שימושים
- אפשרות לשכפול תצוגות

**Command קיים**: `php artisan crm:sync-views`

**דוגמה לשימוש בResource**:
```php
// בתוך CrmEntityResource - בחירת תצוגה
public static function table(Table $table): Table
{
    return $table
        ->filters([
            SelectFilter::make('view')
                ->label('תצוגה שמורה')
                ->options(function () {
                    $folderId = request('folder_id');
                    return CrmView::where('crm_folder_id', $folderId)
                        ->pluck('name', 'id');
                })
                ->query(function (Builder $query, array $data) {
                    if (!empty($data['value'])) {
                        $view = CrmView::find($data['value']);
                        if ($view) {
                            // החל פילטרים של התצוגה
                            $view->applyToQuery($query);
                        }
                    }
                }),
        ]);
}
```

---

## 4. Filament Resources - פאנל ניהול

### 4.1 מבנה Resources מומלץ

```
src/Filament/Admin/Resources/
├── CrmFolderResource.php         # ניהול תיקיות CRM
│   ├── Pages/
│   │   ├── ListFolders.php
│   │   ├── ViewFolder.php
│   │   └── EditFolder.php
│   └── RelationManagers/
│       ├── EntitiesRelationManager.php
│       └── ViewsRelationManager.php
│
├── CrmEntityResource.php         # ניהול ישויות CRM (עיקרי)
│   ├── Pages/
│   │   ├── ListEntities.php
│   │   ├── CreateEntity.php
│   │   ├── EditEntity.php
│   │   └── ViewEntity.php
│   └── Widgets/
│       ├── EntityStatsWidget.php
│       └── EntityUsageWidget.php
│
└── CrmViewResource.php           # ניהול תצוגות
    └── Pages/
        ├── ListViews.php
        └── ViewView.php
```

### 4.2 Navigation Structure

**מיקום**: פאנל ניהול → קבוצת "CRM"

```php
// בכל Resource
protected static ?string $navigationGroup = 'CRM';
protected static ?int $navigationSort = 1;  // 1=Folders, 2=Entities, 3=Views
```

**תוצאה בממשק**:
```
📊 CRM
├── 📁 תיקיות (Folders)
├── 👥 ישויות (Entities)
└── 🔍 תצוגות (Views)
```

### 4.3 דוגמה: CrmEntityResource המלא

**מיקום**: `src/Filament/Admin/Resources/CrmEntityResource.php`

```php
<?php

namespace OfficeGuy\LaravelSumitGateway\Filament\Admin\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\Resource;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

class CrmEntityResource extends Resource
{
    protected static ?string $model = CrmEntity::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'ישויות CRM';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 2;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                // פרטי ישות
                Forms\Components\Section::make('פרטי ישות')
                    ->schema([
                        Forms\Components\Select::make('crm_folder_id')
                            ->label('תיקייה')
                            ->relationship('folder', 'name')
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('שם')
                            ->required(),

                        Forms\Components\TextInput::make('email')
                            ->label('אימייל')
                            ->email(),

                        // שדות נוספים...
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('שם')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('is_active')
                    ->label('סטטוס')
                    ->boolean()
                    ->trueLabel('פעיל')
                    ->falseLabel('בארכיון'),

                // עמודות נוספות...
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('סטטוס')
                    ->default(true),
            ])
            ->actions([
                // פעולות בודדות
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('archive')
                    ->label('ארכיון')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (CrmEntity $record) {
                        CrmDataService::archiveEntity($record->sumit_entity_id);
                    }),

                Tables\Actions\Action::make('printPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(function (CrmEntity $record) {
                        $result = CrmDataService::getEntityPrintHTML(
                            $record->sumit_entity_id,
                            $record->folder->sumit_folder_id,
                            true
                        );

                        if ($result['success']) {
                            $pdfData = base64_decode($result['pdf']);
                            $filename = "entity-{$record->id}.pdf";

                            return response()->streamDownload(
                                fn() => print($pdfData),
                                $filename,
                                ['Content-Type' => 'application/pdf']
                            );
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('bulkArchive')
                        ->label('ארכיון מרובה')
                        ->icon('heroicon-o-archive-box')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                CrmDataService::archiveEntity($record->sumit_entity_id);
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEntities::route('/'),
            'create' => Pages\CreateEntity::route('/create'),
            'view' => Pages\ViewEntity::route('/{record}'),
            'edit' => Pages\EditEntity::route('/{record}/edit'),
        ];
    }
}
```

---

## 5. דוגמאות שימוש מעשיות

### 5.1 ייבוא לקוחות מקובץ CSV

```php
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;
use Illuminate\Support\Facades\Storage;

// קריאת CSV
$csv = Storage::disk('local')->get('customers.csv');
$rows = array_map('str_getcsv', explode("\n", $csv));
$header = array_shift($rows);

$folderId = 123;  // תיקיית "לקוחות"
$imported = 0;
$failed = 0;

foreach ($rows as $row) {
    if (count($row) !== count($header)) continue;

    $data = array_combine($header, $row);

    $result = CrmDataService::createEntity($folderId, [
        'name' => $data['name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'address' => $data['address'],
    ]);

    $result['success'] ? $imported++ : $failed++;
}

echo "יובאו: {$imported}, נכשלו: {$failed}";
```

### 5.2 דוח חודשי אוטומטי

```php
// בקובץ routes/console.php או Command
use Illuminate\Support\Facades\Schedule;
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

Schedule::call(function () {
    $schemaId = 456;  // תיקיית "עסקאות"
    $viewId = 789;    // תצוגת "עסקאות פעילות"

    $result = CrmDataService::getEntitiesHTML($schemaId, $viewId, true);

    if ($result['success']) {
        $pdfData = base64_decode($result['pdf']);
        $filename = 'monthly-report-' . now()->format('Y-m') . '.pdf';

        Storage::disk('reports')->put($filename, $pdfData);

        // שלח במייל למנהלים
        Mail::to('manager@example.com')->send(
            new MonthlyReport($filename)
        );
    }
})->monthly();  // בכל תחילת חודש
```

### 5.3 בדיקת תלויות לפני מחיקה מרובה

```php
use OfficeGuy\LaravelSumitGateway\Services\CrmDataService;

$entitiesToDelete = CrmEntity::where('last_activity', '<', now()->subYear())->get();

$canDelete = [];
$hasDependencies = [];

foreach ($entitiesToDelete as $entity) {
    $result = CrmDataService::countEntityUsage($entity->sumit_entity_id);

    if ($result['success']) {
        if ($result['usage_count'] === 0) {
            $canDelete[] = $entity;
        } else {
            $hasDependencies[] = [
                'entity' => $entity,
                'count' => $result['usage_count'],
            ];
        }
    }
}

echo "ניתן למחוק: " . count($canDelete) . "\n";
echo "עם תלויות: " . count($hasDependencies) . "\n";

// מחק רק את אלו בלי תלויות
foreach ($canDelete as $entity) {
    CrmDataService::deleteEntity($entity->id);
}
```

---

## 6. שאלות נפוצות

### ש: איך מוסיפים Resource חדש לפאנל?

**ת**:
1. צור קובץ Resource: `src/Filament/Admin/Resources/CrmEntityResource.php`
2. רשום ב-ServiceProvider (אוטומטי עם Filament)
3. הוסף navigation group וסדר

### ש: איך מטמיעים פעולה מרובה (Bulk Action)?

**ת**: השתמש ב-`BulkAction`:
```php
BulkAction::make('archive')
    ->label('ארכיון')
    ->action(function (Collection $records) {
        foreach ($records as $record) {
            CrmDataService::archiveEntity($record->sumit_entity_id);
        }
    }),
```

### ש: איך מוסיפים אימות (validation) לפני פעולה?

**ת**: השתמש ב-`before()` hook:
```php
->before(function (CrmEntity $record, Action $action) {
    // בדוק תנאי
    if (!$record->can_delete) {
        Notification::make()
            ->title('לא ניתן למחוק')
            ->danger()
            ->send();
        $action->halt();
    }
})
```

### ש: איך מציגים התקדמות בפעולות ארוכות?

**ת**: השתמש ב-Job עם progress tracking או Livewire polling.

### ש: היכן מוגדרים ההרשאות (permissions)?

**ת**: ב-Resource policy או ישירות ב-Resource:
```php
public static function canCreate(): bool
{
    return auth()->user()->can('create_crm_entities');
}
```

---

## 📞 תמיכה ומידע נוסף

- **Repository**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel
- **Issues**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/issues
- **מסמכי API**: `docs/CRM_API_MAPPING.md`
- **מצב הטמעה**: `docs/CRM_IMPLEMENTATION_STATUS.md`

---

**גרסה**: v1.9.0
**עודכן**: 01/12/2025
**100% CRM API Coverage!** 🎉
