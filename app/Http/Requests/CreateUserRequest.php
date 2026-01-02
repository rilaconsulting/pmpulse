<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => [
                Rule::requiredIf($this->input('auth_provider') === User::AUTH_PROVIDER_PASSWORD),
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
                'required',
                'string',
                Rule::in([User::AUTH_PROVIDER_PASSWORD, User::AUTH_PROVIDER_GOOGLE]),
            ],
            'google_id' => [
                Rule::requiredIf($this->input('auth_provider') === User::AUTH_PROVIDER_GOOGLE),
                'nullable',
                'string',
                'unique:users',
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
            'password.required_if' => 'Password is required for password authentication.',
            'google_id.required_if' => 'Google ID is required for Google SSO authentication.',
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
            $authProvider = $this->input('auth_provider');

            // SSO users should not have a password set by the admin
            if ($authProvider === User::AUTH_PROVIDER_GOOGLE && $this->filled('password')) {
                $validator->errors()->add(
                    'password',
                    'Password should not be set for Google SSO users.'
                );
            }

            // Password users should not have a Google ID
            if ($authProvider === User::AUTH_PROVIDER_PASSWORD && $this->filled('google_id')) {
                $validator->errors()->add(
                    'google_id',
                    'Google ID should not be set for password users.'
                );
            }
        });
    }
}
