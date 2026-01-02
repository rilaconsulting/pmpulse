<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyRollup extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'date',
        'property_id',
        'vacancy_count',
        'total_units',
        'occupancy_rate',
        'delinquency_amount',
        'delinquent_units',
        'open_work_orders',
        'avg_days_open_work_orders',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'occupancy_rate' => 'decimal:2',
            'delinquency_amount' => 'decimal:2',
            'avg_days_open_work_orders' => 'decimal:2',
        ];
    }

    /**
     * Get the property this rollup belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope to get rollups for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Scope to get rollups for a date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
