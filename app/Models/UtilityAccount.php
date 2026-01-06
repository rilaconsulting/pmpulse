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
     * Default utility types (used as fallback if settings are not configured).
     */
    public const DEFAULT_UTILITY_TYPES = [
        'water' => 'Water',
        'electric' => 'Electric',
        'gas' => 'Gas',
        'garbage' => 'Garbage',
        'sewer' => 'Sewer',
        'other' => 'Other',
    ];

    /**
     * Settings category for utility configuration.
     */
    public const SETTINGS_CATEGORY = 'utilities';

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
        $types = self::getUtilityTypeOptions();

        return $types[$this->utility_type] ?? ucfirst($this->utility_type);
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
     * Get all active utility accounts keyed by GL account number.
     *
     * @return \Illuminate\Support\Collection<string, UtilityAccount>
     */
    public static function getActiveAccountsByGlNumber(): \Illuminate\Support\Collection
    {
        return static::active()->get()->keyBy('gl_account_number');
    }

    /**
     * Get all utility type options for dropdowns.
     *
     * Reads from settings with fallback to defaults.
     *
     * @return array<string, string>
     */
    public static function getUtilityTypeOptions(): array
    {
        $types = Setting::get(self::SETTINGS_CATEGORY, 'types', null);

        if ($types === null || empty($types)) {
            return self::DEFAULT_UTILITY_TYPES;
        }

        return $types;
    }

    /**
     * Set the utility type options.
     *
     * @param  array<string, string>  $types  Key => Label pairs
     */
    public static function setUtilityTypeOptions(array $types): void
    {
        Setting::set(self::SETTINGS_CATEGORY, 'types', $types);
    }

    /**
     * Add a new utility type.
     *
     * @param  string  $key  The utility type key (lowercase, no spaces)
     * @param  string  $label  The display label
     */
    public static function addUtilityType(string $key, string $label): array
    {
        $types = self::getUtilityTypeOptions();
        $types[$key] = $label;
        self::setUtilityTypeOptions($types);

        return $types;
    }

    /**
     * Remove a utility type.
     *
     * @param  string  $key  The utility type key to remove
     * @return bool True if removed, false if in use by accounts
     */
    public static function removeUtilityType(string $key): bool
    {
        // Check if any accounts use this type
        // Expenses are linked to accounts, so deleting the type only requires no accounts
        $accountCount = static::where('utility_type', $key)->count();

        if ($accountCount > 0) {
            return false;
        }

        $types = self::getUtilityTypeOptions();
        unset($types[$key]);
        self::setUtilityTypeOptions($types);

        return true;
    }

    /**
     * Update a utility type label.
     *
     * @param  string  $key  The utility type key
     * @param  string  $label  The new display label
     */
    public static function updateUtilityTypeLabel(string $key, string $label): array
    {
        $types = self::getUtilityTypeOptions();

        if (isset($types[$key])) {
            $types[$key] = $label;
            self::setUtilityTypeOptions($types);
        }

        return $types;
    }
}
