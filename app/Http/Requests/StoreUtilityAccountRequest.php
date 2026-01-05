<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\UtilityAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUtilityAccountRequest extends FormRequest
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
            'gl_account_number' => ['required', 'string', 'max:50', 'unique:utility_accounts,gl_account_number'],
            'gl_account_name' => ['required', 'string', 'max:255'],
            'utility_type' => ['required', 'string', Rule::in(array_keys(UtilityAccount::UTILITY_TYPES))],
            'is_active' => ['boolean'],
        ];
    }
}
