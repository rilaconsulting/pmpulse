<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Attempt authentication first to prevent user enumeration via timing attacks.
        // This ensures the password hash check happens regardless of auth_provider.
        $authResult = Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));

        if (! $authResult) {
            // Check if user exists to provide helpful auth provider guidance
            // Note: This is done AFTER password check to avoid enumeration via timing
            $user = User::where('email', $this->input('email'))->first();

            RateLimiter::hit($this->throttleKey());

            // Provide SSO guidance only for SSO users (acceptable UX trade-off for internal app)
            if ($user && $user->auth_provider === User::AUTH_PROVIDER_GOOGLE) {
                throw ValidationException::withMessages([
                    'email' => 'This account uses Google SSO. Please click "Login with Google" to sign in.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // User authenticated - now check if they should be allowed to log in
        /** @var User $user */
        $user = Auth::user();

        // Check if user is SSO-only (logout and reject if they should use SSO)
        if ($user->auth_provider === User::AUTH_PROVIDER_GOOGLE) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'This account uses Google SSO. Please click "Login with Google" to sign in.',
            ]);
        }

        // Check if user is active (logout and reject if deactivated)
        if (! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
