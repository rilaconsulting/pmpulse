<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lease extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'unit_id',
        'person_id',
        'start_date',
        'end_date',
        'rent',
        'security_deposit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent' => 'decimal:2',
            'security_deposit' => 'decimal:2',
        ];
    }

    /**
     * Get the unit this lease belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the person (tenant) this lease belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Check if the lease is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the lease is expiring soon (within 60 days).
     */
    public function isExpiringSoon(): bool
    {
        if (! $this->end_date) {
            return false;
        }

        return $this->end_date->isBetween(now(), now()->addDays(60));
    }

    /**
     * Scope to get only active leases.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get leases expiring within N days.
     */
    public function scopeExpiringSoon($query, int $days = 60)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }
}
