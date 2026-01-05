<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PropertyFlag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlagRequest extends FormRequest
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
            'flag_type' => [
                'required',
                'string',
                Rule::in(array_keys(PropertyFlag::FLAG_TYPES)),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
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
            'flag_type.required' => 'Please select a flag type.',
            'flag_type.in' => 'Please select a valid flag type.',
            'reason.max' => 'The reason cannot exceed 500 characters.',
        ];
    }
}
