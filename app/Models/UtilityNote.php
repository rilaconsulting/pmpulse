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
        'utility_type',
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
     * Get the utility type label.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        $types = UtilityAccount::getUtilityTypeOptions();

        return $types[$this->utility_type] ?? ucfirst($this->utility_type);
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, string $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope to filter by utility type.
     */
    public function scopeOfType(Builder $query, string $utilityType): Builder
    {
        return $query->where('utility_type', $utilityType);
    }
}
