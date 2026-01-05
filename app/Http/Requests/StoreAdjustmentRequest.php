<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PropertyAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdjustmentRequest extends FormRequest
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
        $fieldName = $this->input('field_name');
        $fieldRules = PropertyAdjustment::getValidationRules($fieldName);

        return [
            'field_name' => [
                'required',
                'string',
                Rule::in(array_keys(PropertyAdjustment::ADJUSTABLE_FIELDS)),
            ],
            'adjusted_value' => $fieldRules,
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['required', 'string', 'max:1000'],
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
            'field_name.required' => 'Please select a field to adjust.',
            'field_name.in' => 'Please select a valid field.',
            'adjusted_value.required' => 'Please enter the adjusted value.',
            'adjusted_value.integer' => 'The adjusted value must be a whole number.',
            'adjusted_value.numeric' => 'The adjusted value must be a number.',
            'adjusted_value.min' => 'The adjusted value must be at least 0.',
            'effective_from.required' => 'Please select an effective from date.',
            'effective_from.date' => 'Please enter a valid date.',
            'effective_to.date' => 'Please enter a valid date.',
            'effective_to.after_or_equal' => 'The effective to date must be on or after the effective from date.',
            'reason.required' => 'Please provide a reason for this adjustment.',
            'reason.max' => 'The reason cannot exceed 1000 characters.',
        ];
    }
}
