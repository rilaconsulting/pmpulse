<?php

namespace App\Notifications;

use App\Models\AlertRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly AlertRule $rule,
        public readonly float $currentValue,
        public readonly string $message
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
        return (new MailMessage)
            ->subject('PMPulse Alert: ' . $this->rule->name)
            ->greeting('Hello!')
            ->line($this->message)
            ->line('This alert was triggered based on your configured rules.')
            ->action('View Dashboard', url('/dashboard'))
            ->line('Thank you for using PMPulse!')
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
            'rule_id' => $this->rule->id,
            'rule_name' => $this->rule->name,
            'metric' => $this->rule->metric,
            'current_value' => $this->currentValue,
            'threshold' => $this->rule->threshold,
        ];
    }
}
