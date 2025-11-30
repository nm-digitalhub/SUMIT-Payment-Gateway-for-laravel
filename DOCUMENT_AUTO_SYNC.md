# 🔄 SUMIT Documents Auto-Sync System

Intelligent automatic synchronization system for invoices/documents from SUMIT with many-to-many subscription mapping.

## 📋 Overview

This system automatically syncs ALL documents (invoices, receipts) from SUMIT to your local database, intelligently mapping them to subscriptions using a **many-to-many relationship**.

### Why Many-to-Many?

SUMIT can **consolidate multiple subscription charges into a single invoice**. For example:
- Invoice #40000 contains 3 subscriptions: "הייי" (10₪), "חיוב" (10₪), "נאא" (10₪) = Total 30₪
- Each subscription is tracked separately in the `document_subscription` pivot table

## ✨ Features

### 1. **Intelligent Subscription Matching**

Documents are matched to subscriptions using **4 methods** (in priority order):

```php
// Method 1: ExternalReference (MOST RELIABLE)
// Format: subscription_{id}_recurring_{recurring_id}
'subscription_17_recurring_1095094793'

// Method 2: Description matching
// Exact word match for short names (≤3 chars), contains for longer names

// Method 3: Item name matching
// Exact match on invoice line items

// Method 4: Amount matching (LAST RESORT)
// Only for unique amounts (>100) or documents with no metadata
```

### 2. **Complete Subscription Coverage**

The system syncs **ALL subscriptions** including:
- ✅ Active subscriptions
- ✅ Paused subscriptions
- ✅ Cancelled subscriptions
- ✅ Expired subscriptions

**Critical**: Always uses `includeInactive = true` when syncing from SUMIT.

### 3. **Three Sync Modes**

#### A. **Daily Automatic Sync** (Scheduled)
Runs every day at 3:00 AM:
```bash
# Automatically scheduled - no action needed
# Syncs last 30 days of documents
```

Configuration in `OfficeGuyServiceProvider.php`:
```php
$schedule->command('sumit:sync-all-documents --days=30')
    ->dailyAt('03:00')
    ->withoutOverlapping(120)
    ->runInBackground();
```

#### B. **Webhook-Triggered Sync** (Real-time)
Automatically triggers when:
- Subscription charged (`RecurringCharge`)
- New subscription created (`RecurringCreated`)
- Subscription updated/cancelled (`RecurringUpdated`, `RecurringCancelled`)
- Invoice created (`InvoiceCreated`)

```php
// Automatically runs via DocumentSyncListener
// Syncs last 7 days (recent documents only)
// Runs in background queue (non-blocking)
```

#### C. **Manual Sync** (On-demand)
```bash
# Sync all users, last 30 days
php artisan sumit:sync-all-documents

# Sync specific user
php artisan sumit:sync-all-documents --user-id=123

# Sync last 60 days
php artisan sumit:sync-all-documents --days=60

# Force full sync (ignore cache)
php artisan sumit:sync-all-documents --force

# Dry run (preview without saving)
php artisan sumit:sync-all-documents --dry-run
```

## 📊 How It Works

### Step 1: Sync ALL Subscriptions
```
Found 10 users with SUMIT customer ID
  • User #1 (user@example.com)...
     ✓ Synced 18 subscriptions (including inactive)
```

**Critical**: Uses `SubscriptionService::syncFromSumit($user, true)` with `includeInactive = true`.

### Step 2: Sync Documents for Each Subscription
```
Found 18 subscriptions
 15/18 [████████████████░░░░] 83% - Subscription #17: נאא
```

For each subscription:
1. Fetches documents from SUMIT API (last N days)
2. Matches documents using 4 methods (ExternalReference, Description, Items, Amount)
3. Creates/updates `OfficeGuyDocument` records
4. Maps subscriptions in `document_subscription` pivot table

### Step 3: Summary Report
```
╔═══════════════════════════════════════════╤═══════╗
║ Metric                                    │ Value ║
╠═══════════════════════════════════════════╪═══════╣
║ Total Subscriptions                       │ 18    ║
║ Active Subscriptions                      │ 9     ║
║ Total Documents                           │ 145   ║
║ Paid Documents                            │ 112   ║
║ Documents with Multiple Subscriptions     │ 23    ║
║ Documents Synced (This Run)               │ 12    ║
╚═══════════════════════════════════════════╧═══════╝

💡 Found 23 consolidated invoices (multiple subscriptions per document)
```

## 🗄️ Database Schema

### `document_subscription` Pivot Table
```sql
CREATE TABLE document_subscription (
    id BIGINT PRIMARY KEY,
    document_id BIGINT,         -- FK to officeguy_documents
    subscription_id BIGINT,     -- FK to officeguy_subscriptions
    amount DECIMAL(10,2),       -- Amount for THIS subscription in the document
    item_data JSON,             -- The specific item(s) that belong to this subscription
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(document_id, subscription_id)
);
```

### Relationships

