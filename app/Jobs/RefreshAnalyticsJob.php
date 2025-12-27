<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly ?Carbon $date = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        $date = $this->date ?? now();

        Log::info('Starting analytics refresh', [
            'date' => $date->toDateString(),
        ]);

        try {
            $analyticsService->refreshForDate($date);

            Log::info('Analytics refresh completed', [
                'date' => $date->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics refresh failed', [
                'date' => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'analytics',
            'date:' . ($this->date ?? now())->toDateString(),
        ];
    }
}
