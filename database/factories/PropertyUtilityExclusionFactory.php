<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyUtilityExclusion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyUtilityExclusion>
 */
class PropertyUtilityExclusionFactory extends Factory
{
    protected $model = PropertyUtilityExclusion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'utility_type' => $this->faker->randomElement(array_diff(array_keys(\App\Models\UtilityAccount::DEFAULT_UTILITY_TYPES), ['other'])),
            'reason' => $this->faker->sentence(),
            'created_by' => null,
        ];
    }

    /**
     * Set a specific utility type.
     */
    public function forUtilityType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => $type,
        ]);
    }

    /**
     * Set a specific property.
     */
    public function forProperty(Property $property): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => $property->id,
        ]);
    }

    /**
     * Set the creator.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
