<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UtilityType>
 */
class UtilityTypeFactory extends Factory
{
    protected $model = UtilityType::class;

    /**
     * Available icons for testing.
     */
    private const ICONS = [
        'BeakerIcon',
        'BoltIcon',
        'FireIcon',
        'TrashIcon',
        'SparklesIcon',
        'CubeIcon',
        'CloudIcon',
        'SunIcon',
    ];

    /**
     * Available color schemes for testing.
     */
    private const COLOR_SCHEMES = [
        'blue',
        'yellow',
        'orange',
        'gray',
        'green',
        'purple',
        'red',
        'cyan',
        'slate',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'label' => $this->faker->words(2, true),
            'icon' => $this->faker->randomElement(self::ICONS),
            'color_scheme' => $this->faker->randomElement(self::COLOR_SCHEMES),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_system' => false,
        ];
    }

    /**
     * Indicate that this is a system type.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Create a water utility type.
     */
    public function water(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'water',
            'label' => 'Water',
            'icon' => 'BeakerIcon',
            'color_scheme' => 'blue',
            'sort_order' => 1,
            'is_system' => true,
        ]);
    }

    /**
     * Create an electric utility type.
     */
    public function electric(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'electric',
            'label' => 'Electric',
            'icon' => 'BoltIcon',
            'color_scheme' => 'yellow',
            'sort_order' => 2,
            'is_system' => true,
        ]);
    }

    /**
     * Create a gas utility type.
     */
    public function gas(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'gas',
            'label' => 'Gas',
            'icon' => 'FireIcon',
            'color_scheme' => 'orange',
            'sort_order' => 3,
            'is_system' => true,
        ]);
    }

    /**
     * Create a garbage utility type.
     */
    public function garbage(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'garbage',
            'label' => 'Garbage',
            'icon' => 'TrashIcon',
            'color_scheme' => 'gray',
            'sort_order' => 4,
            'is_system' => true,
        ]);
    }

    /**
     * Create a sewer utility type.
     */
    public function sewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'sewer',
            'label' => 'Sewer',
            'icon' => 'SparklesIcon',
            'color_scheme' => 'green',
            'sort_order' => 5,
            'is_system' => true,
        ]);
    }

    /**
     * Create an "other" utility type.
     */
    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'other',
            'label' => 'Other',
            'icon' => 'CubeIcon',
            'color_scheme' => 'purple',
            'sort_order' => 100,
            'is_system' => true,
        ]);
    }

    /**
     * Create a custom (non-system) utility type.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => false,
        ]);
    }

    /**
     * Set a specific sort order.
     */
    public function sortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
