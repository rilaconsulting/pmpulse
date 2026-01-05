<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSyncConfigurationRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_hours_enabled' => ['required', 'boolean'],
            'timezone' => ['required', 'string', 'timezone'],
            'start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'end_hour' => ['required', 'integer', 'min:1', 'max:23', 'gt:start_hour'],
            'weekdays_only' => ['required', 'boolean'],
            'business_hours_interval' => ['required', 'integer', 'min:5', 'max:60'],
            'off_hours_interval' => ['required', 'integer', 'min:15', 'max:240'],
            'full_sync_time' => ['required', 'string', 'regex:/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'],
        ];
    }
}
