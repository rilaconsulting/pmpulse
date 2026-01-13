<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityExpense extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'property_id',
        'utility_account_id',
        'gl_account_number',
        'expense_date',
        'period_start',
        'period_end',
        'amount',
        'vendor_name',
        'description',
        'external_expense_id',
        'bill_detail_id',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the property this expense belongs to.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the utility account this expense is linked to.
     */
    public function utilityAccount(): BelongsTo
    {
        return $this->belongsTo(UtilityAccount::class);
    }

    /**
     * Get the bill detail this expense was created from.
     */
    public function billDetail(): BelongsTo
    {
        return $this->belongsTo(BillDetail::class);
    }

    /**
     * Get the utility type from the linked account.
     */
    public function getUtilityTypeAttribute(): ?string
    {
        return $this->utilityAccount?->utility_type;
    }

    /**
     * Get the display label for the utility type.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        $type = $this->utility_type;

        if ($type === null) {
            return 'Unknown';
        }

        $types = UtilityAccount::getUtilityTypeOptions();

        return $types[$type] ?? $type;
    }

    /**
     * Scope to filter by utility type (via account relationship).
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->whereHas('utilityAccount', function ($q) use ($type) {
            $q->where('utility_type', $type);
        });
    }

    /**
     * Scope to filter by property.
     */
    public function scopeForProperty(Builder $query, string $propertyId): Builder
    {
        return $query->where('property_id', $propertyId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeInDateRange(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('expense_date', [$start, $end]);
    }

    /**
     * Scope to filter by month.
     */
    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month);
    }

    /**
     * Scope to filter by year.
     */
    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('expense_date', $year);
    }

    /**
     * Scope to get expenses for the current month.
     */
    public function scopeCurrentMonth(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query->inMonth($now->year, $now->month);
    }

    /**
     * Scope to get expenses for the previous month.
     */
    public function scopePreviousMonth(Builder $query): Builder
    {
        $lastMonth = Carbon::now()->subMonth();

        return $query->inMonth($lastMonth->year, $lastMonth->month);
    }

    /**
     * Get the total amount for a given query.
     */
    public static function sumAmount(Builder $query): float
    {
        return (float) $query->sum('amount');
    }
}
