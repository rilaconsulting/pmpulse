<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFlag extends Model
{
    use HasFactory, HasUuids;

    /**
     * Available flag types.
     */
    public const FLAG_TYPES = [
        'hoa' => 'HOA Property',
        'tenant_pays_utilities' => 'Tenant Pays Utilities',
        'exclude_from_reports' => 'Exclude from Reports',
        'under_renovation' => 'Under Renovation',
        'sold' => 'Sold',
        'other' => 'Other',
    ];

    /**
     * Flags that exclude from utility reports.
     */
    public const UTILITY_EXCLUSION_FLAGS = [
        'hoa',
        'tenant_pays_utilities',
    ];

    protected $fillable = [
        'property_id',
        'flag_type',
        'reason',
        'created_by',
    ];

    /**
     * Get the property that owns the flag.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user who created the flag.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the display label for the flag type.
     */
    public function getFlagLabelAttribute(): string
    {
        return self::FLAG_TYPES[$this->flag_type] ?? $this->flag_type;
    }

    /**
     * Check if this flag excludes the property from reports.
     */
    public function excludesFromReports(): bool
    {
        return $this->flag_type === 'exclude_from_reports';
    }

    /**
     * Check if this flag excludes the property from utility reports.
     */
    public function excludesFromUtilityReports(): bool
    {
        return in_array($this->flag_type, self::UTILITY_EXCLUSION_FLAGS, true);
    }
}
