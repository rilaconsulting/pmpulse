<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'googleSsoEnabled' => $this->isGoogleSsoEnabled(),
        ]);
    }

    /**
     * Check if Google SSO is enabled and configured.
     */
    private function isGoogleSsoEnabled(): bool
    {
        $googleConfig = Setting::getCategory('google_sso');

        // Check database settings first
        if (! empty($googleConfig['enabled']) && ! empty($googleConfig['client_id']) && ! empty($googleConfig['client_secret'])) {
            return true;
        }

        // Fall back to env config
        return ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret'));
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
