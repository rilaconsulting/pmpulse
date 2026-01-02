<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncFailureAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'appfolio_connection_id',
        'consecutive_failures',
        'last_alert_sent_at',
        'acknowledged_at',
        'acknowledged_by',
        'failure_details',
    ];

    protected function casts(): array
    {
        return [
            'last_alert_sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'failure_details' => 'array',
        ];
    }

    /**
     * Get the connection this alert belongs to.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(AppfolioConnection::class, 'appfolio_connection_id');
    }

    /**
     * Get the user who acknowledged this alert.
     */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Record a sync failure.
     */
    public function recordFailure(array $details = []): void
    {
        $currentDetails = $this->failure_details ?? [];
        $currentDetails[] = [
            'timestamp' => now()->toIso8601String(),
            'details' => $details,
        ];

        // Keep only the last 10 failure details
        if (count($currentDetails) > 10) {
            $currentDetails = array_slice($currentDetails, -10);
        }

        $this->update([
            'consecutive_failures' => $this->consecutive_failures + 1,
            'failure_details' => $currentDetails,
            'acknowledged_at' => null, // Clear acknowledgment on new failure
            'acknowledged_by' => null,
        ]);
    }

    /**
     * Reset the failure count (called on successful sync).
     */
    public function resetFailures(): void
    {
        $this->update([
            'consecutive_failures' => 0,
            'failure_details' => null,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ]);
    }

    /**
     * Mark an alert as sent.
     */
    public function markAlertSent(): void
    {
        $this->update(['last_alert_sent_at' => now()]);
    }

    /**
     * Acknowledge this alert.
     */
    public function acknowledge(User $user): void
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);
    }

    /**
     * Check if an alert should be sent based on rate limiting.
     *
     * @param  int  $minMinutesBetweenAlerts  Minimum minutes between alerts
     */
    public function shouldSendAlert(int $minMinutesBetweenAlerts = 60): bool
    {
        if ($this->acknowledged_at !== null) {
            return false;
        }

        if ($this->last_alert_sent_at === null) {
            return true;
        }

        return $this->last_alert_sent_at->diffInMinutes(now()) >= $minMinutesBetweenAlerts;
    }

    /**
     * Check if this alert is active (unacknowledged with failures).
     */
    public function isActive(): bool
    {
        return $this->consecutive_failures > 0 && $this->acknowledged_at === null;
    }

    /**
     * Get or create an alert tracker for a connection.
     */
    public static function forConnection(AppfolioConnection $connection): self
    {
        return self::firstOrCreate(
            ['appfolio_connection_id' => $connection->id],
            [
                'consecutive_failures' => 0,
                'failure_details' => null,
            ]
        );
    }
}
