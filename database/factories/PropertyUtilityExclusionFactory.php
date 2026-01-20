<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyUtilityExclusion;
use App\Models\User;
use App\Models\UtilityType;
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
            'utility_type_id' => UtilityType::factory(),
            'reason' => $this->faker->sentence(),
            'created_by' => null,
        ];
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

    /**
     * Create an exclusion for water utility.
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
     * Create an exclusion for electric utility.
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
}
