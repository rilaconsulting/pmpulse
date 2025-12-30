<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Services\AppfolioClient;
use App\Services\IngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAppfolioResourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly SyncRun $syncRun
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AppfolioClient $appfolioClient, IngestionService $ingestionService): void
    {
        Log::info('Starting AppFolio sync job', [
            'sync_run_id' => $this->syncRun->id,
            'mode' => $this->syncRun->mode,
        ]);

        try {
            // Check if AppFolio is configured
            if (! $appfolioClient->isConfigured()) {
                throw new \RuntimeException('AppFolio connection is not configured');
            }

            // Start the sync
            $ingestionService->startSync($this->syncRun);

            // Process all resources
            $ingestionService->processAll();

            // Complete the sync
            $ingestionService->completeSync();

            Log::info('AppFolio sync job completed', [
                'sync_run_id' => $this->syncRun->id,
                'processed' => $ingestionService->getProcessedCount(),
                'errors' => $ingestionService->getErrorCount(),
            ]);

        } catch (\Exception $e) {
            Log::error('AppFolio sync job failed', [
                'sync_run_id' => $this->syncRun->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $ingestionService->failSync($e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AppFolio sync job permanently failed', [
            'sync_run_id' => $this->syncRun->id,
            'error' => $exception->getMessage(),
        ]);

        $this->syncRun->markAsFailed($exception->getMessage());
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'sync',
            'sync-run:'.$this->syncRun->id,
            'mode:'.$this->syncRun->mode,
        ];
    }
}
