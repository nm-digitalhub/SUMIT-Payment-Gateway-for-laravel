<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Models\CrmView;
use OfficeGuy\LaravelSumitGateway\Services\CrmViewService;

/**
 * CRM Sync Views Command
 *
 * Syncs CRM saved views/filters from SUMIT API to local database.
 * Views allow users to save custom filters, column configurations, and sorting preferences.
 *
 * Usage:
 * - Manual full sync: php artisan crm:sync-views
 * - Specific folder: php artisan crm:sync-views --folder-id=1
 * - Dry run: php artisan crm:sync-views --dry-run
 */
class CrmSyncViewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:sync-views
                            {--folder-id= : Sync views for specific folder ID (local DB ID)}
                            {--sumit-folder-id= : Sync views for specific SUMIT folder ID}
                            {--dry-run : Show what would be synced without saving}
                            {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync CRM views/filters from SUMIT API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting CRM Views Sync from SUMIT...');
        $this->newLine();

        $folderId = $this->option('folder-id') ? (int) $this->option('folder-id') : null;
        $sumitFolderId = $this->option('sumit-folder-id') ? (int) $this->option('sumit-folder-id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // If specific folder ID is provided, sync only that folder's views
        if ($folderId) {
            $folder = CrmFolder::find($folderId);
            if (!$folder || !$folder->sumit_folder_id) {
                $this->error('✗ Folder not found or not synced with SUMIT');
                return Command::FAILURE;
            }
            return $this->syncFolderViews($folder, $dryRun);
        }

        // If SUMIT folder ID is provided, sync only that folder's views
        if ($sumitFolderId) {
            $folder = CrmFolder::where('sumit_folder_id', $sumitFolderId)->first();
            if (!$folder) {
                $this->error('✗ Folder with SUMIT ID ' . $sumitFolderId . ' not found locally');
                return Command::FAILURE;
            }
            return $this->syncFolderViews($folder, $dryRun);
        }

        // Otherwise, sync views for all folders
        return $this->syncAllFoldersViews($dryRun, $force);
    }

    /**
     * Sync views for a single folder
     *
     * @param CrmFolder $folder Folder to sync views for
     * @param bool $dryRun Dry run mode
     * @return int Command exit code
     */
    protected function syncFolderViews(CrmFolder $folder, bool $dryRun): int
    {
        $this->info("📁 Syncing views for folder: {$folder->name} (SUMIT ID: {$folder->sumit_folder_id})");
        $this->newLine();

        if ($dryRun) {
            $this->line('   [DRY RUN] Would fetch views from SUMIT API');
            $this->line('   [DRY RUN] Would create/update views in database');
            $this->newLine();
            $this->info('✅ Dry run completed');
            return Command::SUCCESS;
        }

        try {
            // Get views from SUMIT
            $result = CrmViewService::listViews($folder->sumit_folder_id);

            if (!$result['success']) {
                $this->error('✗ Failed to list views: ' . $result['error']);
                return Command::FAILURE;
            }

            $views = $result['views'];
            $viewsCount = count($views);

            if ($viewsCount === 0) {
                $this->warn("⚠️  No views found for folder: {$folder->name}");
                return Command::SUCCESS;
            }

            $this->info("Found {$viewsCount} views");
            $this->newLine();

            // Sync each view
            $synced = 0;
            $errors = [];

            foreach ($views as $viewData) {
                $viewId = $viewData['ID'] ?? null;
                $viewName = $viewData['Name'] ?? 'Unknown View';

                if (!$viewId) {
                    $errors[] = "View '{$viewName}' has no ID";
                    continue;
                }

                try {
                    $syncResult = CrmViewService::syncViewFromSumit(
                        $folder->sumit_folder_id,
                        $viewId,
                        $viewName
                    );

                    if ($syncResult['success']) {
                        $synced++;
                        $this->line("  ✓ {$viewName}");
                    } else {
                        $errors[] = "View '{$viewName}': " . $syncResult['error'];
                        $this->line("  ✗ {$viewName}: " . $syncResult['error']);
                    }

                } catch (\Throwable $e) {
                    $errors[] = "View '{$viewName}': " . $e->getMessage();
                    $this->line("  ✗ {$viewName}: " . $e->getMessage());
                }
            }

            $this->newLine();

            // Display summary
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Folder', $folder->name],
                    ['SUMIT Folder ID', $folder->sumit_folder_id],
                    ['Views Synced', $synced],
                    ['Errors', count($errors)],
                ]
            );

            if (!empty($errors)) {
                $this->newLine();
                $this->error('⚠️  Errors encountered:');
                foreach ($errors as $error) {
                    $this->line('   • ' . $error);
                }
            }

            $this->newLine();

            if (empty($errors)) {
                $this->info('✅ All views synced successfully!');
                return Command::SUCCESS;
            } else {
                $this->warn('⚠️  Sync completed with ' . count($errors) . ' error(s)');
                return Command::FAILURE;
            }

        } catch (\Throwable $e) {
            $this->error('✗ Exception: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Sync views for all folders from SUMIT
     *
     * @param bool $dryRun Dry run mode
     * @param bool $force Force sync even if recently synced
     * @return int Command exit code
     */
    protected function syncAllFoldersViews(bool $dryRun, bool $force): int
    {
        $this->info('📋 Syncing views for all folders...');
        $this->newLine();

        try {
            // Get all folders that are synced with SUMIT
            $folders = CrmFolder::whereNotNull('sumit_folder_id')->get();

            if ($folders->isEmpty()) {
                $this->warn('⚠️  No synced folders found. Please run crm:sync-folders first.');
                return Command::FAILURE;
            }

            $foldersCount = $folders->count();
            $this->info("Found {$foldersCount} synced folders");
            $this->newLine();

            if ($dryRun) {
                $this->table(
                    ['Folder ID', 'SUMIT ID', 'Name', 'Entity Type'],
                    $folders->map(fn($f) => [
                        $f->id,
                        $f->sumit_folder_id,
                        $f->name,
                        $f->entity_type,
                    ])->toArray()
                );

                $this->newLine();
                $this->info('✅ Dry run completed - no changes were saved');
                return Command::SUCCESS;
            }

            // Sync views for each folder
            $progressBar = $this->output->createProgressBar($foldersCount);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

            $foldersProcessed = 0;
            $totalViews = 0;
            $errors = [];

            foreach ($folders as $folder) {
                $progressBar->setMessage("Syncing: {$folder->name}");
                $progressBar->advance();

                try {
                    $result = CrmViewService::syncAllViews($folder->sumit_folder_id);

                    if ($result['success']) {
                        $foldersProcessed++;
                        $totalViews += $result['synced_count'];
                    } else {
                        $errors[] = "Folder '{$folder->name}': " . $result['error'];
                    }

                } catch (\Throwable $e) {
                    $errors[] = "Folder '{$folder->name}': " . $e->getMessage();
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display summary
            $this->generateSummary($foldersProcessed, $totalViews, $errors);

            return empty($errors) ? Command::SUCCESS : Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error('✗ Exception: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Generate and display summary report
     *
     * @param int $foldersProcessed Number of folders processed
     * @param int $totalViews Total views synced across all folders
     * @param array $errors Array of error messages
     * @return void
     */
    protected function generateSummary(int $foldersProcessed, int $totalViews, array $errors): void
    {
        // Get database statistics
        $totalViewsInDb = CrmView::count();
        $publicViews = CrmView::where('is_public', true)->count();
        $defaultViews = CrmView::where('is_default', true)->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Folders Processed (This Run)', $foldersProcessed],
                ['Views Synced (This Run)', $totalViews],
                ['Total Views in Database', $totalViewsInDb],
                ['Public Views', $publicViews],
                ['Default Views', $defaultViews],
                ['Errors', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->error('⚠️  Errors encountered:');
            foreach ($errors as $error) {
                $this->line('   • ' . $error);
            }
        }

        $this->newLine();

        if (empty($errors)) {
            $this->info('✅ All views synced successfully!');
        } else {
            $this->warn('⚠️  Sync completed with ' . count($errors) . ' error(s)');
        }

        // Show views breakdown by folder
        $this->newLine();
        $this->info('📊 Views by Folder:');
        $byFolder = CrmView::join('officeguy_crm_folders', 'officeguy_crm_views.crm_folder_id', '=', 'officeguy_crm_folders.id')
            ->select('officeguy_crm_folders.name as folder_name')
            ->selectRaw('COUNT(officeguy_crm_views.id) as count')
            ->groupBy('officeguy_crm_folders.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($byFolder->isEmpty()) {
            $this->line('   No views found');
        } else {
            foreach ($byFolder as $item) {
                $this->line("   • {$item->folder_name}: {$item->count}");
            }

            if ($byFolder->count() === 10) {
                $this->line('   ... (showing top 10)');
            }
        }
    }
}
