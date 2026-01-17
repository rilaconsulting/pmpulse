<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class SocialiteServiceProvider extends ServiceProvider
{
    /**
     * The custom driver name for Google SSO with database configuration.
     * Uses a custom name to avoid overriding the built-in 'google' driver.
     */
    public const GOOGLE_DRIVER = 'google-db';

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register a custom Google driver that uses database config
        // Using 'google-db' instead of 'google' to avoid overriding the built-in driver
        Socialite::extend(self::GOOGLE_DRIVER, function ($app) {
            $googleConfig = Setting::getCategory('google_sso');

            $config = [
                'client_id' => $googleConfig['client_id'] ?? config('services.google.client_id'),
                'client_secret' => $googleConfig['client_secret'] ?? config('services.google.client_secret'),
                'redirect' => config('services.google.redirect'),
            ];

            return Socialite::buildProvider(
                \Laravel\Socialite\Two\GoogleProvider::class,
                $config
            );
        });
    }
}
