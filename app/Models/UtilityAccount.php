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

    /**
     * Available utility types.
     */
    public const UTILITY_TYPES = [
        'water' => 'Water',
        'electric' => 'Electric',
        'gas' => 'Gas',
        'garbage' => 'Garbage',
        'sewer' => 'Sewer',
        'other' => 'Other',
    ];

    protected $fillable = [
        'gl_account_number',
        'gl_account_name',
        'utility_type',
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
     * Get the utility expenses for this account.
     */
    public function utilityExpenses(): HasMany
    {
        return $this->hasMany(UtilityExpense::class, 'utility_type', 'utility_type');
    }

    /**
     * Get the display label for the utility type.
     */
    public function getUtilityTypeLabelAttribute(): string
    {
        return self::UTILITY_TYPES[$this->utility_type] ?? $this->utility_type;
    }

    /**
     * Scope to get only active utility accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by utility type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('utility_type', $type);
    }

    /**
     * Check if a GL account number matches this utility account.
     */
    public function matchesGlAccount(string $glAccountNumber): bool
    {
        return $this->gl_account_number === $glAccountNumber;
    }

    /**
     * Get all active GL account numbers mapped to utility types.
     *
     * @return array<string, string> GL account number => utility type
     */
    public static function getActiveAccountMappings(): array
    {
        return static::active()
            ->pluck('utility_type', 'gl_account_number')
            ->toArray();
    }

    /**
     * Get all utility type options for dropdowns.
     *
     * @return array<string, string>
     */
    public static function getUtilityTypeOptions(): array
    {
        return self::UTILITY_TYPES;
    }
}
