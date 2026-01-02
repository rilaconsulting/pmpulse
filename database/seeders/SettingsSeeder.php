<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds default application settings.
 *
 * This seeder populates the settings table with all default configuration values,
 * organized by category. Settings that already exist are not overwritten.
 */
class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSyncSettings();
        $this->seedBusinessHoursSettings();
        $this->seedRateLimitSettings();
        $this->seedAlertSettings();
        $this->seedFeatureFlags();
        $this->seedAppfolioSettings();
    }

    /**
     * Seed sync configuration settings.
     */
    protected function seedSyncSettings(): void
    {
        $settings = [
            [
                'key' => 'full_sync_time',
                'value' => '02:00',
                'description' => 'Time of day to run full sync (HH:MM format)',
            ],
            [
                'key' => 'incremental_sync_interval',
                'value' => 15,
                'description' => 'Minutes between incremental syncs',
            ],
            [
                'key' => 'batch_size',
                'value' => 100,
                'description' => 'Number of records to fetch per API request',
            ],
            [
                'key' => 'incremental_days',
                'value' => 7,
                'description' => 'Number of days to look back for incremental sync',
            ],
            [
                'key' => 'full_sync_lookback_days',
                'value' => 365,
                'description' => 'Number of days to look back for full sync',
            ],
            [
                'key' => 'resources',
                'value' => ['properties', 'units', 'people', 'leases', 'ledger_transactions', 'work_orders'],
                'description' => 'Resource types to sync from AppFolio',
            ],
        ];

        $this->seedCategory('sync', $settings);
    }

    /**
     * Seed business hours configuration settings.
     */
    protected function seedBusinessHoursSettings(): void
    {
        $settings = [
            [
                'key' => 'enabled',
                'value' => true,
                'description' => 'Enable business hours sync frequency adjustment',
            ],
            [
                'key' => 'timezone',
                'value' => 'America/Los_Angeles',
                'description' => 'Timezone for business hours calculation',
            ],
            [
                'key' => 'start_hour',
                'value' => 9,
                'description' => 'Business hours start (0-23)',
            ],
            [
                'key' => 'end_hour',
                'value' => 17,
                'description' => 'Business hours end (0-23)',
            ],
            [
                'key' => 'weekdays_only',
                'value' => true,
                'description' => 'Only treat Mon-Fri as business days',
            ],
            [
                'key' => 'business_hours_interval',
                'value' => 15,
                'description' => 'Sync interval during business hours (minutes)',
            ],
            [
                'key' => 'off_hours_interval',
                'value' => 60,
                'description' => 'Sync interval during off-hours (minutes)',
            ],
        ];

        $this->seedCategory('business_hours', $settings);
    }

    /**
     * Seed rate limiting configuration settings.
     */
    protected function seedRateLimitSettings(): void
    {
        $settings = [
            [
                'key' => 'requests_per_minute',
                'value' => 60,
                'description' => 'Maximum API requests per minute',
            ],
            [
                'key' => 'max_retries',
                'value' => 5,
                'description' => 'Maximum retry attempts for failed requests',
            ],
            [
                'key' => 'initial_backoff_seconds',
                'value' => 1,
                'description' => 'Initial backoff time in seconds',
            ],
            [
                'key' => 'backoff_multiplier',
                'value' => 2,
                'description' => 'Multiplier for exponential backoff',
            ],
            [
                'key' => 'max_backoff_seconds',
                'value' => 60,
                'description' => 'Maximum backoff time in seconds',
            ],
        ];

        $this->seedCategory('rate_limit', $settings);
    }

    /**
     * Seed alert configuration settings.
     */
    protected function seedAlertSettings(): void
    {
        $settings = [
            [
                'key' => 'failure_threshold',
                'value' => 3,
                'description' => 'Number of consecutive failures before alerting',
            ],
            [
                'key' => 'cooldown_minutes',
                'value' => 60,
                'description' => 'Minimum minutes between alert emails',
            ],
            [
                'key' => 'recipients',
                'value' => null,
                'description' => 'Override alert recipients (null = all users)',
            ],
        ];

        $this->seedCategory('alerts', $settings);
    }

    /**
     * Seed feature flag settings.
     */
    protected function seedFeatureFlags(): void
    {
        $settings = [
            [
                'key' => 'notifications',
                'value' => true,
                'description' => 'Enable email notifications for alerts',
            ],
            [
                'key' => 'incremental_sync',
                'value' => true,
                'description' => 'Enable incremental sync mode',
            ],
            [
                'key' => 'dashboard_refresh',
                'value' => true,
                'description' => 'Enable auto-refresh on dashboard',
            ],
        ];

        $this->seedCategory('features', $settings);
    }

    /**
     * Seed AppFolio API configuration settings.
     */
    protected function seedAppfolioSettings(): void
    {
        $settings = [
            [
                'key' => 'api_base_url',
                'value' => 'https://api.appfolio.com',
                'description' => 'AppFolio API base URL',
            ],
        ];

        $this->seedCategory('appfolio', $settings);

        // Note: client_id and client_secret are not seeded with defaults
        // They should be configured via the admin UI or environment variables
    }

    /**
     * Seed settings for a category.
     *
     * Only creates settings that don't already exist (preserves existing values).
     *
     * @param  string  $category  The setting category
     * @param  array<array{key: string, value: mixed, description: string}>  $settings  Settings to seed
     */
    protected function seedCategory(string $category, array $settings): void
    {
        foreach ($settings as $setting) {
            // Only seed if setting doesn't exist
            $exists = Setting::query()
                ->where('category', $category)
                ->where('key', $setting['key'])
                ->exists();

            if (! $exists) {
                Setting::set(
                    $category,
                    $setting['key'],
                    $setting['value'],
                    encrypted: false,
                    description: $setting['description']
                );
            }
        }
    }
}
