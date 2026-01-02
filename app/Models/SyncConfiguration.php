<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Sync configuration model for storing scheduler settings.
 *
 * This model stores configuration for the sync scheduler, allowing
 * administrators to adjust sync frequency via the admin UI.
 */
class SyncConfiguration extends Model
{
    protected $fillable = [
        'business_hours_enabled',
        'timezone',
        'start_hour',
        'end_hour',
        'weekdays_only',
        'business_hours_interval',
        'off_hours_interval',
        'full_sync_time',
    ];

    protected function casts(): array
    {
        return [
            'business_hours_enabled' => 'boolean',
            'weekdays_only' => 'boolean',
            'start_hour' => 'integer',
            'end_hour' => 'integer',
            'business_hours_interval' => 'integer',
            'off_hours_interval' => 'integer',
        ];
    }

    private const CACHE_KEY = 'sync_configuration';

    private const CACHE_TTL = 60; // 1 minute

    /**
     * Get the current sync configuration.
     * Falls back to environment config if no database record exists.
     */
    public static function current(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $config = self::query()->first();

            if ($config) {
                return [
                    'enabled' => $config->business_hours_enabled,
                    'timezone' => $config->timezone,
                    'start_hour' => $config->start_hour,
                    'end_hour' => $config->end_hour,
                    'weekdays_only' => $config->weekdays_only,
                    'business_hours_interval' => $config->business_hours_interval,
                    'off_hours_interval' => $config->off_hours_interval,
                    'full_sync_time' => $config->full_sync_time,
                ];
            }

            // Fall back to environment config
            return config('appfolio.business_hours', [
                'enabled' => true,
                'timezone' => 'America/Los_Angeles',
                'start_hour' => 9,
                'end_hour' => 17,
                'weekdays_only' => true,
                'business_hours_interval' => 15,
                'off_hours_interval' => 60,
            ]);
        });
    }

    /**
     * Clear the configuration cache.
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

    /**
     * Get available timezone options.
     */
    public static function getTimezones(): array
    {
        return [
            'America/Los_Angeles' => 'Pacific Time (PT)',
            'America/Denver' => 'Mountain Time (MT)',
            'America/Chicago' => 'Central Time (CT)',
            'America/New_York' => 'Eastern Time (ET)',
            'America/Anchorage' => 'Alaska Time (AKT)',
            'Pacific/Honolulu' => 'Hawaii Time (HT)',
        ];
    }
}
