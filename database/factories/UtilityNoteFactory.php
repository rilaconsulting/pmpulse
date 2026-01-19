<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use App\Models\UtilityNote;
use App\Models\UtilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UtilityNote>
 */
class UtilityNoteFactory extends Factory
{
    protected $model = UtilityNote::class;

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
            'note' => $this->faker->paragraph(),
            'created_by' => User::factory(),
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
     * Create a note for water utility.
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
     * Create a note for electric utility.
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
