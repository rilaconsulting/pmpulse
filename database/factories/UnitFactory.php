<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) $this->faker->unique()->numberBetween(10000, 99999),
            'property_id' => Property::factory(),
            'unit_number' => (string) $this->faker->numberBetween(100, 999),
            'unit_type' => $this->faker->randomElement(['1BR', '2BR', '3BR', 'Studio']),
            'sqft' => $this->faker->numberBetween(400, 2000),
            'bedrooms' => $this->faker->numberBetween(0, 4),
            'bathrooms' => $this->faker->randomFloat(1, 1, 3),
            'status' => 'occupied',
            'market_rent' => $this->faker->randomFloat(2, 1000, 5000),
            'advertised_rent' => $this->faker->optional()->randomFloat(2, 1000, 5000),
            'is_active' => true,
            'rentable' => true,
        ];
    }

    /**
     * Indicate that the unit is vacant.
     */
    public function vacant(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'vacant',
        ]);
    }

    /**
     * Indicate that the unit is occupied.
     */
    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'occupied',
        ]);
    }

    /**
     * Indicate that the unit is not ready.
     */
    public function notReady(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'not_ready',
        ]);
    }

    /**
     * Indicate that the unit is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the unit is not rentable.
     */
    public function notRentable(): static
    {
        return $this->state(fn (array $attributes) => [
            'rentable' => false,
        ]);
    }
}
