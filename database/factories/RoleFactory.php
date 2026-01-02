<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Role>
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'permissions' => ['reports.view'],
        ];
    }

    /**
     * Indicate that the role is an admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::ADMIN,
            'description' => 'Full access to all features',
            'permissions' => [
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'settings.view',
                'settings.update',
                'reports.view',
                'reports.export',
                'sync.trigger',
                'sync.configure',
            ],
        ]);
    }

    /**
     * Indicate that the role is a viewer role.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => Role::VIEWER,
            'description' => 'Read-only access to dashboards',
            'permissions' => ['reports.view'],
        ]);
    }
}
