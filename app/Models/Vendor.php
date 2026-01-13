<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'external_id',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'address_street',
        'address_city',
        'address_state',
        'address_zip',
        'vendor_type',
        'vendor_trades',
        'workers_comp_expires',
        'liability_ins_expires',
        'auto_ins_expires',
        'state_lic_expires',
        'do_not_use',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'workers_comp_expires' => 'date',
            'liability_ins_expires' => 'date',
            'auto_ins_expires' => 'date',
            'state_lic_expires' => 'date',
            'do_not_use' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the work orders for this vendor.
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Scope to get active vendors.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get usable vendors (not marked do_not_use).
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('do_not_use', false);
    }

    /**
     * Get vendor trades as an array.
     */
    public function getTradesArrayAttribute(): array
    {
        if (empty($this->vendor_trades)) {
            return [];
        }

        return array_map('trim', explode(',', $this->vendor_trades));
    }

    /**
     * Check if any insurance is expired.
     */
    public function hasExpiredInsurance(): bool
    {
        $today = now()->startOfDay();

        return ($this->workers_comp_expires && $this->workers_comp_expires < $today)
            || ($this->liability_ins_expires && $this->liability_ins_expires < $today)
            || ($this->auto_ins_expires && $this->auto_ins_expires < $today);
    }

    /**
     * Check if state license is expired.
     */
    public function hasExpiredLicense(): bool
    {
        if (! $this->state_lic_expires) {
            return false;
        }

        return $this->state_lic_expires < now()->startOfDay();
    }

    /**
     * Get the full address as a string.
     */
    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address_street,
            $this->address_city,
            $this->address_state,
            $this->address_zip,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }
}
