<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UtilityExpenseService;
use Illuminate\Console\Command;

class ReprocessUtilityExpensesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'utilities:reprocess
                            {--from= : Start date (Y-m-d) to filter expenses}
                            {--to= : End date (Y-m-d) to filter expenses}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess all utility expenses with current account mappings';

    public function __construct(
        private readonly UtilityExpenseService $utilityExpenseService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromDate = $this->option('from');
        $toDate = $this->option('to');

        // Build description of what will be reprocessed
        $dateRange = 'all time';
        if ($fromDate && $toDate) {
            $dateRange = "{$fromDate} to {$toDate}";
        } elseif ($fromDate) {
            $dateRange = "from {$fromDate}";
        } elseif ($toDate) {
            $dateRange = "up to {$toDate}";
        }

        $this->info("Reprocessing utility expenses for: {$dateRange}");
        $this->newLine();

        $this->warn('This will:');
        $this->line('  1. Delete all existing utility expenses in the date range');
        $this->line('  2. Recreate them from bill details using current account mappings');
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to continue?')) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->info('Starting reprocessing...');
        $this->newLine();

        $startTime = microtime(true);

        $stats = $this->utilityExpenseService->reprocessAllWithCurrentMappings($fromDate, $toDate);

        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('Reprocessing complete!');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Deleted', $stats['deleted'] ?? 0],
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Unmatched GL Accounts', $stats['unmatched']],
                ['Errors', $stats['errors']],
            ]
        );

        $this->newLine();
        $this->info("Duration: {$duration} seconds");

        if ($stats['errors'] > 0) {
            $this->newLine();
            $this->warn('Some records had errors. Check the logs for details.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
