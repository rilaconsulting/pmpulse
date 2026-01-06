<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UtilityExpense>
 */
class UtilityExpenseFactory extends Factory
{
    protected $model = UtilityExpense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $utilityTypes = array_keys(UtilityAccount::DEFAULT_UTILITY_TYPES);
        $expenseDate = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'property_id' => Property::factory(),
            'utility_type' => $this->faker->randomElement($utilityTypes),
            'expense_date' => $expenseDate,
            'period_start' => (clone $expenseDate)->modify('-1 month'),
            'period_end' => $expenseDate,
            'amount' => $this->faker->randomFloat(2, 50, 1500),
            'vendor_name' => $this->faker->company(),
            'description' => $this->faker->optional()->sentence(),
            'external_expense_id' => $this->faker->unique()->uuid(),
        ];
    }

    /**
     * Create a water expense.
     */
    public function water(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'water',
            'vendor_name' => 'City Water Department',
        ]);
    }

    /**
     * Create an electric expense.
     */
    public function electric(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'electric',
            'vendor_name' => 'PG&E',
        ]);
    }

    /**
     * Create a gas expense.
     */
    public function gas(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'gas',
            'vendor_name' => 'Gas Company',
        ]);
    }

    /**
     * Create a garbage expense.
     */
    public function garbage(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'garbage',
            'vendor_name' => 'Waste Management',
        ]);
    }

    /**
     * Create a sewer expense.
     */
    public function sewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_type' => 'sewer',
            'vendor_name' => 'City Sewer Authority',
        ]);
    }

    /**
     * Create expense for a specific month.
     */
    public function forMonth(int $year, int $month): static
    {
        $start = new \DateTime("{$year}-{$month}-01");
        $end = (clone $start)->modify('last day of this month');

        return $this->state(fn (array $attributes) => [
            'expense_date' => $end,
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    /**
     * Associate with a specific property.
     */
    public function forProperty(Property $property): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => $property->id,
        ]);
    }

    /**
     * Associate with a specific utility account.
     */
    public function forAccount(UtilityAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'utility_account_id' => $account->id,
            'gl_account_number' => $account->gl_account_number,
            'utility_type' => $account->utility_type,
        ]);
    }
}
