<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilityAccountRequest extends FormRequest
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
            'gl_account_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('utility_accounts', 'gl_account_number')->ignore($this->route('utilityAccount')),
            ],
            'gl_account_name' => ['required', 'string', 'max:255'],
            'utility_type_id' => ['required', 'uuid', Rule::exists('utility_types', 'id')],
            'is_active' => ['boolean'],
        ];
    }
}
