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
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:500'],
            'database' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/i'],
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
            'client_id.required' => 'The AppFolio client ID is required.',
            'database.required' => 'The AppFolio database name is required.',
            'database.regex' => 'The database name should only contain letters, numbers, and hyphens (e.g., "sutro" or "my-company").',
        ];
    }
}
