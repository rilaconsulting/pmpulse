<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\UtilityDashboardController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BenchmarkUtilities extends Command
{
    protected $signature = 'benchmark:utilities
                            {--iterations=5 : Number of iterations to run}
                            {--warmup=1 : Number of warmup iterations (not counted)}
                            {--detailed : Show per-iteration details}';

    protected $description = 'Benchmark the utilities dashboard endpoint performance';

    public function handle(): int
    {
        $iterations = (int) $this->option('iterations');
        $warmup = (int) $this->option('warmup');
        $detailed = $this->option('detailed');

        $this->info('Utility Dashboard Performance Benchmark');
        $this->info('=======================================');
        $this->newLine();

        // Get controller instance
        $controller = app(UtilityDashboardController::class);
        $request = new Request;

        // Warmup iterations
        if ($warmup > 0) {
            $this->info("Running {$warmup} warmup iteration(s)...");
            for ($i = 0; $i < $warmup; $i++) {
                $controller->index($request);
            }
            $this->info('Warmup complete.');
            $this->newLine();
        }

        $times = [];
        $queryCounts = [];
        $memoryUsages = [];

        $this->info("Running {$iterations} benchmark iteration(s)...");
        $this->newLine();

        for ($i = 0; $i < $iterations; $i++) {
            // Reset query log
            DB::flushQueryLog();
            DB::enableQueryLog();

            // Measure memory before
            $memoryBefore = memory_get_usage(true);

            // Measure time
            $start = hrtime(true);
            $controller->index($request);
            $elapsed = (hrtime(true) - $start) / 1_000_000; // Convert to ms

            // Capture metrics
            $queryCount = count(DB::getQueryLog());
            $memoryAfter = memory_get_usage(true);
            $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

            $times[] = $elapsed;
            $queryCounts[] = $queryCount;
            $memoryUsages[] = $memoryUsed;

            if ($detailed) {
                $this->line(sprintf(
                    '  Iteration %d: %s ms | %d queries | %.2f MB',
                    $i + 1,
                    number_format($elapsed, 2),
                    $queryCount,
                    $memoryUsed
                ));
            }
        }

        DB::disableQueryLog();

        // Calculate statistics
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        $avgQueries = array_sum($queryCounts) / count($queryCounts);
        $avgMemory = array_sum($memoryUsages) / count($memoryUsages);

        // Display results
        $this->newLine();
        $this->info('Results:');
        $this->info('--------');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Average Time', number_format($avgTime, 2).' ms'],
                ['Min Time', number_format($minTime, 2).' ms'],
                ['Max Time', number_format($maxTime, 2).' ms'],
                ['Query Count', number_format($avgQueries, 0)],
                ['Memory Usage', number_format($avgMemory, 2).' MB'],
            ]
        );

        // Performance assessment
        $this->newLine();
        if ($avgTime < 500) {
            $this->info('Performance: GOOD (under 500ms target)');
        } elseif ($avgTime < 1000) {
            $this->warn('Performance: ACCEPTABLE (under 1000ms)');
        } else {
            $this->error('Performance: NEEDS OPTIMIZATION (over 1000ms)');
        }

        if ($avgQueries > 100) {
            $this->warn("Query count is high ({$avgQueries}). Consider optimizing N+1 queries.");
        }

        return Command::SUCCESS;
    }
}
