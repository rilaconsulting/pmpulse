<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilityAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'gl_account_number',
        'gl_account_name',
        'utility_type_id',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user who created this utility account mapping.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the utility type for this account.
     */
    public function utilityType(): BelongsTo
    {
        return $this->belongsTo(UtilityType::class);
    }

    /**
     * Get the utility expenses linked to this account.
     */
    public function utilityExpenses(): HasMany
    {
        return $this->hasMany(UtilityExpense::class);
    }

    /**
     * Get the display label for the utility type.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        return $this->utilityType?->label ?? 'Unknown';
    }

    /**
     * Scope to get only active utility accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by utility type ID.
     */
    public function scopeOfType(Builder $query, string $utilityTypeId): Builder
    {
        return $query->where('utility_type_id', $utilityTypeId);
    }

    /**
     * Scope to filter by utility type key.
     */
    public function scopeOfTypeKey(Builder $query, string $typeKey): Builder
    {
        return $query->whereHas('utilityType', fn ($q) => $q->where('key', $typeKey));
    }

    /**
     * Check if a GL account number matches this utility account.
     */
    public function matchesGlAccount(string $glAccountNumber): bool
    {
        return $this->gl_account_number === $glAccountNumber;
    }

    /**
     * Get all active utility accounts keyed by GL account number.
     *
     * @return \Illuminate\Support\Collection<string, UtilityAccount>
     */
    public static function getActiveAccountsByGlNumber(): \Illuminate\Support\Collection
    {
        return static::active()->get()->keyBy('gl_account_number');
    }
}
