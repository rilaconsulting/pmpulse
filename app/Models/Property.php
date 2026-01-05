<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'external_id',
        'name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'portfolio',
        'portfolio_id',
        'property_type',
        'year_built',
        'total_sqft',
        'county',
        'unit_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'portfolio_id' => 'integer',
            'year_built' => 'integer',
            'total_sqft' => 'integer',
            'unit_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if the property has geocoding coordinates.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Scope to get properties that need geocoding.
     */
    public function scopeNeedsGeocoding(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('latitude')
                ->orWhereNull('longitude');
        });
    }

    /**
     * Scope to get properties with coordinates.
     */
    public function scopeHasCoordinates(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    /**
     * Get the units for this property.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Get the ledger transactions for this property.
     */
    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    /**
     * Get the work orders for this property.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get the property rollups for this property.
     */
    public function rollups(): HasMany
    {
        return $this->hasMany(PropertyRollup::class);
    }

    /**
     * Get the flags for this property.
     */
    public function flags(): HasMany
    {
        return $this->hasMany(PropertyFlag::class);
    }

    /**
     * Get the adjustments for this property.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PropertyAdjustment::class);
    }

    /**
     * Get the utility expenses for this property.
     */
    public function utilityExpenses(): HasMany
    {
        return $this->hasMany(UtilityExpense::class);
    }

    /**
     * Check if property has a specific flag.
     */
    public function hasFlag(string $flagType): bool
    {
        return $this->flags()->where('flag_type', $flagType)->exists();
    }

    /**
     * Check if property is excluded from reports.
     */
    public function isExcludedFromReports(): bool
    {
        return $this->hasFlag('exclude_from_reports');
    }

    /**
     * Check if property is excluded from utility reports.
     */
    public function isExcludedFromUtilityReports(): bool
    {
        return $this->flags()
            ->whereIn('flag_type', PropertyFlag::UTILITY_EXCLUSION_FLAGS)
            ->exists();
    }

    /**
     * Get the full address as a string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state ? "{$this->state} {$this->zip}" : $this->zip,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Scope to get only active properties.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to exclude properties with a specific flag.
     */
    public function scopeWithoutFlag(Builder $query, string $flagType): Builder
    {
        return $query->whereDoesntHave('flags', function (Builder $q) use ($flagType): void {
            $q->where('flag_type', $flagType);
        });
    }

    /**
     * Scope to exclude properties with any of the specified flags.
     */
    public function scopeWithoutFlags(Builder $query, array $flagTypes): Builder
    {
        return $query->whereDoesntHave('flags', function (Builder $q) use ($flagTypes): void {
            $q->whereIn('flag_type', $flagTypes);
        });
    }

    /**
     * Scope to get properties for general reports (excludes 'exclude_from_reports' flag).
     */
    public function scopeForReports(Builder $query): Builder
    {
        return $query->withoutFlag('exclude_from_reports');
    }

    /**
     * Scope to get properties for utility reports (excludes HOA and tenant pays utilities).
     */
    public function scopeForUtilityReports(Builder $query): Builder
    {
        return $query->withoutFlags(PropertyFlag::UTILITY_EXCLUSION_FLAGS);
    }

    /**
     * Scope to include only properties with a specific flag.
     */
    public function scopeWithFlag(Builder $query, string $flagType): Builder
    {
        return $query->whereHas('flags', function (Builder $q) use ($flagType): void {
            $q->where('flag_type', $flagType);
        });
    }

    /**
     * Scope to search properties by name, address, or city.
     * Uses ILIKE for PostgreSQL, LOWER/LIKE for other databases.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = '%'.strtolower($search).'%';
        $driver = $query->getConnection()->getDriverName();

        return $query->where(function (Builder $q) use ($search, $driver) {
            if ($driver === 'pgsql') {
                $q->where('name', 'ILIKE', $search)
                    ->orWhere('address_line1', 'ILIKE', $search)
                    ->orWhere('city', 'ILIKE', $search);
            } else {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(address_line1) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$search]);
            }
        });
    }
}
