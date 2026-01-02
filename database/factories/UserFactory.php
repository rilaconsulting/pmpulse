<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'api_token' => Str::random(60),
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'google_id' => null,
            'is_active' => true,
            'force_sso' => false,
            'role_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user uses Google SSO.
     */
    public function googleSso(): static
    {
        return $this->state(fn (array $attributes) => [
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => fake()->uuid(),
            'password' => Hash::make(bin2hex(random_bytes(32))),
            'force_sso' => true,
        ]);
    }

    /**
     * Assign an admin role to the user.
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            $role = Role::firstOrCreate(
                ['name' => Role::ADMIN],
                [
                    'description' => 'Full administrative access',
                    'permissions' => ['*'],
                ]
            );

            return [
                'role_id' => $role->id,
            ];
        });
    }

    /**
     * Assign a viewer role to the user.
     */
    public function viewer(): static
    {
        return $this->state(function (array $attributes) {
            $role = Role::firstOrCreate(
                ['name' => Role::VIEWER],
                [
                    'description' => 'Read-only access to dashboards',
                    'permissions' => ['dashboard.view', 'reports.view'],
                ]
            );

            return [
                'role_id' => $role->id,
            ];
        });
    }

    /**
     * Assign a specific role to the user.
     */
    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $role->id,
        ]);
    }
}
