<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : $user;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => [
                'sometimes',
                'nullable',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'auth_provider' => [
                'sometimes',
                'string',
                Rule::in([User::AUTH_PROVIDER_PASSWORD, User::AUTH_PROVIDER_GOOGLE]),
            ],
            'google_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('users')->ignore($userId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'force_sso' => ['sometimes', 'boolean'],
            'role_id' => ['sometimes', 'nullable', 'uuid', 'exists:user_roles,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use.',
            'google_id.unique' => 'This Google ID is already associated with another account.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            if (! $user instanceof User) {
                return;
            }

            // Check if trying to deactivate last admin
            if ($this->has('is_active') && $this->boolean('is_active') === false) {
                if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
                    $validator->errors()->add(
                        'is_active',
                        'Cannot deactivate the last admin user.'
                    );
                }
            }

            // Check if trying to remove admin role from last admin
            if ($this->has('role_id')) {
                $adminRole = Role::where('name', Role::ADMIN)->first();
                $newRoleId = $this->input('role_id');

                if ($user->role_id === $adminRole?->id && $newRoleId !== $adminRole?->id) {
                    if ($this->isLastActiveAdmin($user)) {
                        $validator->errors()->add(
                            'role_id',
                            'Cannot remove admin role from the last admin user.'
                        );
                    }
                }
            }

            // Validate auth provider changes
            $currentAuthProvider = $user->auth_provider;
            $newAuthProvider = $this->input('auth_provider', $currentAuthProvider);

            // If changing from password to SSO, require google_id
            if ($currentAuthProvider === User::AUTH_PROVIDER_PASSWORD
                && $newAuthProvider === User::AUTH_PROVIDER_GOOGLE
                && ! $this->filled('google_id')
                && ! $user->google_id) {
                $validator->errors()->add(
                    'google_id',
                    'Google ID is required when switching to Google SSO.'
                );
            }

            // If changing from SSO to password, require password
            if ($currentAuthProvider === User::AUTH_PROVIDER_GOOGLE
                && $newAuthProvider === User::AUTH_PROVIDER_PASSWORD
                && ! $this->filled('password')) {
                $validator->errors()->add(
                    'password',
                    'Password is required when switching to password authentication.'
                );
            }
        });
    }

    /**
     * Check if the given user is the last active admin.
     */
    private function isLastActiveAdmin(User $user): bool
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        if (! $adminRole) {
            return false;
        }

        $activeAdminCount = User::where('role_id', $adminRole->id)
            ->where('is_active', true)
            ->count();

        return $activeAdminCount <= 1;
    }
}
