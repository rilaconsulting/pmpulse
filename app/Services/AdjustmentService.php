<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Adjustment Service
 *
 * Retrieves effective values for adjusted fields on properties.
 * Handles date logic, overlapping adjustments, and provides access
 * to both current and historical adjustment data.
 */
class AdjustmentService
{
    /**
     * Get the effective value for a property field on a given date.
     *
     * Returns the adjusted value if an active adjustment exists,
     * otherwise returns the original property value.
     *
     * @param  Property  $property  The property to get value for
     * @param  string  $field  The field name (e.g., 'unit_count', 'total_sqft')
     * @param  Carbon|null  $date  The date to check (defaults to today)
     * @return mixed The effective value (adjusted or original)
     */
    public function getEffectiveValue(Property $property, string $field, ?Carbon $date = null): mixed
    {
        $adjustment = $this->getActiveAdjustment($property, $field, $date);

        if ($adjustment) {
            return $adjustment->typed_adjusted_value;
        }

        return $this->getOriginalValue($property, $field);
    }

    /**
     * Check if an adjustment exists for a field on a given date.
     */
    public function hasAdjustment(Property $property, string $field, ?Carbon $date = null): bool
    {
        return $this->getActiveAdjustment($property, $field, $date) !== null;
    }

    /**
     * Get all active adjustments for a property on a given date.
     *
     * @return Collection<int, PropertyAdjustment>
     */
    public function getActiveAdjustments(Property $property, ?Carbon $date = null): Collection
    {
        $date = $date ?? Carbon::today();

        return $property->adjustments()
            ->activeOn($date)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the adjustment history for a specific field on a property.
     *
     * Returns all adjustments (active and historical) for the field,
     * ordered by effective_from date descending.
     *
     * @return Collection<int, PropertyAdjustment>
     */
    public function getAdjustmentHistory(Property $property, string $field): Collection
    {
        return $property->adjustments()
            ->forField($field)
            ->orderBy('effective_from', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the active adjustment for a specific field on a property.
     *
     * When multiple adjustments are active (overlapping), the most
     * recently created one takes precedence.
     */
    public function getActiveAdjustment(Property $property, string $field, ?Carbon $date = null): ?PropertyAdjustment
    {
        $date = $date ?? Carbon::today();

        return $property->adjustments()
            ->forField($field)
            ->activeOn($date)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get the original (unadjusted) value from the property.
     */
    public function getOriginalValue(Property $property, string $field): mixed
    {
        // Check if the field exists on the property model
        if (! isset(PropertyAdjustment::ADJUSTABLE_FIELDS[$field])) {
            return null;
        }

        return $property->getAttribute($field);
    }

    /**
     * Get effective values for multiple fields at once.
     *
     * @param  array<string>  $fields  Array of field names
     * @return array<string, mixed> Map of field name to effective value
     */
    public function getEffectiveValues(Property $property, array $fields, ?Carbon $date = null): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $this->getEffectiveValue($property, $field, $date);
        }

        return $values;
    }

    /**
     * Get all effective values with metadata about adjustments.
     *
     * Returns an array with the effective value, whether it's adjusted,
     * and the original value if different.
     *
     * @return array<string, array{value: mixed, is_adjusted: bool, original: mixed|null, adjustment: PropertyAdjustment|null}>
     */
    public function getEffectiveValuesWithMetadata(Property $property, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();
        $result = [];

        foreach (PropertyAdjustment::ADJUSTABLE_FIELDS as $field => $metadata) {
            $adjustment = $this->getActiveAdjustment($property, $field, $date);
            $originalValue = $this->getOriginalValue($property, $field);

            $result[$field] = [
                'value' => $adjustment ? $adjustment->typed_adjusted_value : $originalValue,
                'is_adjusted' => $adjustment !== null,
                'original' => $adjustment ? $originalValue : null,
                'adjustment' => $adjustment,
                'label' => $metadata['label'],
                'type' => $metadata['type'],
            ];
        }

        return $result;
    }

    /**
     * Create a new adjustment for a property.
     *
     * @throws \InvalidArgumentException If the field is not in ADJUSTABLE_FIELDS
     */
    public function createAdjustment(
        Property $property,
        string $field,
        mixed $adjustedValue,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo,
        string $reason,
        ?string $createdBy = null
    ): PropertyAdjustment {
        if (! isset(PropertyAdjustment::ADJUSTABLE_FIELDS[$field])) {
            throw new \InvalidArgumentException("Field '{$field}' is not adjustable. Allowed fields: ".implode(', ', array_keys(PropertyAdjustment::ADJUSTABLE_FIELDS)));
        }

        $originalValue = $this->getOriginalValue($property, $field);

        return $property->adjustments()->create([
            'field_name' => $field,
            'original_value' => $originalValue !== null ? (string) $originalValue : null,
            'adjusted_value' => (string) $adjustedValue,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * End a permanent adjustment by setting its effective_to date.
     *
     * @param  PropertyAdjustment  $adjustment  The adjustment to end
     * @param  Carbon|null  $endDate  The end date (defaults to today)
     * @return PropertyAdjustment The updated adjustment
     *
     * @throws \InvalidArgumentException If endDate is before the adjustment's effective_from date
     */
    public function endAdjustment(PropertyAdjustment $adjustment, ?Carbon $endDate = null): PropertyAdjustment
    {
        $endDate = $endDate ?? Carbon::today();

        // Validate that end date is not before the start date
        if ($endDate->lt($adjustment->effective_from)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'End date (%s) cannot be before the adjustment start date (%s).',
                    $endDate->toDateString(),
                    $adjustment->effective_from->toDateString()
                )
            );
        }

        $adjustment->update([
            'effective_to' => $endDate,
        ]);

        return $adjustment->refresh();
    }
}
