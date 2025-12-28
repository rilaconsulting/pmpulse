<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'property_id',
        'unit_id',
        'date',
        'type',
        'amount',
        'category',
        'description',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    /**
     * Get the property this transaction belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the unit this transaction belongs to.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Check if this is a charge.
     */
    public function isCharge(): bool
    {
        return $this->type === 'charge';
    }

    /**
     * Check if this is a payment.
     */
    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }

    /**
     * Scope to get only charges.
     */
    public function scopeCharges($query)
    {
        return $query->where('type', 'charge');
    }

    /**
     * Scope to get only payments.
     */
    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    /**
     * Scope to filter by category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
