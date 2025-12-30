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

        try {
            $date = $dateInput ? Carbon::parse($dateInput) : now();
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            $this->error("Invalid date format: {$dateInput}");

            return self::FAILURE;
        }

        $this->info("Refreshing analytics for: {$date->toDateString()}");

        if ($sync) {
            // Run synchronously using Laravel's dispatchSync helper
            RefreshAnalyticsJob::dispatchSync($date);
            $this->info('Analytics refresh completed.');
        } else {
            // Queue the job
            RefreshAnalyticsJob::dispatch($date);
            $this->info('Analytics refresh job has been queued.');
        }

        return self::SUCCESS;
    }
}
