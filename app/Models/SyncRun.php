<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'appfolio_connection_id',
        'mode',
        'status',
        'started_at',
        'ended_at',
        'resources_synced',
        'errors_count',
        'error_summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the connection this run belongs to.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(AppfolioConnection::class, 'appfolio_connection_id');
    }

    /**
     * Get the raw events for this run.
     */
    public function rawEvents(): HasMany
    {
        return $this->hasMany(RawAppfolioEvent::class);
    }

    /**
     * Mark the run as started.
     */
    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the run as completed.
     */
    public function markAsCompleted(int $resourcesSynced = 0): void
    {
        $this->update([
            'status' => 'completed',
            'ended_at' => now(),
            'resources_synced' => $resourcesSynced,
        ]);
    }

    /**
     * Mark the run as failed.
     */
    public function markAsFailed(string $errorSummary, int $errorsCount = 1): void
    {
        $this->update([
            'status' => 'failed',
            'ended_at' => now(),
            'errors_count' => $errorsCount,
            'error_summary' => $errorSummary,
        ]);
    }

    /**
     * Increment the resources synced count.
     */
    public function incrementResourcesSynced(int $count = 1): void
    {
        $this->increment('resources_synced', $count);
    }

    /**
     * Increment the errors count.
     */
    public function incrementErrorsCount(int $count = 1): void
    {
        $this->increment('errors_count', $count);
    }

    /**
     * Get the duration in seconds.
     */
    public function getDurationAttribute(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return $this->ended_at->diffInSeconds($this->started_at);
    }
}
