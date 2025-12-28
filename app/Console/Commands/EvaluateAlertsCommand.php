<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class EvaluateAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'alerts:evaluate';

    /**
     * The console command description.
     */
    protected $description = 'Evaluate alert rules and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $this->info('Evaluating alert rules...');

        $notificationService->evaluateAlerts();

        $this->info('Alert evaluation completed.');

        return self::SUCCESS;
    }
}
