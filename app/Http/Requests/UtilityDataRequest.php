<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UtilityDataRequest extends FormRequest
{
    /**
     * Valid period options for utility data filtering.
     */
    private const VALID_PERIODS = [
        'month',
        'last_month',
        'last_3_months',
        'last_6_months',
        'last_12_months',
        'quarter',
        'ytd',
        'year',
    ];

    /**
     * Determine if the user is authorized to make this request.
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
            'period' => ['nullable', 'string', Rule::in(self::VALID_PERIODS)],
            // utility_type is validated as string only; controller handles fallback for invalid types
            'utility_type' => ['nullable', 'string', 'max:50'],
            'unit_count_min' => ['nullable', 'integer', 'min:0'],
            'unit_count_max' => [
                'nullable',
                'integer',
                'min:0',
                Rule::when($this->filled('unit_count_min'), 'gte:unit_count_min'),
            ],
            'property_types' => ['nullable', 'array'],
            'property_types.*' => ['string', 'max:50'],
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
            'unit_count_max.gte' => 'The maximum unit count must be greater than or equal to the minimum.',
        ];
    }
}
