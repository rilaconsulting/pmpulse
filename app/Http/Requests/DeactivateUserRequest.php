<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateUserRequest extends FormRequest
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
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            if (! $user instanceof User) {
                return;
            }

            // Prevent self-deactivation
            if ($user->id === $this->user()->id) {
                $validator->errors()->add(
                    'user',
                    'Cannot deactivate your own account.'
                );
            }

            // Prevent deactivating the last admin
            $userService = app(UserService::class);
            if ($userService->isLastActiveAdmin($user)) {
                $validator->errors()->add(
                    'user',
                    'Cannot deactivate the last admin user.'
                );
            }
        });
    }
}
