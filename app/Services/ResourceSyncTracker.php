<?php

namespace App\Services;

use App\Models\SyncRun;
use Illuminate\Support\Facades\Log;

/**
 * Tracks sync metrics per resource type.
 *
 * Provides detailed logging and metric collection for each resource type
 * during a sync operation, including created/updated/skipped counts,
 * timing information, and error tracking.
 */
class ResourceSyncTracker
{
    private SyncRun $syncRun;

    private string $resourceType;

    private float $startTime;

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $errors = 0;

    private array $errorMessages = [];

    public function __construct(SyncRun $syncRun, string $resourceType)
    {
        $this->syncRun = $syncRun;
        $this->resourceType = $resourceType;
        $this->startTime = microtime(true);

        Log::info("Starting sync for {$resourceType}", [
            'run_id' => $syncRun->id,
            'mode' => $syncRun->mode,
            'resource' => $resourceType,
        ]);
    }

    /**
     * Record a created record.
     */
    public function recordCreated(): void
    {
        $this->created++;
    }

    /**
     * Record an updated record.
     */
    public function recordUpdated(): void
    {
        $this->updated++;
    }

    /**
     * Record a skipped record.
     */
    public function recordSkipped(string $reason = ''): void
    {
        $this->skipped++;

        if ($reason) {
            Log::debug("Skipped {$this->resourceType} record", [
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Record an error.
     */
    public function recordError(string $message, ?array $context = null): void
    {
        $this->errors++;
        $this->errorMessages[] = $message;

        Log::error("Error syncing {$this->resourceType}", array_merge([
            'run_id' => $this->syncRun->id,
            'resource' => $this->resourceType,
            'message' => $message,
        ], $context ?? []));

        // Also store in the sync run
        $this->syncRun->addResourceError($this->resourceType, $message);
    }

    /**
     * Get the total number of processed items.
     */
    public function getProcessedCount(): int
    {
        return $this->created + $this->updated;
    }

    /**
     * Get all metrics.
     */
    public function getMetrics(): array
    {
        $durationMs = (int) ((microtime(true) - $this->startTime) * 1000);

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Finish tracking and save metrics to the sync run.
     */
    public function finish(): array
    {
        $metrics = $this->getMetrics();

        // Save metrics to sync run
        $this->syncRun->updateResourceMetrics($this->resourceType, $metrics);

        // Log completion summary
        Log::info("Completed sync for {$this->resourceType}", [
            'run_id' => $this->syncRun->id,
            'resource' => $this->resourceType,
            'created' => $metrics['created'],
            'updated' => $metrics['updated'],
            'skipped' => $metrics['skipped'],
            'errors' => $metrics['errors'],
            'duration_ms' => $metrics['duration_ms'],
        ]);

        return $metrics;
    }

    /**
     * Check if there were any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errors > 0;
    }

    /**
     * Get error messages.
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }
}
