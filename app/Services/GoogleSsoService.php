<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as GoogleUser;

class GoogleSsoService
{
    /**
     * Result codes for user resolution.
     */
    public const RESULT_SUCCESS = 'success';

    public const RESULT_NOT_FOUND = 'not_found';

    public const RESULT_PASSWORD_USER = 'password_user';

    public const RESULT_GOOGLE_ID_MISMATCH = 'google_id_mismatch';

    public const RESULT_INACTIVE = 'inactive';

    /**
     * Resolve a user from Google OAuth data.
     *
     * @return array{result: string, user: User|null, message: string|null}
     */
    public function resolveUser(GoogleUser $googleUser): array
    {
        $googleEmail = $googleUser->getEmail();
        $googleId = $googleUser->getId();

        // First, try to find user by google_id (strongest match - already linked)
        $userByGoogleId = User::where('google_id', $googleId)->first();

        // Then, try to find user by email
        $userByEmail = User::where('email', $googleEmail)->first();

        // Case 1: No user found by either google_id or email
        if (! $userByGoogleId && ! $userByEmail) {
            return [
                'result' => self::RESULT_NOT_FOUND,
                'user' => null,
                'message' => 'No account found for this Google account. Please contact an administrator to create your account.',
            ];
        }

        // Case 2: Found user by google_id, but email doesn't match their account
        // This could be a Google account that was linked to a different email
        if ($userByGoogleId && $userByGoogleId->email !== $googleEmail) {
            // If there's also a different user with this email, that's a conflict
            if ($userByEmail && $userByEmail->id !== $userByGoogleId->id) {
                Log::warning('Google ID linked to different email than OAuth provided.', [
                    'google_id_user' => $userByGoogleId->id,
                    'email_user' => $userByEmail->id,
                    'oauth_email' => $googleEmail,
                ]);

                return [
                    'result' => self::RESULT_GOOGLE_ID_MISMATCH,
                    'user' => null,
                    'message' => 'This Google account is linked to a different email address. Please contact an administrator.',
                ];
            }

            // The linked user's email changed on Google's side - still allow login
            $user = $userByGoogleId;
        } elseif ($userByGoogleId) {
            // Found by google_id and email matches - perfect match
            $user = $userByGoogleId;
        } else {
            // Found only by email (no google_id link yet)
            $user = $userByEmail;
        }

        // Case 3: User uses password authentication
        if ($user->auth_provider === User::AUTH_PROVIDER_PASSWORD) {
            return [
                'result' => self::RESULT_PASSWORD_USER,
                'user' => null,
                'message' => 'This email is registered with password authentication. Please use the password login form.',
            ];
        }

        // Case 4: User is a Google user, but google_id from provider does not match stored google_id
        // This can happen if they try to log in with a different Google account that shares the same email
        if ($user->google_id && $user->google_id !== $googleId) {
            Log::warning('Mismatched Google ID for user.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'stored_google_id' => $user->google_id,
                'oauth_google_id' => $googleId,
            ]);

            return [
                'result' => self::RESULT_GOOGLE_ID_MISMATCH,
                'user' => null,
                'message' => 'This email is associated with a different Google account. Please use the original Google account to log in.',
            ];
        }

        // Case 5: User found by email, google_id is not set yet. Link it.
        if (! $user->google_id) {
            // Check if this google_id is already associated with another user
            $existingGoogleUser = User::where('google_id', $googleId)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($existingGoogleUser) {
                Log::warning('Attempted to link Google ID that is already associated with another user.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return [
                    'result' => self::RESULT_GOOGLE_ID_MISMATCH,
                    'user' => null,
                    'message' => 'This Google account is already linked to a different user. Please contact an administrator.',
                ];
            }

            $user->update(['google_id' => $googleId]);
        }

        return [
            'result' => self::RESULT_SUCCESS,
            'user' => $user,
            'message' => null,
        ];
    }

    /**
     * Validate that a user can log in.
     *
     * @return array{valid: bool, message: string|null}
     */
    public function validateUserForLogin(User $user): array
    {
        if (! $user->is_active) {
            return [
                'valid' => false,
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ];
        }

        return [
            'valid' => true,
            'message' => null,
        ];
    }
}
