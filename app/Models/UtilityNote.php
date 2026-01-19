<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityNote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'property_id',
        'utility_type_id',
        'note',
        'created_by',
    ];

    /**
     * Get the property this note belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who created/updated this note.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the utility type for this note.
     */
    public function utilityType(): BelongsTo
    {
        return $this->belongsTo(UtilityType::class);
    }

    /**
     * Get the utility type label.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        return $this->utilityType?->label ?? 'Unknown';
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, string $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
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
}
