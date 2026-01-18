<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityFormattingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UtilityFormattingRule>
 */
class UtilityFormattingRuleFactory extends Factory
{
    protected $model = UtilityFormattingRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $utilityTypes = array_keys(UtilityAccount::DEFAULT_UTILITY_TYPES);
        $operators = array_keys(UtilityFormattingRule::OPERATORS);

        return [
            'utility_type' => $this->faker->randomElement($utilityTypes),
            'name' => $this->faker->words(3, true),
            'operator' => $this->faker->randomElement($operators),
            'threshold' => $this->faker->randomFloat(2, 5, 50),
            'color' => $this->faker->hexColor(),
            'background_color' => $this->faker->optional(0.5)->hexColor(),
            'priority' => $this->faker->numberBetween(0, 100),
            'enabled' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the rule is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Create an increase percent rule.
     */
    public function increasePercent(float $threshold = 20.0): static
    {
        return $this->state(fn (array $attributes) => [
            'operator' => 'increase_percent',
            'threshold' => $threshold,
        ]);
    }

    /**
     * Create a decrease percent rule.
     */
    public function decreasePercent(float $threshold = 20.0): static
    {
        return $this->state(fn (array $attributes) => [
            'operator' => 'decrease_percent',
            'threshold' => $threshold,
        ]);
    }

    /**
     * Create a rule for water utility.
     */
    public function water(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'water',
        ]);
    }

    /**
     * Create a rule for electric utility.
     */
    public function electric(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'electric',
        ]);
    }

    /**
     * Create a high priority rule.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 100,
        ]);
    }
}
