<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityNote;
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
        $utilityTypes = array_keys(UtilityAccount::DEFAULT_UTILITY_TYPES);

        return [
            'property_id' => Property::factory(),
            'utility_type' => $this->faker->randomElement($utilityTypes),
            'note' => $this->faker->paragraph(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Create a note for water utility.
     */
    public function water(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'water',
        ]);
    }

    /**
     * Create a note for electric utility.
     */
    public function electric(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'electric',
        ]);
    }
}
