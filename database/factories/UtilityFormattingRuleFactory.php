<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UtilityFormattingRule;
use App\Models\UtilityType;
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
        $operators = array_keys(UtilityFormattingRule::OPERATORS);

        return [
            'utility_type_id' => UtilityType::factory(),
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
     * Set a specific utility type.
     */
    public function forUtilityType(UtilityType $utilityType): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type_id' => $utilityType->id,
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
        return $this->state(function (array $attributes) {
            $waterType = UtilityType::findByKey('water') ?? UtilityType::factory()->water()->create();

            return [
                'utility_type_id' => $waterType->id,
            ];
        });
    }

    /**
     * Create a rule for electric utility.
     */
    public function electric(): static
    {
        return $this->state(function (array $attributes) {
            $electricType = UtilityType::findByKey('electric') ?? UtilityType::factory()->electric()->create();

            return [
                'utility_type_id' => $electricType->id,
            ];
        });
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
