<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class UtilityType extends Model
{
    use HasFactory, HasUuids;

    /**
     * Default icon for utility types without a configured icon.
     */
    public const DEFAULT_ICON = 'CubeIcon';

    /**
     * Default color scheme for utility types without a configured color.
     */
    public const DEFAULT_COLOR_SCHEME = 'slate';

    protected $fillable = [
        'key',
        'label',
        'icon',
        'color_scheme',
        'sort_order',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Get the utility accounts of this type.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(UtilityAccount::class);
    }

    /**
     * Get the utility notes of this type.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(UtilityNote::class);
    }

    /**
     * Get the formatting rules for this type.
     */
    public function formattingRules(): HasMany
    {
        return $this->hasMany(UtilityFormattingRule::class);
    }

    /**
     * Get the property exclusions for this type.
     */
    public function exclusions(): HasMany
    {
        return $this->hasMany(PropertyUtilityExclusion::class);
    }

    /**
     * Get the icon, falling back to default if not set.
     */
    public function getIconOrDefaultAttribute(): string
    {
        return $this->icon ?? self::DEFAULT_ICON;
    }

    /**
     * Get the color scheme, falling back to default if not set.
     */
    public function getColorSchemeOrDefaultAttribute(): string
    {
        return $this->color_scheme ?? self::DEFAULT_COLOR_SCHEME;
    }

    /**
     * Scope to order by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to get only system types.
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to get only custom (non-system) types.
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    /**
     * Get all utility types as key => label options.
     *
     * @return array<string, string>
     */
    public static function getOptions(): array
    {
        return static::ordered()
            ->pluck('label', 'key')
            ->toArray();
    }

    /**
     * Get all utility types with full metadata.
     *
     * @return Collection<int, array{id: string, key: string, label: string, icon: string, color_scheme: string, is_system: bool}>
     */
    public static function getAllWithMetadata(): Collection
    {
        return static::ordered()
            ->get()
            ->map(fn (self $type) => [
                'id' => $type->id,
                'key' => $type->key,
                'label' => $type->label,
                'icon' => $type->icon_or_default,
                'color_scheme' => $type->color_scheme_or_default,
                'is_system' => $type->is_system,
            ]);
    }

    /**
     * Find a utility type by its key.
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    /**
     * Get a utility type by key, or fail.
     */
    public static function findByKeyOrFail(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
    }

    /**
     * Check if a utility type key exists.
     */
    public static function keyExists(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Check if this type is in use by any related records.
     */
    public function isInUse(): bool
    {
        return $this->accounts()->exists()
            || $this->notes()->exists()
            || $this->formattingRules()->exists()
            || $this->exclusions()->exists();
    }

    /**
     * Check if this type can be deleted.
     * Types in use by accounts cannot be deleted.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->isInUse();
    }

    /**
     * Get the ID of a utility type by key.
     * Useful for test setup and factories.
     *
     * @param  string  $key  The utility type key (e.g., 'water', 'electric')
     */
    public static function getIdByKey(string $key): ?string
    {
        return static::where('key', $key)->value('id');
    }

    /**
     * Get the ID of a utility type by key, or create it if it doesn't exist.
     * Useful for test setup.
     *
     * @param  string  $key  The utility type key
     * @param  string|null  $label  Optional label (defaults to ucfirst of key)
     */
    public static function getOrCreateIdByKey(string $key, ?string $label = null): string
    {
        $type = static::findByKey($key);

        if ($type) {
            return $type->id;
        }

        return static::create([
            'key' => $key,
            'label' => $label ?? ucfirst($key),
            'icon' => self::DEFAULT_ICON,
            'color_scheme' => self::DEFAULT_COLOR_SCHEME,
            'sort_order' => 99,
            'is_system' => false,
        ])->id;
    }
}