**OfficeGuyDocument (Many-to-Many)**:
```php
public function subscriptions()
{
    return $this->belongsToMany(Subscription::class, 'document_subscription')
        ->withPivot('amount', 'item_data')
        ->withTimestamps();
}
```

**Subscription (Many-to-Many)**:
```php
public function documentsMany()
{
    return $this->belongsToMany(OfficeGuyDocument::class, 'document_subscription')
        ->withPivot('amount', 'item_data')
        ->withTimestamps();
}
```

## 🔍 Usage Examples

### Check Documents for a Subscription
```php
use OfficeGuy\LaravelSumitGateway\Models\Subscription;

$subscription = Subscription::find(17); // "נאא"

// Get all documents (many-to-many)
$documents = $subscription->documentsMany;

foreach ($documents as $doc) {
    echo "Document #{$doc->document_number}: {$doc->pivot->amount}₪\n";
}
```

### Check Subscriptions in a Document
```php
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

$document = OfficeGuyDocument::where('document_number', '40000')->first();

// Get all subscriptions in this document
$subscriptions = $document->subscriptions;

$total = $subscriptions->sum('pivot.amount');
echo "Total: {$total}₪\n";

foreach ($subscriptions as $sub) {
    echo "- {$sub->name}: {$sub->pivot->amount}₪\n";
    // View item details
    $items = json_decode($sub->pivot->item_data, true);
}
```

## 🚀 Background Queue Processing

The system uses Laravel queues for **non-blocking** synchronization:

```php
use OfficeGuy\LaravelSumitGateway\Jobs\SyncDocumentsJob;

// Dispatch to queue
SyncDocumentsJob::dispatch($userId, $days, $force);

// Job configuration:
// - Tries: 3 attempts with 60s backoff
// - Timeout: 10 minutes
// - Queue: 'default'
```

**Run queue worker**:
```bash
php artisan queue:work --queue=default
```

**Monitor with Horizon** (if installed):
```bash
php artisan horizon
# Visit: /horizon
```

## 📈 Monitoring & Logging

### Logs

All sync operations are logged:

```bash
# Successful sync
[2025-11-30 03:00:15] info: SUMIT documents auto-sync completed successfully

# Webhook trigger
[2025-11-30 14:23:45] info: Document sync triggered by webhook {"webhook_type":"RecurringCharge"}

# Job processing
[2025-11-30 14:23:46] info: Starting SUMIT documents sync job {"user_id":123,"days":7}

# Errors
[2025-11-30 15:10:22] error: SUMIT documents sync job failed {"error":"API timeout"}
```

### Check Sync Status

```bash
# View recent logs
tail -100 storage/logs/laravel-$(date +%Y-%m-%d).log | grep "SUMIT"

# Check scheduled tasks
php artisan schedule:list | grep sumit

# Verify command exists
php artisan list | grep sumit:sync
```

## 🛠️ Troubleshooting

### Issue: Missing Subscriptions

**Problem**: Only seeing 9 subscriptions instead of 18.

**Solution**: Ensure `includeInactive = true` when syncing:
```php
SubscriptionService::syncFromSumit($user, true); // ✅ Include ALL statuses
```

### Issue: Documents Not Matching

**Problem**: Documents exist in SUMIT but not mapping to subscriptions.

**Debugging**:
```bash
# Run in dry-run mode to see what would be synced
php artisan sumit:sync-all-documents --dry-run --user-id=123

# Check matching logic in DocumentService.php:
# - Lines 454-466: ExternalReference matching
# - Lines 468-486: Description matching
# - Lines 488-498: Item name matching
# - Lines 500-522: Amount matching
```

### Issue: Scheduler Not Running

**Problem**: Daily sync not running at 3:00 AM.

**Solution**:
```bash
# Add to crontab:
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1

# Test schedule manually:
php artisan schedule:run

# Verify schedule:
php artisan schedule:list
```

## 🔐 Security Considerations

1. **Webhook Verification**: All webhooks from SUMIT are verified via signature
2. **Queue Authentication**: Jobs run with proper user context
3. **Rate Limiting**: Scheduler prevents overlapping runs (2-hour timeout)
4. **Error Handling**: Failed jobs retry 3 times with exponential backoff

## 📝 Version History

### v1.5.0 (2025-11-30)
- ✨ Initial release of auto-sync system
- ✨ Many-to-many Document ↔ Subscriptions relationship
- ✨ Intelligent 4-method subscription matching
- ✨ Daily scheduler (3:00 AM)
- ✨ Webhook-triggered sync
- ✨ Background queue processing
- ✨ Comprehensive logging and monitoring

## 🤝 Contributing

When extending this system:

1. **Add new matching methods** in `DocumentService::syncForSubscription()`
2. **Add new webhook types** in `DocumentSyncListener::handleWebhook()`
3. **Update tests** to cover edge cases
4. **Document changes** in this file

## 📞 Support

For issues or questions:
- Check logs: `storage/logs/laravel-*.log`
- Run dry-run: `php artisan sumit:sync-all-documents --dry-run`
- Review code: `src/Services/DocumentService.php`, `src/Console/Commands/SyncAllDocumentsCommand.php`
