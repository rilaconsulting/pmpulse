<?php

namespace App\Notifications;

use App\Models\SyncFailureAlert;
use App\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SyncFailureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly SyncFailureAlert $alert,
        public readonly SyncRun $syncRun
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $failureCount = $this->alert->consecutive_failures;
        $connectionName = $this->alert->connection->name ?? 'AppFolio';

        $message = (new MailMessage)
            ->subject("PMPulse Alert: {$failureCount} Consecutive Sync Failures")
            ->error()
            ->greeting('Sync Failure Alert')
            ->line("Your {$connectionName} sync has failed {$failureCount} consecutive times.")
            ->line('');

        // Add last sync details
        if ($this->syncRun) {
            $message->line('**Last Sync Attempt:**')
                ->line("Mode: {$this->syncRun->mode}")
                ->line("Started: {$this->syncRun->started_at->format('M j, Y g:i A')}")
                ->line('');

            if ($this->syncRun->error_summary) {
                $message->line('**Error Summary:**')
                    ->line($this->syncRun->error_summary)
                    ->line('');
            }
        }

        // Add recent failure details
        $failureDetails = $this->alert->failure_details ?? [];
        if (count($failureDetails) > 0) {
            $message->line('**Recent Failures:**');
            foreach (array_slice($failureDetails, -3) as $failure) {
                $timestamp = $failure['timestamp'] ?? 'Unknown time';
                $details = $failure['details']['error'] ?? 'No details available';
                $message->line("- {$timestamp}: {$details}");
            }
            $message->line('');
        }

        return $message
            ->line('Please check your AppFolio connection settings and API credentials.')
            ->action('View Admin Dashboard', url('/admin'))
            ->line('You can acknowledge this alert in the admin dashboard to stop further notifications until the next failure.')
            ->salutation('Best regards, PMPulse');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'connection_id' => $this->alert->appfolio_connection_id,
            'consecutive_failures' => $this->alert->consecutive_failures,
            'sync_run_id' => $this->syncRun->id,
            'sync_run_status' => $this->syncRun->status,
        ];
    }
}
