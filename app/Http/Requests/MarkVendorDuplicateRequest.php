<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarkVendorDuplicateRequest extends FormRequest
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
            'canonical_vendor_id' => ['required', 'uuid', 'exists:vendors,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateNotSelf($validator);
            $this->validateNoDuplicatesLinked($validator);
        });
    }

    /**
     * Validate that a vendor cannot be marked as a duplicate of itself.
     */
    private function validateNotSelf(Validator $validator): void
    {
        $vendor = $this->route('vendor');
        $canonicalVendorId = $this->input('canonical_vendor_id');

        if ($vendor && $canonicalVendorId && $vendor->id === $canonicalVendorId) {
            $validator->errors()->add(
                'canonical_vendor_id',
                'A vendor cannot be marked as a duplicate of itself.'
            );
        }
    }

    /**
     * Validate that the vendor doesn't have duplicates linked to it.
     */
    private function validateNoDuplicatesLinked(Validator $validator): void
    {
        $vendor = $this->route('vendor');

        if ($vendor && $vendor->duplicateVendors()->exists()) {
            $validator->errors()->add(
                'canonical_vendor_id',
                'This vendor has duplicates linked to it. Reassign those duplicates first.'
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'canonical_vendor_id.required' => 'The canonical vendor ID is required.',
            'canonical_vendor_id.uuid' => 'The canonical vendor ID must be a valid UUID.',
            'canonical_vendor_id.exists' => 'The specified canonical vendor does not exist.',
        ];
    }
}
