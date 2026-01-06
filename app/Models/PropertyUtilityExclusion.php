<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Property Utility Exclusion
 *
 * Represents a per-utility exclusion for a property.
 * This allows excluding specific utility types from reports for a property
 * (e.g., tenant pays electric but landlord pays water).
 */
class PropertyUtilityExclusion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'property_id',
        'utility_type',
        'reason',
        'created_by',
    ];

    /**
     * Get the property this exclusion belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who created this exclusion.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the display label for the utility type.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        $types = UtilityAccount::getUtilityTypeOptions();

        return $types[$this->utility_type] ?? ucfirst($this->utility_type);
    }

    /**
     * Scope to filter by utility type.
     */
    public function scopeOfType(Builder $query, string $utilityType): Builder
    {
        return $query->where('utility_type', $utilityType);
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, string $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Get all property IDs excluded for a specific utility type.
     *
     * @return array<string>
     */
    public static function getExcludedPropertyIds(string $utilityType): array
    {
        return static::query()
            ->ofType($utilityType)
            ->pluck('property_id')
            ->toArray();
    }

    /**
     * Check if a property is excluded for a specific utility type.
     */
    public static function isPropertyExcluded(string $propertyId, string $utilityType): bool
    {
        return static::query()
            ->forProperty($propertyId)
            ->ofType($utilityType)
            ->exists();
    }
}
