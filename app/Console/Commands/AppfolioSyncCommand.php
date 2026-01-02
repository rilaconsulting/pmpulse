<?php

namespace App\Console\Commands;

use App\Jobs\SyncAppfolioResourceJob;
use App\Models\SyncRun;
use App\Services\AppfolioClient;
use Illuminate\Console\Command;

class AppfolioSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appfolio:sync
                            {--mode=incremental : Sync mode (incremental or full)}
                            {--force : Force sync even if another sync is running}';

    /**
     * The console command description.
     */
    protected $description = 'Sync data from AppFolio API';

    /**
     * Execute the console command.
     */
    public function handle(AppfolioClient $client): int
    {
        $mode = $this->option('mode');

        if (! in_array($mode, ['incremental', 'full'])) {
            $this->error("Invalid mode: {$mode}. Must be 'incremental' or 'full'.");

            return self::FAILURE;
        }

        if (! $client->isConfigured()) {
            $this->error('AppFolio connection is not configured. Please configure it in the Admin panel.');

            return self::FAILURE;
        }

        // Check for running syncs
        if (! $this->option('force')) {
            $runningSync = SyncRun::where('status', 'running')
                ->where('started_at', '>=', now()->subHours(2))
                ->first();

            if ($runningSync) {
                $this->warn("Another sync is currently running (ID: {$runningSync->id}). Use --force to start anyway.");

                return self::FAILURE;
            }
        }

        // Create sync run
        $syncRun = SyncRun::create([
            'mode' => $mode,
            'status' => 'pending',
            'started_at' => now(),
            'metadata' => ['triggered_by' => 'command'],
        ]);

        $this->info("Created sync run ID: {$syncRun->id}");
        $this->info("Mode: {$mode}");

        // Dispatch the job
        SyncAppfolioResourceJob::dispatch($syncRun);

        $this->info('Sync job has been queued.');

        return self::SUCCESS;
    }
}
