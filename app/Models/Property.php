<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'zip',
        'property_type',
        'unit_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
