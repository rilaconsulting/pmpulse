<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\UtilityAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUtilityNoteRequest extends FormRequest
{
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
            'utility_type' => ['required', 'string', Rule::in(array_keys(UtilityAccount::getUtilityTypeOptions()))],
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
