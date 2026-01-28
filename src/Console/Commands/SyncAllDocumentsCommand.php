<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Models\Subscription;
use OfficeGuy\LaravelSumitGateway\Services\DocumentService;
use OfficeGuy\LaravelSumitGateway\Services\SubscriptionService;

/**
 * Sync All Documents Command
 *
 * Automatically syncs all invoices/documents from SUMIT to local database
 * with intelligent mapping to subscriptions (many-to-many).
 *
 * Usage:
 * - Daily: Scheduled automatically
 * - Manual: php artisan sumit:sync-all-documents
 * - After webhook: Triggered by DocumentReceived event
 */
class SyncAllDocumentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sumit:sync-all-documents
                            {--user-id= : Sync only for specific user ID}
                            {--days=1825 : Number of days to look back (default: 1825 = 5 years)}
                            {--force : Force full sync even if recently synced}
                            {--dry-run : Show what would be synced without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all documents (invoices) from SUMIT with intelligent subscription mapping';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting SUMIT Documents Auto-Sync...');
        $this->newLine();

        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // Step 1: Sync ALL subscriptions first (including inactive ones)
        $this->info('📋 Step 1/3: Syncing ALL subscriptions (including inactive)...');
        $this->syncAllSubscriptions($userId, $dryRun);

        // Step 2: Sync documents for each subscription
        $this->info('📄 Step 2/3: Syncing documents for all subscriptions...');
        $documentsCount = $this->syncDocumentsForSubscriptions($userId, $days, $force, $dryRun);

        // Step 3: Summary report
        $this->newLine();
        $this->info('✅ Step 3/3: Generating summary...');
        $this->generateSummary($documentsCount, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * Sync all subscriptions including inactive ones
     *
     * @param  int|null  $userId  Specific user ID or null for all users
     * @param  bool  $dryRun  Dry run mode
     * @return int Number of subscriptions synced
     */
    protected function syncAllSubscriptions(?int $userId, bool $dryRun): int
    {
        $totalSynced = 0;

        // Get all users with SUMIT customer ID
        $query = \App\Models\User::whereNotNull('sumit_customer_id');

        if ($userId) {
            $query->where('id', $userId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('   ⚠️  No users with SUMIT customer ID found');

            return 0;
        }

        $this->info("   Found {$users->count()} users with SUMIT customer ID");

        foreach ($users as $user) {
            $this->line("   • User #{$user->id} ({$user->email})...");

            if ($dryRun) {
                $this->line('     [DRY RUN] Would sync subscriptions');

                continue;
            }

            try {
                // CRITICAL: includeInactive = true to get ALL subscriptions
                $count = SubscriptionService::syncFromSumit($user, true);
                $totalSynced += $count;
                $this->line("     ✓ Synced {$count} subscriptions");
            } catch (\Throwable $e) {
                $this->error("     ✗ Error: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("   Total subscriptions synced: {$totalSynced}");
        $this->newLine();

        return $totalSynced;
    }

    /**
     * Sync ALL documents for all customers
     *
     * This syncs ALL documents from SUMIT, not just subscription-related ones.
     * Includes invoices, credit notes, eSIM purchases, and any other document type.
     *
     * @param  int|null  $userId  Specific user ID or null for all users
     * @param  int  $days  Number of days to look back
     * @param  bool  $force  Force sync even if recently synced
     * @param  bool  $dryRun  Dry run mode
     * @return int Number of documents synced
     */
    protected function syncDocumentsForSubscriptions(?int $userId, int $days, bool $force, bool $dryRun): int
    {
        $totalDocuments = 0;

        // Get all users with SUMIT customer ID
        $query = \App\Models\User::whereNotNull('sumit_customer_id');

        if ($userId) {
            $query->where('id', $userId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('   ⚠️  No users with SUMIT customer ID found');

            return 0;
        }

        $this->info("   Found {$users->count()} users with SUMIT customer ID");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

        foreach ($users as $user) {
            $progressBar->setMessage("User #{$user->id}: {$user->email}");
            $progressBar->advance();

            if ($dryRun) {
                continue;
            }

            try {
                $dateFrom = now()->subDays($days);

                // Sync ALL documents for this customer (not just subscription-related)
                $count = DocumentService::syncAllForCustomer(
                    (int) $user->sumit_customer_id,
                    $dateFrom
                );
                $totalDocuments += $count;
            } catch (\Throwable $e) {
                // Log error but continue with next user
                \Log::error("Failed to sync documents for user #{$user->id}: {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("   Total documents synced: {$totalDocuments}");
        $this->newLine();

        return $totalDocuments;
    }

    /**
     * Generate and display summary report
     *
     * @param  int  $documentsCount  Number of documents synced
     * @param  bool  $dryRun  Dry run mode
     */
    protected function generateSummary(int $documentsCount, bool $dryRun): void
    {
        if ($dryRun) {
            $this->warn('📊 Dry run completed - no changes were saved');
            $this->newLine();

            return;
        }

        // Get statistics
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', Subscription::STATUS_ACTIVE)->count();
        $totalDocuments = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument::count();
        $paidDocuments = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument::where('is_closed', true)->count();

        // Get documents with multiple subscriptions (many-to-many)
        $multipleSubsDocs = \DB::table('document_subscription')
            ->select('document_id')
            ->groupBy('document_id')
            ->havingRaw('COUNT(subscription_id) > 1')
            ->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Subscriptions', $totalSubscriptions],
                ['Active Subscriptions', $activeSubscriptions],
                ['Total Documents', $totalDocuments],
                ['Paid Documents', $paidDocuments],
                ['Documents with Multiple Subscriptions', $multipleSubsDocs],
                ['Documents Synced (This Run)', $documentsCount],
            ]
        );

        $this->newLine();
        $this->info('✅ Auto-sync completed successfully!');

        if ($multipleSubsDocs > 0) {
            $this->newLine();
            $this->comment("💡 Found {$multipleSubsDocs} consolidated invoices (multiple subscriptions per document)");
        }
    }
}
