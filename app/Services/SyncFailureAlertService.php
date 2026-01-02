<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppfolioConnection;
use App\Models\FeatureFlag;
use App\Models\SyncFailureAlert;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\SyncFailureNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Service for managing sync failure alerts.
 *
 * This service tracks consecutive sync failures and sends notifications
 * to admins when the failure threshold is exceeded.
 */
class SyncFailureAlertService
{
    /**
     * Default number of consecutive failures before alerting.
     */
    public const DEFAULT_FAILURE_THRESHOLD = 3;

    /**
     * Default minimum minutes between alert emails.
     */
    public const DEFAULT_ALERT_COOLDOWN_MINUTES = 60;

    /**
     * Handle a completed sync run and evaluate if alerts should be sent.
     */
    public function handleSyncCompleted(SyncRun $syncRun): void
    {
        $connection = $syncRun->connection;

        if (! $connection) {
            Log::warning('SyncRun has no associated connection', ['sync_run_id' => $syncRun->id]);

            return;
        }

        if ($syncRun->status === 'completed') {
            $this->handleSuccess($connection);
        } elseif ($syncRun->status === 'failed') {
            $this->handleFailure($connection, $syncRun);
        }
    }

    /**
     * Handle a successful sync.
     */
    private function handleSuccess(AppfolioConnection $connection): void
    {
        $alert = SyncFailureAlert::forConnection($connection);

        if ($alert->consecutive_failures > 0) {
            Log::info('Sync succeeded, resetting failure count', [
                'connection_id' => $connection->id,
                'previous_failures' => $alert->consecutive_failures,
            ]);

            $alert->resetFailures();
        }
    }

    /**
     * Handle a failed sync.
     */
    private function handleFailure(AppfolioConnection $connection, SyncRun $syncRun): void
    {
        $alert = SyncFailureAlert::forConnection($connection);

        // Record the failure with details
        $alert->recordFailure([
            'sync_run_id' => $syncRun->id,
            'error' => $syncRun->error_summary ?? 'Unknown error',
            'errors_count' => $syncRun->errors_count,
            'mode' => $syncRun->mode,
        ]);

        Log::info('Sync failure recorded', [
            'connection_id' => $connection->id,
            'consecutive_failures' => $alert->consecutive_failures,
        ]);

        // Check if we should send an alert
        $this->evaluateAndSendAlert($alert, $syncRun);
    }

    /**
     * Evaluate whether to send an alert and send if appropriate.
     */
    private function evaluateAndSendAlert(SyncFailureAlert $alert, SyncRun $syncRun): void
    {
        // Check if notifications are enabled
        if (! FeatureFlag::isEnabled('notifications')) {
            Log::info('Notifications disabled, skipping sync failure alert');

            return;
        }

        $threshold = $this->getFailureThreshold();
        $cooldownMinutes = $this->getAlertCooldownMinutes();

        // Check if we've reached the failure threshold
        if ($alert->consecutive_failures < $threshold) {
            Log::debug('Failure threshold not reached', [
                'current' => $alert->consecutive_failures,
                'threshold' => $threshold,
            ]);

            return;
        }

        // Check rate limiting
        if (! $alert->shouldSendAlert($cooldownMinutes)) {
            Log::info('Alert rate limited, skipping notification', [
                'last_alert' => $alert->last_alert_sent_at?->toIso8601String(),
                'cooldown_minutes' => $cooldownMinutes,
            ]);

            return;
        }

        // Send the alert
        $this->sendAlert($alert, $syncRun);
    }

    /**
     * Send the failure alert notification.
     */
    private function sendAlert(SyncFailureAlert $alert, SyncRun $syncRun): void
    {
        $recipients = $this->getAlertRecipients();

        if (empty($recipients)) {
            Log::warning('No recipients configured for sync failure alerts');

            return;
        }

        Log::info('Sending sync failure alert', [
            'connection_id' => $alert->appfolio_connection_id,
            'consecutive_failures' => $alert->consecutive_failures,
            'recipients' => $recipients,
        ]);

        // Send notification
        Notification::route('mail', $recipients)
            ->notify(new SyncFailureNotification($alert, $syncRun));

        // Mark alert as sent
        $alert->markAlertSent();
    }

    /**
     * Get the configured failure threshold.
     */
    private function getFailureThreshold(): int
    {
        return (int) config('appfolio.alerts.failure_threshold', self::DEFAULT_FAILURE_THRESHOLD);
    }

    /**
     * Get the configured alert cooldown in minutes.
     */
    private function getAlertCooldownMinutes(): int
    {
        return (int) config('appfolio.alerts.cooldown_minutes', self::DEFAULT_ALERT_COOLDOWN_MINUTES);
    }

    /**
     * Get alert recipients.
     *
     * This returns admin users' email addresses.
     */
    private function getAlertRecipients(): array
    {
        // Get from config if set
        $configRecipients = config('appfolio.alerts.recipients');
        if (! empty($configRecipients)) {
            // Trim whitespace from each recipient in case of spaces after commas
            $recipients = is_array($configRecipients) ? $configRecipients : [$configRecipients];

            return array_map('trim', $recipients);
        }

        // Fall back to all users (single-tenant app, all users are effectively admins)
        // TODO: When user roles are implemented, filter by admin role here
        return User::query()
            ->pluck('email')
            ->toArray();
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(SyncFailureAlert $alert, User $user): void
    {
        $alert->acknowledge($user);

        Log::info('Sync failure alert acknowledged', [
            'alert_id' => $alert->id,
            'connection_id' => $alert->appfolio_connection_id,
            'acknowledged_by' => $user->id,
        ]);
    }

    /**
     * Get the current alert status for a connection.
     */
    public function getAlertStatus(AppfolioConnection $connection): array
    {
        $alert = SyncFailureAlert::where('appfolio_connection_id', $connection->id)->first();

        if (! $alert) {
            return [
                'has_alert' => false,
                'consecutive_failures' => 0,
                'is_acknowledged' => false,
            ];
        }

        return [
            'has_alert' => $alert->isActive(),
            'consecutive_failures' => $alert->consecutive_failures,
            'is_acknowledged' => $alert->acknowledged_at !== null,
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'last_alert_sent_at' => $alert->last_alert_sent_at?->toIso8601String(),
            'failure_details' => $alert->failure_details,
        ];
    }

    /**
     * Get all active (unacknowledged) alerts.
     */
    public function getActiveAlerts(): array
    {
        return SyncFailureAlert::where('consecutive_failures', '>', 0)
            ->whereNull('acknowledged_at')
            ->with('connection')
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'connection_id' => $alert->appfolio_connection_id,
                    'connection_name' => $alert->connection->name ?? 'Unknown',
                    'consecutive_failures' => $alert->consecutive_failures,
                    'last_alert_sent_at' => $alert->last_alert_sent_at?->toIso8601String(),
                    'failure_details' => $alert->failure_details,
                ];
            })
            ->toArray();
    }
}
