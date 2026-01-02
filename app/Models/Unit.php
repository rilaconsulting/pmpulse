<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'external_id',
        'property_id',
        'unit_number',
        'sqft',
        'bedrooms',
        'bathrooms',
        'status',
        'market_rent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sqft' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'decimal:1',
            'market_rent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the property this unit belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the leases for this unit.
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Get the ledger transactions for this unit.
     */
    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    /**
     * Get the work orders for this unit.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get the active lease for this unit.
     */
    public function activeLease()
    {
        return $this->hasOne(Lease::class)
            ->where('status', 'active')
            ->latest('start_date');
    }

    /**
     * Check if the unit is vacant.
     */
    public function isVacant(): bool
    {
        return $this->status === 'vacant';
    }

    /**
     * Check if the unit is occupied.
     */
    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    /**
     * Scope to get only vacant units.
     */
    public function scopeVacant($query)
    {
        return $query->where('status', 'vacant');
    }

    /**
     * Scope to get only occupied units.
     */
    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    /**
     * Scope to get only active units.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
