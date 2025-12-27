<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'metric',
        'operator',
        'threshold',
        'enabled',
        'recipients',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:2',
            'enabled' => 'boolean',
            'recipients' => 'array',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * Available operators for comparison.
     */
    public const OPERATORS = [
        'gt' => 'Greater than',
        'gte' => 'Greater than or equal',
        'lt' => 'Less than',
        'lte' => 'Less than or equal',
        'eq' => 'Equal to',
    ];

    /**
     * Available metrics for alerting.
     */
    public const METRICS = [
        'vacancy_count' => 'Vacancy Count',
        'delinquency_amount' => 'Delinquency Amount',
        'work_order_days_open' => 'Work Order Days Open',
        'occupancy_rate' => 'Occupancy Rate',
    ];

    /**
     * Evaluate if this rule should trigger based on a value.
     */
    public function evaluate(float $value): bool
    {
        return match ($this->operator) {
            'gt' => $value > $this->threshold,
            'gte' => $value >= $this->threshold,
            'lt' => $value < $this->threshold,
            'lte' => $value <= $this->threshold,
            'eq' => $value == $this->threshold,
            default => false,
        };
    }

    /**
     * Mark this rule as triggered.
     */
    public function markAsTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Get the human-readable operator.
     */
    public function getOperatorLabelAttribute(): string
    {
        return self::OPERATORS[$this->operator] ?? $this->operator;
    }

    /**
     * Get the human-readable metric.
     */
    public function getMetricLabelAttribute(): string
    {
        return self::METRICS[$this->metric] ?? $this->metric;
    }

    /**
     * Scope to get only enabled rules.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
