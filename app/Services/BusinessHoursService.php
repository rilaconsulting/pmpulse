<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Service to determine if current time is within business hours.
 *
 * Used for optimizing sync frequency - frequent syncs during business hours,
 * less frequent syncs during off-hours to conserve API resources.
 */
class BusinessHoursService
{
    /**
     * Check if the current time is within configured business hours.
     */
    public function isBusinessHours(): bool
    {
        $config = config('appfolio.business_hours');

        if (! $config['enabled']) {
            return true; // Treat all hours as business hours when disabled
        }

        $now = Carbon::now($config['timezone']);

        // Check if it's a weekday (Mon-Fri)
        if ($config['weekdays_only'] && $now->isWeekend()) {
            return false;
        }

        // Check if current hour is within business hours range
        $currentHour = $now->hour;

        return $currentHour >= $config['start_hour'] && $currentHour < $config['end_hour'];
    }

    /**
     * Get the sync interval in minutes based on current time.
     */
    public function getSyncInterval(): int
    {
        $config = config('appfolio.business_hours');

        if (! $config['enabled']) {
            return config('appfolio.incremental_sync_interval', 15);
        }

        return $this->isBusinessHours()
            ? $config['business_hours_interval']
            : $config['off_hours_interval'];
    }

    /**
     * Check if a sync should run at the current minute.
     *
     * This is used by the scheduler to determine if the sync should execute.
     * The sync is scheduled to run every minute, but this method determines
     * whether it should actually execute based on the appropriate interval.
     */
    public function shouldSyncNow(): bool
    {
        $config = config('appfolio.business_hours');

        if (! $config['enabled']) {
            // Fall back to legacy behavior - let Laravel scheduler handle interval
            return true;
        }

        $now = Carbon::now($config['timezone']);
        $currentMinute = $now->minute;
        $interval = $this->getSyncInterval();

        // Sync when the current minute is divisible by the interval
        return $currentMinute % $interval === 0;
    }

    /**
     * Get a human-readable description of the current sync mode.
     */
    public function getSyncModeDescription(): string
    {
        $config = config('appfolio.business_hours');

        if (! $config['enabled']) {
            return sprintf('Fixed interval: every %d minutes', config('appfolio.incremental_sync_interval', 15));
        }

        if ($this->isBusinessHours()) {
            return sprintf(
                'Business hours mode: every %d minutes (%s %d:00-%d:00)',
                $config['business_hours_interval'],
                $config['timezone'],
                $config['start_hour'],
                $config['end_hour']
            );
        }

        return sprintf(
            'Off-hours mode: every %d minutes',
            $config['off_hours_interval']
        );
    }

    /**
     * Get the next sync time based on current interval.
     */
    public function getNextSyncTime(): Carbon
    {
        $config = config('appfolio.business_hours');
        $now = Carbon::now($config['timezone'] ?? 'America/Los_Angeles');
        $interval = $this->getSyncInterval();

        // Calculate minutes until next interval
        $currentMinute = $now->minute;
        $minutesUntilNext = $interval - ($currentMinute % $interval);

        if ($minutesUntilNext === $interval) {
            $minutesUntilNext = 0; // We're at the interval boundary
        }

        return $now->copy()->addMinutes($minutesUntilNext)->startOfMinute();
    }

    /**
     * Get business hours configuration for display.
     */
    public function getConfiguration(): array
    {
        $config = config('appfolio.business_hours');

        return [
            'enabled' => $config['enabled'],
            'timezone' => $config['timezone'],
            'business_hours' => sprintf('%d:00 - %d:00', $config['start_hour'], $config['end_hour']),
            'weekdays_only' => $config['weekdays_only'],
            'business_hours_interval' => $config['business_hours_interval'],
            'off_hours_interval' => $config['off_hours_interval'],
            'current_mode' => $this->isBusinessHours() ? 'business_hours' : 'off_hours',
            'current_interval' => $this->getSyncInterval(),
            'next_sync' => $this->getNextSyncTime()->toIso8601String(),
        ];
    }
}
