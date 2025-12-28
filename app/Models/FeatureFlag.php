<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'enabled',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * Cache key for feature flags.
     */
    private const CACHE_KEY = 'feature_flags';
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Check if a feature is enabled.
     * First checks database, falls back to config.
     */
    public static function isEnabled(string $name): bool
    {
        $flags = self::getAllFlags();

        if (isset($flags[$name])) {
            return $flags[$name];
        }

        // Fall back to config
        return config("features.{$name}", false);
    }

    /**
     * Get all feature flags from cache or database.
     */
    public static function getAllFlags(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::all()->pluck('enabled', 'name')->toArray();
        });
    }

    /**
     * Clear the feature flags cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
