<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleSsoController extends Controller
{
    /**
     * Redirect the user to Google's OAuth page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->withErrors(['google' => 'Failed to authenticate with Google. Please try again.']);
        }

        // First, try to find user by google_id
        $user = User::where('google_id', $googleUser->getId())->first();

        // If not found by google_id, try to find by email (only for SSO users)
        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())
                ->where('auth_provider', User::AUTH_PROVIDER_GOOGLE)
                ->first();

            // Update google_id if found by email
            if ($user && ! $user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        }

        // Check if user exists but uses password authentication
        $passwordUser = User::where('email', $googleUser->getEmail())
            ->where('auth_provider', User::AUTH_PROVIDER_PASSWORD)
            ->first();

        if ($passwordUser) {
            return redirect()->route('login')
                ->withErrors(['google' => 'This email is registered with password authentication. Please use the password login form.']);
        }

        // User not found - they need to be created by an admin first
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['google' => 'No account found for this Google account. Please contact an administrator to create your account.']);
        }

        // Check if user is active
        if (! $user->is_active) {
            return redirect()->route('login')
                ->withErrors(['google' => 'Your account has been deactivated. Please contact an administrator.']);
        }

        // Log the user in
        Auth::login($user, remember: true);

        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
