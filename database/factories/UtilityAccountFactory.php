<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityAccount;
use App\Models\UtilityType;
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
        return [
            'gl_account_number' => $this->faker->unique()->numerify('6###'),
            'gl_account_name' => $this->faker->words(3, true),
            'utility_type_id' => UtilityType::factory(),
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
     * Set a specific utility type by ID.
     */
    public function forUtilityType(UtilityType $utilityType): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type_id' => $utilityType->id,
        ]);
    }

    /**
     * Create a water utility account.
     */
    public function water(): static
    {
        return $this->state(function (array $attributes) {
            $waterType = UtilityType::findByKey('water') ?? UtilityType::factory()->water()->create();

            return [
                'utility_type_id' => $waterType->id,
                'gl_account_name' => 'Water Expense',
            ];
        });
    }

    /**
     * Create an electric utility account.
     */
    public function electric(): static
    {
        return $this->state(function (array $attributes) {
            $electricType = UtilityType::findByKey('electric') ?? UtilityType::factory()->electric()->create();

            return [
                'utility_type_id' => $electricType->id,
                'gl_account_name' => 'Electric Expense',
            ];
        });
    }

    /**
     * Create a gas utility account.
     */
    public function gas(): static
    {
        return $this->state(function (array $attributes) {
            $gasType = UtilityType::findByKey('gas') ?? UtilityType::factory()->gas()->create();

            return [
                'utility_type_id' => $gasType->id,
                'gl_account_name' => 'Gas Expense',
            ];
        });
    }

    /**
     * Create a garbage utility account.
     */
    public function garbage(): static
    {
        return $this->state(function (array $attributes) {
            $garbageType = UtilityType::findByKey('garbage') ?? UtilityType::factory()->garbage()->create();

            return [
                'utility_type_id' => $garbageType->id,
                'gl_account_name' => 'Garbage Expense',
            ];
        });
    }

    /**
     * Create a sewer utility account.
     */
    public function sewer(): static
    {
        return $this->state(function (array $attributes) {
            $sewerType = UtilityType::findByKey('sewer') ?? UtilityType::factory()->sewer()->create();

            return [
                'utility_type_id' => $sewerType->id,
                'gl_account_name' => 'Sewer Expense',
            ];
        });
    }
}
