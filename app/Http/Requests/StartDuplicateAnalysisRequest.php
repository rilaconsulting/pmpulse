<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDuplicateAnalysisRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'threshold' => ['sometimes', 'numeric', 'min:0.1', 'max:1.0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'threshold.numeric' => 'The similarity threshold must be a number.',
            'threshold.min' => 'The similarity threshold must be at least 0.1.',
            'threshold.max' => 'The similarity threshold cannot exceed 1.0.',
            'limit.integer' => 'The result limit must be an integer.',
            'limit.min' => 'The result limit must be at least 1.',
            'limit.max' => 'The result limit cannot exceed 500.',
        ];
    }
}
