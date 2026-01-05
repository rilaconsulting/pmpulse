<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyAdjustment extends Model
{
    use HasFactory, HasUuids;

    /**
     * Adjustable field types with their metadata.
     */
    public const ADJUSTABLE_FIELDS = [
        'unit_count' => [
            'type' => 'integer',
            'label' => 'Unit Count',
            'description' => 'Override the total number of units for this property',
            'affects' => ['Occupancy Rate', 'Cost per Unit'],
        ],
        'total_sqft' => [
            'type' => 'integer',
            'label' => 'Total Square Footage',
            'description' => 'Override the total square footage for this property',
            'affects' => ['Cost per Sqft'],
        ],
        'market_rent' => [
            'type' => 'decimal',
            'label' => 'Market Rent',
            'description' => 'Override the portfolio market rent for this property',
            'affects' => ['Market Rent Totals', 'Loss to Lease'],
        ],
        'rentable_units' => [
            'type' => 'integer',
            'label' => 'Rentable Units',
            'description' => 'Count of units considered rentable (excludes model units, storage, etc.)',
            'affects' => ['Occupancy Rate', 'Vacancy Calculations'],
        ],
    ];

    protected $fillable = [
        'property_id',
        'field_name',
        'original_value',
        'adjusted_value',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * Get the property this adjustment belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who created this adjustment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this is a permanent adjustment (no end date).
     */
    public function isPermanent(): bool
    {
        return $this->effective_to === null;
    }

    /**
     * Check if this adjustment is active for a given date.
     */
    public function isActiveOn(?Carbon $date = null): bool
    {
        $date = $date ?? Carbon::today();

        if ($date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to === null) {
            return true;
        }

        return $date->lte($this->effective_to);
    }

    /**
     * Get the display label for the field.
     */
    public function getFieldLabelAttribute(): string
    {
        return self::ADJUSTABLE_FIELDS[$this->field_name]['label'] ?? $this->field_name;
    }

    /**
     * Get the typed adjusted value based on field type.
     */
    public function getTypedAdjustedValueAttribute(): mixed
    {
        $type = self::ADJUSTABLE_FIELDS[$this->field_name]['type'] ?? 'string';

        return match ($type) {
            'integer' => (int) $this->adjusted_value,
            'decimal' => (float) $this->adjusted_value,
            default => $this->adjusted_value,
        };
    }

    /**
     * Get the typed original value based on field type.
     */
    public function getTypedOriginalValueAttribute(): mixed
    {
        if ($this->original_value === null) {
            return null;
        }

        $type = self::ADJUSTABLE_FIELDS[$this->field_name]['type'] ?? 'string';

        return match ($type) {
            'integer' => (int) $this->original_value,
            'decimal' => (float) $this->original_value,
            default => $this->original_value,
        };
    }

    /**
     * Scope to get adjustments active on a given date.
     */
    public function scopeActiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        $date = $date ?? Carbon::today();

        return $query->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Scope to get adjustments for a specific field.
     */
    public function scopeForField(Builder $query, string $fieldName): Builder
    {
        return $query->where('field_name', $fieldName);
    }

    /**
     * Scope to get only permanent adjustments.
     */
    public function scopePermanent(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }

    /**
     * Scope to get only date-ranged adjustments.
     */
    public function scopeDateRanged(Builder $query): Builder
    {
        return $query->whereNotNull('effective_to');
    }

    /**
     * Get validation rules for a field type.
     */
    public static function getValidationRules(string $fieldName): array
    {
        $field = self::ADJUSTABLE_FIELDS[$fieldName] ?? null;

        if (! $field) {
            return ['string'];
        }

        return match ($field['type']) {
            'integer' => ['required', 'integer', 'min:0'],
            'decimal' => ['required', 'numeric', 'min:0'],
            default => ['required', 'string'],
        };
    }

    /**
     * Get all adjustable field names.
     */
    public static function getAdjustableFieldNames(): array
    {
        return array_keys(self::ADJUSTABLE_FIELDS);
    }
}
