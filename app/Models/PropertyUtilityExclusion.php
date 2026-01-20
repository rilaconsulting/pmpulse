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
        'utility_type_id',
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
     * Get the utility type for this exclusion.
     */
    public function utilityType(): BelongsTo
    {
        return $this->belongsTo(UtilityType::class);
    }

    /**
     * Get the display label for the utility type.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        return $this->utilityType?->label ?? 'Unknown';
    }

    /**
     * Scope to filter by utility type ID.
     */
    public function scopeOfType(Builder $query, string $utilityTypeId): Builder
    {
        return $query->where('utility_type_id', $utilityTypeId);
    }

    /**
     * Scope to filter by utility type key.
     */
    public function scopeOfTypeKey(Builder $query, string $typeKey): Builder
    {
        return $query->whereHas('utilityType', fn ($q) => $q->where('key', $typeKey));
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
     * @param  string  $utilityTypeId  The utility type ID
     * @return array<string>
     */
    public static function getExcludedPropertyIds(string $utilityTypeId): array
    {
        return static::query()
            ->ofType($utilityTypeId)
            ->pluck('property_id')
            ->toArray();
    }

    /**
     * Get all property IDs excluded for a specific utility type by key.
     *
     * @param  string  $typeKey  The utility type key (e.g., 'water', 'electric')
     * @return array<string>
     */
    public static function getExcludedPropertyIdsByTypeKey(string $typeKey): array
    {
        return static::query()
            ->ofTypeKey($typeKey)
            ->pluck('property_id')
            ->toArray();
    }

    /**
     * Check if a property is excluded for a specific utility type.
     *
     * @param  string  $propertyId  The property ID
     * @param  string  $utilityTypeId  The utility type ID
     */
    public static function isPropertyExcluded(string $propertyId, string $utilityTypeId): bool
    {
        return static::query()
            ->forProperty($propertyId)
            ->ofType($utilityTypeId)
            ->exists();
    }

    /**
     * Check if a property is excluded for a specific utility type by key.
     *
     * @param  string  $propertyId  The property ID
     * @param  string  $typeKey  The utility type key (e.g., 'water', 'electric')
     */
    public static function isPropertyExcludedByTypeKey(string $propertyId, string $typeKey): bool
    {
        return static::query()
            ->forProperty($propertyId)
            ->ofTypeKey($typeKey)
            ->exists();
    }
}
