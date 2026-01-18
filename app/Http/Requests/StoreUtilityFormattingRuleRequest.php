<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\UtilityAccount;
use App\Models\UtilityFormattingRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUtilityFormattingRuleRequest extends FormRequest
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
            'utility_type' => ['required', 'string', Rule::in(array_keys(UtilityAccount::getUtilityTypeOptions()))],
            'name' => ['required', 'string', 'max:100'],
            'operator' => ['required', 'string', Rule::in(array_keys(UtilityFormattingRule::OPERATORS))],
            'threshold' => ['required', 'numeric', 'min:0', 'max:1000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
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
            'color.regex' => 'The color must be a valid hex color code (e.g., #FF0000).',
            'background_color.regex' => 'The background color must be a valid hex color code (e.g., #FF0000).',
        ];
    }
}
