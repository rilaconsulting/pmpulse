<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityFormattingRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'utility_type_id',
        'name',
        'operator',
        'threshold',
        'color',
        'background_color',
        'priority',
        'enabled',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'decimal:2',
            'priority' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    /**
     * Available operators for comparison against 12-month average.
     */
    public const OPERATORS = [
        'increase_percent' => 'Increase % over average',
        'decrease_percent' => 'Decrease % below average',
    ];

    /**
     * Get the user who created this rule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the utility type for this rule.
     */
    public function utilityType(): BelongsTo
    {
        return $this->belongsTo(UtilityType::class);
    }

    /**
     * Evaluate if this rule matches for a given value and average.
     *
     * @param  float  $value  The current value to evaluate
     * @param  float  $average  The 12-month average to compare against
     * @return bool True if the rule matches, false otherwise
     */
    public function evaluate(float $value, float $average): bool
    {
        if ($average <= 0) {
            return false;
        }

        $percentChange = (($value - $average) / $average) * 100;

        return match ($this->operator) {
            'increase_percent' => $percentChange >= $this->threshold,
            'decrease_percent' => $percentChange <= -$this->threshold,
            default => false,
        };
    }

    /**
     * Get the human-readable operator label.
     */
    public function getOperatorLabelAttribute(): string
    {
        return self::OPERATORS[$this->operator] ?? $this->operator;
    }

    /**
     * Scope to get only enabled rules.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to filter by utility type ID.
     */
    public function scopeForUtilityType(Builder $query, string $utilityTypeId): Builder
    {
        return $query->where('utility_type_id', $utilityTypeId);
    }

    /**
     * Scope to filter by utility type key.
     */
    public function scopeForUtilityTypeKey(Builder $query, string $typeKey): Builder
    {
        return $query->whereHas('utilityType', fn ($q) => $q->where('key', $typeKey));
    }

    /**
     * Scope to order by priority (highest first).
     */
    public function scopeByPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }
}
