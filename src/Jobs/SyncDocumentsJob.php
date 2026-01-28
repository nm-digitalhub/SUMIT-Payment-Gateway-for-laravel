<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Sync Documents Job
 *
 * Background job to sync documents from SUMIT
 * Runs asynchronously in queue to avoid blocking requests
 */
class SyncDocumentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param  int|null  $userId  User ID to sync (null = all users)
     * @param  int  $days  Number of days to look back
     * @param  bool  $force  Force full sync
     */
    public function __construct(protected ?int $userId = null, protected int $days = 30, protected bool $force = false) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting SUMIT documents sync job', [
            'user_id' => $this->userId,
            'days' => $this->days,
            'force' => $this->force,
        ]);

        try {
            $options = [
                '--days' => $this->days,
            ];

            if ($this->userId) {
                $options['--user-id'] = $this->userId;
            }

            if ($this->force) {
                $options['--force'] = true;
            }

            // Run the sync command
            $exitCode = Artisan::call('sumit:sync-all-documents', $options);

            if ($exitCode === 0) {
                Log::info('SUMIT documents sync job completed successfully', [
                    'user_id' => $this->userId,
                    'exit_code' => $exitCode,
                ]);
            } else {
                Log::warning('SUMIT documents sync job completed with warnings', [
                    'user_id' => $this->userId,
                    'exit_code' => $exitCode,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('SUMIT documents sync job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SUMIT documents sync job failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // TODO: Send notification to admin
        // Notification::route('mail', config('officeguy.admin_email'))
        //     ->notify(new DocumentSyncFailedNotification($this->userId, $exception));
    }
}
