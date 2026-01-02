<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class SocialiteServiceProvider extends ServiceProvider
{
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
        // Extend Socialite to use database config for Google
        Socialite::extend('google', function ($app) {
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
