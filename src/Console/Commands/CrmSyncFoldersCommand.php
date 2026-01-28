<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Console\Commands;

use Illuminate\Console\Command;
use OfficeGuy\LaravelSumitGateway\Models\CrmFolder;
use OfficeGuy\LaravelSumitGateway\Services\CrmSchemaService;

/**
 * CRM Sync Folders Command
 *
 * Syncs CRM folders and their field definitions from SUMIT API to local database.
 * This creates the structure for CRM entities (Contacts, Leads, Companies, Deals, etc.)
 *
 * Usage:
 * - Manual full sync: php artisan crm:sync-folders
 * - Specific folder: php artisan crm:sync-folders --folder-id=1
 * - Dry run: php artisan crm:sync-folders --dry-run
 */
class CrmSyncFoldersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:sync-folders
                            {--folder-id= : Sync only specific folder ID}
                            {--dry-run : Show what would be synced without saving}
                            {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync CRM folders and field definitions from SUMIT API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Starting CRM Folders Sync from SUMIT...');
        $this->newLine();

        $folderId = $this->option('folder-id') ? (int) $this->option('folder-id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        // If specific folder ID is provided, sync only that folder
        if ($folderId) {
            return $this->syncSingleFolder($folderId, $dryRun);
        }

        // Otherwise, sync all folders
        return $this->syncAllFolders($dryRun, $force);
    }

    /**
     * Sync a single folder by ID
     *
     * @param  int  $folderId  SUMIT folder ID
     * @param  bool  $dryRun  Dry run mode
     * @return int Command exit code
     */
    protected function syncSingleFolder(int $folderId, bool $dryRun): int
    {
        $this->info("📁 Syncing folder ID: {$folderId}");
        $this->newLine();

        if ($dryRun) {
            $this->line('   [DRY RUN] Would fetch folder from SUMIT API');
            $this->line('   [DRY RUN] Would create/update folder in database');
            $this->line('   [DRY RUN] Would sync folder fields');
            $this->newLine();
            $this->info('✅ Dry run completed');

            return Command::SUCCESS;
        }

        try {
            // First, get the folder name from listFolders
            $listResult = CrmSchemaService::listFolders();

            if (! $listResult['success']) {
                $this->error('✗ Failed to list folders: ' . $listResult['error']);

                return Command::FAILURE;
            }

            $folderData = collect($listResult['folders'])->firstWhere(fn (array $folder): bool => ($folder['FolderID'] ?? $folder['ID'] ?? null) == $folderId);

            if (! $folderData) {
                $this->error("✗ Folder ID {$folderId} not found in SUMIT");

                return Command::FAILURE;
            }

            $folderName = $folderData['Name'] ?? 'Unknown';

            // Now sync the folder
            $result = CrmSchemaService::syncFolderSchema($folderId, $folderName);

            if (! $result['success']) {
                $this->error('✗ Failed to sync folder: ' . $result['error']);

                return Command::FAILURE;
            }

            $folder = $result['folder'];
            $fieldsSynced = $result['fields_synced'];

            $this->info("✓ Synced folder: {$folder->name} (ID: {$folder->id})");
            $this->line("  • Entity type: {$folder->entity_type}");
            $this->line("  • Fields synced: {$fieldsSynced}");
            $this->line('  • System folder: ' . ($folder->is_system ? 'Yes' : 'No'));
            $this->line('  • Active: ' . ($folder->is_active ? 'Yes' : 'No'));

            $this->newLine();
            $this->info('✅ Folder sync completed successfully!');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('✗ Exception: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Sync all folders from SUMIT
     *
     * @param  bool  $dryRun  Dry run mode
     * @param  bool  $force  Force sync even if recently synced
     * @return int Command exit code
     */
    protected function syncAllFolders(bool $dryRun, bool $force): int
    {
        $this->info('📋 Fetching folders from SUMIT API...');
        $this->newLine();

        try {
            // Get all folders from SUMIT
            $result = CrmSchemaService::listFolders();

            if (! $result['success']) {
                $this->error('✗ Failed to list folders: ' . $result['error']);

                return Command::FAILURE;
            }

            $folders = $result['folders'];
            $foldersCount = count($folders);

            if ($foldersCount === 0) {
                $this->warn('⚠️  No folders found in SUMIT');

                return Command::SUCCESS;
            }

            $this->info("Found {$foldersCount} folders in SUMIT");
            $this->newLine();

            if ($dryRun) {
                $this->table(
                    ['Folder ID', 'Name', 'Entity Type', 'System'],
                    collect($folders)->map(fn ($f): array => [
                        $f['FolderID'] ?? $f['ID'] ?? 'N/A',
                        $f['Name'] ?? 'Unknown',
                        $f['EntityType'] ?? 'Unknown',
                        ($f['IsSystem'] ?? false) ? 'Yes' : 'No',
                    ])->toArray()
                );

                $this->newLine();
                $this->info('✅ Dry run completed - no changes were saved');

                return Command::SUCCESS;
            }

            // Sync each folder
            $progressBar = $this->output->createProgressBar($foldersCount);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

            $synced = 0;
            $totalFields = 0;
            $errors = [];

            foreach ($folders as $folderData) {
                // Support both 'FolderID' and 'ID' field names
                $folderId = $folderData['FolderID'] ?? $folderData['ID'] ?? null;
                $folderName = $folderData['Name'] ?? 'Unknown';

                if (! $folderId) {
                    $errors[] = "Folder '{$folderName}' has no ID";

                    continue;
                }

                $progressBar->setMessage("Syncing: {$folderName}");
                $progressBar->advance();

                try {
                    $syncResult = CrmSchemaService::syncFolderSchema($folderId, $folderName);

                    if ($syncResult['success']) {
                        $synced++;
                        $totalFields += $syncResult['fields_synced'] ?? 0;
                    } else {
                        $errors[] = "Folder '{$folderName}': " . $syncResult['error'];
                    }

                } catch (\Throwable $e) {
                    $errors[] = "Folder '{$folderName}': " . $e->getMessage();
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display summary
            $this->generateSummary($synced, $totalFields, $errors);

            return $errors === [] ? Command::SUCCESS : Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error('✗ Exception: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Generate and display summary report
     *
     * @param  int  $synced  Number of folders synced
     * @param  int  $totalFields  Total fields synced across all folders
     * @param  array  $errors  Array of error messages
     */
    protected function generateSummary(int $synced, int $totalFields, array $errors): void
    {
        // Get database statistics
        $totalFolders = CrmFolder::count();
        $activeFolders = CrmFolder::active()->count();
        $systemFolders = CrmFolder::where('is_system', true)->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Folders Synced (This Run)', $synced],
                ['Fields Synced (This Run)', $totalFields],
                ['Total Folders in Database', $totalFolders],
                ['Active Folders', $activeFolders],
                ['System Folders', $systemFolders],
                ['Errors', count($errors)],
            ]
        );

        if ($errors !== []) {
            $this->newLine();
            $this->error('⚠️  Errors encountered:');
            foreach ($errors as $error) {
                $this->line('   • ' . $error);
            }
        }

        $this->newLine();

        if ($errors === []) {
            $this->info('✅ All folders synced successfully!');
        } else {
            $this->warn('⚠️  Sync completed with ' . count($errors) . ' error(s)');
        }

        // Show folder breakdown by entity type
        $this->newLine();
        $this->info('📊 Folders by Entity Type:');
        $byType = CrmFolder::select('entity_type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('entity_type')
            ->get();

        foreach ($byType as $type) {
            $this->line("   • {$type->entity_type}: {$type->count}");
        }
    }
}
