<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDuplicateAnalysis extends Model
{
    use HasUuids;

    protected $fillable = [
        'requested_by',
        'status',
        'threshold',
        'limit',
        'results',
        'total_vendors',
        'comparisons_made',
        'duplicates_found',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'float',
            'limit' => 'integer',
            'results' => 'array',
            'total_vendors' => 'integer',
            'comparisons_made' => 'integer',
            'duplicates_found' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $results, int $totalVendors, int $comparisons, int $duplicatesFound): void
    {
        $this->update([
            'status' => 'completed',
            'results' => $results,
            'total_vendors' => $totalVendors,
            'comparisons_made' => $comparisons,
            'duplicates_found' => $duplicatesFound,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }
}
