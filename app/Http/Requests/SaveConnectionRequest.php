<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Note: For this single-tenant application, all authenticated users
     * are trusted admins. In a multi-tenant setup, add role checks here.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'api_base_url' => ['required', 'url', 'max:255'],
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
            'name.required' => 'A connection name is required.',
            'client_id.required' => 'The AppFolio client ID is required.',
            'api_base_url.required' => 'The API base URL is required.',
            'api_base_url.url' => 'The API base URL must be a valid URL.',
        ];
    }
}
