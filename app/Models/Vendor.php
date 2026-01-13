<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'external_id',
        'canonical_vendor_id',
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
     * Get the canonical vendor this vendor is a duplicate of.
     */
    public function canonicalVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'canonical_vendor_id');
    }

    /**
     * Get all duplicate vendors that point to this vendor as canonical.
     */
    public function duplicateVendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'canonical_vendor_id');
    }

    /**
     * Scope to get only canonical vendors (not duplicates).
     */
    public function scopeCanonical(Builder $query): Builder
    {
        return $query->whereNull('canonical_vendor_id');
    }

    /**
     * Scope to get only duplicate vendors.
     */
    public function scopeDuplicates(Builder $query): Builder
    {
        return $query->whereNotNull('canonical_vendor_id');
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

    /**
     * Check if this vendor is canonical (not a duplicate).
     */
    public function isCanonical(): bool
    {
        return $this->canonical_vendor_id === null;
    }

    /**
     * Check if this vendor is a duplicate of another vendor.
     */
    public function isDuplicate(): bool
    {
        return $this->canonical_vendor_id !== null;
    }

    /**
     * Get the canonical vendor for this vendor.
     * Returns self if this is already a canonical vendor.
     */
    public function getCanonicalVendor(): Vendor
    {
        // Check FK first to avoid lazy loading for canonical vendors
        if ($this->canonical_vendor_id === null) {
            return $this;
        }

        return $this->canonicalVendor ?? $this;
    }

    /**
     * Get the effective vendor ID for grouping/reporting.
     * Returns the canonical vendor's ID if this is a duplicate, otherwise returns own ID.
     */
    public function getEffectiveVendorId(): string
    {
        return $this->canonical_vendor_id ?? $this->id;
    }

    /**
     * Get all vendor IDs in the same canonical group (for queries).
     * Includes this vendor and all its duplicates (if canonical),
     * or the canonical vendor and all its duplicates (if duplicate).
     */
    public function getAllGroupVendorIds(): array
    {
        $canonical = $this->getCanonicalVendor();

        $ids = [$canonical->id];

        foreach ($canonical->duplicateVendors as $duplicate) {
            $ids[] = $duplicate->id;
        }

        return $ids;
    }

    /**
     * Get all work orders for this vendor's canonical group.
     * Combines work orders from the canonical vendor and all duplicates.
     */
    public function getGroupWorkOrders(): HasMany
    {
        return $this->getCanonicalVendor()
            ->workOrders()
            ->orWhereIn('vendor_id', $this->getAllGroupVendorIds());
    }

    /**
     * Set this vendor as a duplicate of another vendor.
     */
    public function markAsDuplicateOf(Vendor $canonicalVendor): bool
    {
        if ($canonicalVendor->isDuplicate()) {
            // The target is also a duplicate, use its canonical instead
            $canonicalVendor = $canonicalVendor->getCanonicalVendor();
        }

        if ($canonicalVendor->id === $this->id) {
            return false; // Can't mark as duplicate of self
        }

        $this->canonical_vendor_id = $canonicalVendor->id;

        return $this->save();
    }

    /**
     * Remove this vendor from its canonical group (make it canonical).
     */
    public function markAsCanonical(): bool
    {
        $this->canonical_vendor_id = null;

        return $this->save();
    }
}
