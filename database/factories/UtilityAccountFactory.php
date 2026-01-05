<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UtilityAccount>
 */
class UtilityAccountFactory extends Factory
{
    protected $model = UtilityAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $utilityTypes = array_keys(UtilityAccount::UTILITY_TYPES);

        return [
            'gl_account_number' => $this->faker->unique()->numerify('6###'),
            'gl_account_name' => $this->faker->words(3, true),
            'utility_type' => $this->faker->randomElement($utilityTypes),
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a water utility account.
     */
    public function water(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'water',
            'gl_account_name' => 'Water Expense',
        ]);
    }

    /**
     * Create an electric utility account.
     */
    public function electric(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'electric',
            'gl_account_name' => 'Electric Expense',
        ]);
    }

    /**
     * Create a gas utility account.
     */
    public function gas(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'gas',
            'gl_account_name' => 'Gas Expense',
        ]);
    }

    /**
     * Create a garbage utility account.
     */
    public function garbage(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'garbage',
            'gl_account_name' => 'Garbage Expense',
        ]);
    }

    /**
     * Create a sewer utility account.
     */
    public function sewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'sewer',
            'gl_account_name' => 'Sewer Expense',
        ]);
    }
}
