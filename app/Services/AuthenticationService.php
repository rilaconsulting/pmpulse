<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class AuthenticationService
{
    /**
     * Check if Google SSO is enabled and configured.
     *
     * This checks database settings first. If the 'enabled' key exists in the database,
     * it respects that setting (including explicit disable). Only falls back to
     * environment config if no database configuration exists.
     */
    public function isGoogleSsoEnabled(): bool
    {
        $googleConfig = Setting::getCategory('google_sso');

        // If database config exists, respect it (including explicit disable)
        if (array_key_exists('enabled', $googleConfig)) {
            return $googleConfig['enabled']
                && ! empty($googleConfig['client_id'])
                && ! empty($googleConfig['client_secret']);
        }

        // Only fall back to env config if no database config exists
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'));
    }
}
