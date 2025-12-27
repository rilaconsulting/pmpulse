<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawAppfolioEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_run_id',
        'resource_type',
        'external_id',
        'payload_json',
        'pulled_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'pulled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Get the sync run this event belongs to.
     */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    /**
     * Check if this event has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Mark this event as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    /**
     * Scope to get unprocessed events.
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope to filter by resource type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('resource_type', $type);
    }
}
