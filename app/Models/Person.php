<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasFactory;

    protected $table = 'people';

    protected $fillable = [
        'external_id',
        'name',
        'email',
        'phone',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the leases for this person.
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Check if this person is a tenant.
     */
    public function isTenant(): bool
    {
        return $this->type === 'tenant';
    }

    /**
     * Scope to get only tenants.
     */
    public function scopeTenants($query)
    {
        return $query->where('type', 'tenant');
    }

    /**
     * Scope to get only active people.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
