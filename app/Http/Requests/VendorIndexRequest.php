<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorIndexRequest extends FormRequest
{
    /**
     * Valid insurance status values.
     */
    public const INSURANCE_STATUSES = ['expired', 'expiring_soon', 'current'];

    /**
     * Valid canonical filter values.
     */
    public const CANONICAL_FILTERS = ['canonical_only', 'all', 'duplicates_only'];

    /**
     * Valid sort fields.
     */
    public const ALLOWED_SORTS = ['company_name', 'vendor_type', 'is_active', 'work_orders_count'];

    /**
     * Valid sort directions.
     */
    public const SORT_DIRECTIONS = ['asc', 'desc'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert is_active string to boolean
        if ($this->has('is_active') && $this->input('is_active') !== '') {
            $value = $this->input('is_active');
            if (is_string($value)) {
                $this->merge([
                    'is_active' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trade' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'nullable', 'boolean'],
            'insurance_status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::INSURANCE_STATUSES),
            ],
            'canonical_filter' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::CANONICAL_FILTERS),
            ],
            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::ALLOWED_SORTS),
            ],
            'direction' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(self::SORT_DIRECTIONS),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'insurance_status.in' => 'Invalid insurance status. Must be one of: '.implode(', ', self::INSURANCE_STATUSES),
            'canonical_filter.in' => 'Invalid canonical filter. Must be one of: '.implode(', ', self::CANONICAL_FILTERS),
            'sort.in' => 'Invalid sort field. Must be one of: '.implode(', ', self::ALLOWED_SORTS),
            'direction.in' => 'Invalid sort direction. Must be one of: '.implode(', ', self::SORT_DIRECTIONS),
        ];
    }
}
