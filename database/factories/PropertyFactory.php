<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) $this->faker->unique()->numberBetween(10000, 99999),
            'name' => $this->faker->company().' '.$this->faker->randomElement(['Apartments', 'Condos', 'Plaza', 'Tower', 'Heights']),
            'address_line1' => $this->faker->streetAddress(),
            'address_line2' => $this->faker->optional(0.3)->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'latitude' => $this->faker->latitude(37.0, 38.0),
            'longitude' => $this->faker->longitude(-122.5, -121.5),
            'portfolio' => $this->faker->optional()->company(),
            'portfolio_id' => $this->faker->optional()->numberBetween(1, 100),
            'property_type' => $this->faker->randomElement(['residential', 'commercial', 'mixed']),
            'year_built' => $this->faker->numberBetween(1960, 2024),
            'total_sqft' => $this->faker->numberBetween(5000, 100000),
            'county' => $this->faker->optional()->city().' County',
            'unit_count' => $this->faker->numberBetween(4, 100),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the property is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a residential property.
     */
    public function residential(): static
    {
        return $this->state(fn (array $attributes) => [
            'property_type' => 'residential',
        ]);
    }

    /**
     * Create a commercial property.
     */
    public function commercial(): static
    {
        return $this->state(fn (array $attributes) => [
            'property_type' => 'commercial',
        ]);
    }

    /**
     * Create a property without coordinates.
     */
    public function withoutCoordinates(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    /**
     * Create a property with a specific external ID.
     */
    public function withExternalId(string $externalId): static
    {
        return $this->state(fn (array $attributes) => [
            'external_id' => $externalId,
        ]);
    }
}
