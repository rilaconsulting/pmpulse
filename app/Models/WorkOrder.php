<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'external_id',
        'property_id',
        'unit_id',
        'vendor_id',
        'vendor_name',
        'opened_at',
        'closed_at',
        'status',
        'priority',
        'category',
        'description',
        'amount',
        'vendor_bill_amount',
        'estimate_amount',
        'vendor_trade',
        'work_order_type',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'amount' => 'decimal:2',
            'vendor_bill_amount' => 'decimal:2',
            'estimate_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the property this work order belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the unit this work order belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the vendor assigned to this work order.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Check if the work order is open.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress']);
    }

    /**
     * Check if the work order is closed.
     */
    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled']);
    }

    /**
     * Get the number of days this work order has been open.
     */
    public function getDaysOpenAttribute(): int
    {
        if ($this->opened_at === null) {
            return 0;
        }

        $endDate = $this->closed_at ?? now();

        return (int) $this->opened_at->diffInDays($endDate);
    }

    /**
     * Scope to get only open work orders.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    /**
     * Scope to get only closed work orders.
     */
    public function scopeClosed($query)
    {
        return $query->whereIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope to get work orders by priority.
     */
    public function scopeOfPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to get work orders by category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get work orders open longer than N days.
     */
    public function scopeOpenLongerThan($query, int $days)
    {
        return $query->open()
            ->where('opened_at', '<=', now()->subDays($days));
    }
}
