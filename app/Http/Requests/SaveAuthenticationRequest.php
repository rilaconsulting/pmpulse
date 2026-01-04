<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAuthenticationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasExistingSecret = ! empty(
            \App\Models\Setting::get('google_sso', 'client_secret')
        );

        return [
            'google_enabled' => ['required', 'boolean'],
            'google_client_id' => [
                'nullable',
                'string',
                'max:255',
                'required_if:google_enabled,true',
            ],
            'google_client_secret' => [
                'nullable',
                'string',
                'max:255',
                // Required when enabling SSO, unless a secret already exists
                $this->boolean('google_enabled') && ! $hasExistingSecret
                    ? 'required'
                    : 'nullable',
            ],
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
            'google_client_id.required_if' => 'Client ID is required when enabling Google SSO.',
            'google_client_id.max' => 'The client ID must not exceed 255 characters.',
            'google_client_secret.required' => 'Client secret is required when enabling Google SSO for the first time.',
            'google_client_secret.max' => 'The client secret must not exceed 255 characters.',
        ];
    }
}
