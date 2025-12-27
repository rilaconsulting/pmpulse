<?php

namespace App\Console\Commands;

use App\Jobs\RefreshAnalyticsJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnalyticsRefreshCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'analytics:refresh
                            {--date= : Date to refresh analytics for (defaults to today)}
                            {--sync : Run synchronously instead of queuing}';

    /**
     * The console command description.
     */
    protected $description = 'Refresh analytics tables with current data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateInput = $this->option('date');
        $sync = $this->option('sync');

        $date = $dateInput ? Carbon::parse($dateInput) : now();

        $this->info("Refreshing analytics for: {$date->toDateString()}");

        if ($sync) {
            // Run synchronously
            $job = new RefreshAnalyticsJob($date);
            $job->handle(app(\App\Services\AnalyticsService::class));
            $this->info('Analytics refresh completed.');
        } else {
            // Queue the job
            RefreshAnalyticsJob::dispatch($date);
            $this->info('Analytics refresh job has been queued.');
        }

        return self::SUCCESS;
    }
}
