<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\SocialiteServiceProvider;
use App\Services\AuthenticationService;
use App\Services\GoogleSsoService;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleSsoController extends Controller
{
    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly Guard $auth,
        private readonly GoogleSsoService $ssoService,
        private readonly AuthenticationService $authService,
    ) {}

    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        // Check if Google SSO is enabled
        if (! $this->authService->isGoogleSsoEnabled()) {
            return redirect()->route('login')
                ->withErrors(['google' => 'Google SSO is not configured. Please contact your administrator.']);
        }

        return $this->socialite->driver(SocialiteServiceProvider::GOOGLE_DRIVER)
            ->scopes(['openid', 'email', 'profile'])
            ->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = $this->socialite->driver(SocialiteServiceProvider::GOOGLE_DRIVER)->user();
        } catch (InvalidStateException $e) {
            Log::error('Google SSO failed due to invalid state.', ['exception' => $e]);

            return redirect()->route('login')
                ->withErrors(['google' => 'Authentication failed due to an invalid state. Please try again.']);
        } catch (\Exception $e) {
            Log::error('Google SSO authentication failed.', ['exception' => $e]);

            return redirect()->route('login')
                ->withErrors(['google' => 'Failed to authenticate with Google. Please try again.']);
        }

        // Validate Google user data
        $googleEmail = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        if (! $googleEmail || ! $googleId) {
            Log::warning('Google SSO returned incomplete user data.', [
                'has_email' => (bool) $googleEmail,
                'has_id' => (bool) $googleId,
            ]);

            return redirect()->route('login')
                ->withErrors(['google' => 'Google did not provide required account information. Please ensure your Google account has a verified email address.']);
        }

        // Resolve user via service
        $resolution = $this->ssoService->resolveUser($googleUser);

        if ($resolution['result'] !== GoogleSsoService::RESULT_SUCCESS) {
            return redirect()->route('login')
                ->withErrors(['google' => $resolution['message']]);
        }

        $user = $resolution['user'];

        // Validate user can log in
        $validation = $this->ssoService->validateUserForLogin($user);

        if (! $validation['valid']) {
            return redirect()->route('login')
                ->withErrors(['google' => $validation['message']]);
        }

        // Log the user in
        $this->auth->login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
