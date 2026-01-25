# Queueable Bulk Actions - Integration Guide

## Overview

החבילה `officeguy/laravel-sumit-gateway` תומכה ב-Queueable Bulk Actions החל מגרסה 2.4.0, המאפשר ביצוע פעולות bulk באופן אסינכרוני עם מעקב אחר זמן-אמת.

## Admin Panel Plugin Registration

באפליקציה שלך, עליך לרשום את ה-plugin ב-AdminPanelProvider.

**קובץ**: `app/Providers/Filament/AdminPanelProvider.php`

```php
<?php

namespace App\Providers\Filament;

use Bytexr\QueueableBulkActions\QueueableBulkActionsPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugin(QueueableBulkActionsPlugin::make())
            // ... שאר הגדרות הפאנל
        ;
    }
}
```

## Environment Configuration

הוסף את ההגדרות הבאות לקובץ `.env` שלך:

```bash
# הפעל או בטל את bulk actions
OFFICEGUY_BULK_ACTIONS_ENABLED=true

# הגדרות Queue
OFFICEGUY_BULK_ACTIONS_QUEUE=officeguy-bulk-actions
OFFICEGUY_BULK_ACTIONS_CONNECTION=redis

# Timeout ומספר ניסיונות
OFFICEGUY_BULK_ACTIONS_TIMEOUT=3600
OFFICEGUY_BULK_ACTIONS_TRIES=3
```

## Supervisor Configuration

צור קובץ תצורה חדש ל-Supervisor:

**קובץ**: `/etc/supervisor/conf.d/officeguy-bulk-actions.conf`

```ini
[program:officeguy-bulk-actions]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --queue=officeguy-bulk-actions --sleep=3 --tries=3 --timeout=3600
autostart=true
autorestart=true
numprocs=3
redirect_stderr=true
stdout_logfile=storage/logs/officeguy-bulk-actions.log
stopwaitsecs=3600
```

לאחר יצירת הקובץ, הפעל מחדש את Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start officeguy-bulk-actions:*
```

## בדיקה שהכל עובד

1. בדוק שה-queue worker רץ:
```bash
php artisan queue:work --queue=officeguy-bulk-actions --verbose
```

2. בדוק שהטבלאות נוצרו:
```bash
php artisan tinker
>>> Schema::hasTable('bulk_actions') && Schema::hasTable('bulk_action_records')
```

3. בדוק ב-Filament Admin:
   - גש ל-`/admin/subscriptions`
   - בחר מספר מנויים פעילים
   - לחץ על "Cancel Selected"
   - אמור שרואים modal עם הודעת progress

## פתרון בעיות

### ה-bulk action לא מופיע

1. בדוק ש-`OFFICEGUY_BULK_ACTIONS_ENABLED=true` ב-`.env`
2. נקה קאש: `php artisan config:clear`
3. ודא ש-Supervisor רץ: `sudo supervisorctl status`

### ה-job לא מעובד

1. בדוק את ה-logs: `storage/logs/officeguy-bulk-actions.log`
2. בדוק שה-queue worker רץ: `php artisan queue:work --queue=officeguy-bulk-actions --verbose`
3. נסה להריץ ידנית: `php artisan queue:work --once --queue=officeguy-bulk-actions`

## מידע נוסף

- תיעוד מלא של bytexr: https://github.com/bytexr/filament-queueable-bulk-actions
- תיעוד SUMIT Package: `CLAUDE.md`
- גירסה: v2.4.0+
