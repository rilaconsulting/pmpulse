<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Unified settings model for application configuration.
 *
 * Replaces sync_configurations and feature_flags tables with a flexible
 * key-value store organized by category.
 */
class Setting extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Cache TTL in seconds (1 hour).
     */
    public const CACHE_TTL = 3600;

    /**
     * Cache key prefix.
     */
    public const CACHE_PREFIX = 'settings:';

    protected $fillable = [
        'category',
        'key',
        'value',
        'encrypted',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'encrypted' => 'boolean',
        ];
    }

    /**
     * Get a setting value.
     *
     * @param  string  $category  The setting category (e.g., 'sync', 'features')
     * @param  string  $key  The setting key within the category
     * @param  mixed  $default  Default value if setting doesn't exist
     * @return mixed The setting value or default
     */
    public static function get(string $category, string $key, mixed $default = null): mixed
    {
        $cacheKey = self::getCacheKey($category, $key);

        // Check if we have a cached value
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey) ?? $default;
        }

        // Query database
        $setting = static::query()
            ->where('category', $category)
            ->where('key', $key)
            ->first();

        if (! $setting) {
            // Don't cache non-existent settings to allow default overrides
            return $default;
        }

        $value = $setting->value;

        // Decrypt if needed
        if ($setting->encrypted && $value !== null) {
            try {
                $value = Crypt::decryptString($value);
                // Try to decode as JSON if it looks like JSON
                if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }
            } catch (\Exception) {
                // If decryption fails, return the raw value
            }
        }

        // Cache the value for future calls
        Cache::put($cacheKey, $value, self::CACHE_TTL);

        return $value;
    }

    /**
     * Set a setting value.
     *
     * @param  string  $category  The setting category
     * @param  string  $key  The setting key within the category
     * @param  mixed  $value  The value to store
     * @param  bool  $encrypted  Whether to encrypt the value
     * @param  string|null  $description  Optional description
     */
    public static function set(
        string $category,
        string $key,
        mixed $value,
        bool $encrypted = false,
        ?string $description = null
    ): static {
        // Encrypt if needed
        $storedValue = $value;
        if ($encrypted && $value !== null) {
            $valueToEncrypt = is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value;
            $storedValue = Crypt::encryptString($valueToEncrypt);
        }

        $setting = static::updateOrCreate(
            [
                'category' => $category,
                'key' => $key,
            ],
            [
                'value' => $storedValue,
                'encrypted' => $encrypted,
                'description' => $description,
            ]
        );

        // Invalidate cache
        self::forgetCache($category, $key);

        return $setting;
    }

    /**
     * Get all settings for a category.
     *
     * @param  string  $category  The setting category
     * @return array<string, mixed> Key-value pairs of settings
     */
    public static function getCategory(string $category): array
    {
        $cacheKey = self::CACHE_PREFIX . "category:{$category}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category) {
            $settings = static::query()
                ->where('category', $category)
                ->get();

            $result = [];
            foreach ($settings as $setting) {
                $value = $setting->value;

                // Decrypt if needed
                if ($setting->encrypted && $value !== null) {
                    try {
                        $value = Crypt::decryptString($value);
                        // Try to decode as JSON
                        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
                            $decoded = json_decode($value, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $value = $decoded;
                            }
                        }
                    } catch (\Exception) {
                        // If decryption fails, return raw value
                    }
                }

                $result[$setting->key] = $value;
            }

            return $result;
        });
    }

    /**
     * Check if a feature is enabled.
     *
     * Convenience method for feature flags stored in the 'features' category.
     *
     * @param  string  $feature  The feature name
     * @param  bool  $default  Default value if not set
     */
    public static function isFeatureEnabled(string $feature, bool $default = false): bool
    {
        return (bool) self::get('features', $feature, $default);
    }

    /**
     * Delete a setting.
     *
     * @param  string  $category  The setting category
     * @param  string  $key  The setting key
     */
    public static function forget(string $category, string $key): bool
    {
        $deleted = static::query()
            ->where('category', $category)
            ->where('key', $key)
            ->delete();

        self::forgetCache($category, $key);

        return $deleted > 0;
    }

    /**
     * Delete all settings in a category.
     *
     * @param  string  $category  The setting category
     */
    public static function forgetCategory(string $category): int
    {
        $deleted = static::query()
            ->where('category', $category)
            ->delete();

        self::forgetCategoryCache($category);

        return $deleted;
    }

    /**
     * Generate cache key for a setting.
     */
    protected static function getCacheKey(string $category, string $key): string
    {
        return self::CACHE_PREFIX . "{$category}:{$key}";
    }

    /**
     * Invalidate cache for a specific setting.
     */
    protected static function forgetCache(string $category, string $key): void
    {
        Cache::forget(self::getCacheKey($category, $key));
        Cache::forget(self::CACHE_PREFIX . "category:{$category}");
    }

    /**
     * Invalidate cache for an entire category.
     */
    protected static function forgetCategoryCache(string $category): void
    {
        Cache::forget(self::CACHE_PREFIX . "category:{$category}");
        // Note: Individual setting caches in this category will expire naturally
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        // Get all unique categories and clear their caches
        $categories = static::query()
            ->distinct()
            ->pluck('category');

        foreach ($categories as $category) {
            self::forgetCategoryCache($category);
        }
    }
}
