<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
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

        return (int) $this->ended_at->diffInSeconds($this->started_at);
    }

    /**
     * Get resource metrics from metadata.
     *
     * @return array<string, array{created: int, updated: int, skipped: int, errors: int, duration_ms: int}>
     */
    public function getResourceMetrics(): array
    {
        return $this->metadata['resource_metrics'] ?? [];
    }

    /**
     * Update resource metrics in metadata.
     */
    public function updateResourceMetrics(string $resourceType, array $metrics): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['resource_metrics'] = $metadata['resource_metrics'] ?? [];
        $metadata['resource_metrics'][$resourceType] = $metrics;
        $this->update(['metadata' => $metadata]);
    }

    /**
     * Add an error for a specific resource type.
     */
    public function addResourceError(string $resourceType, string $error): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['resource_errors'] = $metadata['resource_errors'] ?? [];
        $metadata['resource_errors'][$resourceType] = $metadata['resource_errors'][$resourceType] ?? [];
        $metadata['resource_errors'][$resourceType][] = [
            'message' => $error,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only the last 10 errors per resource type
        if (count($metadata['resource_errors'][$resourceType]) > 10) {
            $metadata['resource_errors'][$resourceType] = array_slice(
                $metadata['resource_errors'][$resourceType],
                -10
            );
        }

        $this->update(['metadata' => $metadata]);
    }

    /**
     * Get errors for a specific resource type.
     */
    public function getResourceErrors(?string $resourceType = null): array
    {
        $errors = $this->metadata['resource_errors'] ?? [];

        if ($resourceType !== null) {
            return $errors[$resourceType] ?? [];
        }

        return $errors;
    }

    /**
     * Get a summary of the sync run for display.
     */
    public function getSummary(): array
    {
        $metrics = $this->getResourceMetrics();

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $totalDuration = 0;

        foreach ($metrics as $resource => $resourceMetrics) {
            $totalCreated += $resourceMetrics['created'] ?? 0;
            $totalUpdated += $resourceMetrics['updated'] ?? 0;
            $totalSkipped += $resourceMetrics['skipped'] ?? 0;
            $totalErrors += $resourceMetrics['errors'] ?? 0;
            $totalDuration += $resourceMetrics['duration_ms'] ?? 0;
        }

        return [
            'status' => $this->status,
            'mode' => $this->mode,
            'duration_seconds' => $this->duration,
            'total_created' => $totalCreated,
            'total_updated' => $totalUpdated,
            'total_skipped' => $totalSkipped,
            'total_errors' => $totalErrors,
            'total_duration_ms' => $totalDuration,
            'resources_synced' => $this->resources_synced,
            'resource_metrics' => $metrics,
            'resource_errors' => $this->getResourceErrors(),
        ];
    }
}
