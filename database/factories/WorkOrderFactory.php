<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Property;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openedAt = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'external_id' => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'property_id' => null,
            'unit_id' => null,
            'vendor_id' => null,
            'vendor_name' => null,
            'opened_at' => $openedAt,
            'closed_at' => null,
            'status' => 'open',
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'emergency']),
            'category' => $this->faker->randomElement(['plumbing', 'electrical', 'hvac', 'appliance', 'general']),
            'description' => $this->faker->sentence(),
            'amount' => null,
            'vendor_bill_amount' => null,
            'estimate_amount' => null,
            'vendor_trade' => null,
            'work_order_type' => null,
        ];
    }

    /**
     * Associate the work order with a property.
     */
    public function forProperty(Property $property): static
    {
        return $this->state(fn (array $attributes) => [
            'property_id' => $property->id,
        ]);
    }

    /**
     * Associate the work order with a unit.
     */
    public function forUnit(Unit $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_id' => $unit->id,
            'property_id' => $unit->property_id,
        ]);
    }

    /**
     * Associate the work order with a vendor.
     */
    public function forVendor(Vendor $vendor): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->company_name,
            'vendor_trade' => $vendor->vendor_trades,
        ]);
    }

    /**
     * Set the work order as completed.
     */
    public function completed(?int $daysToComplete = null): static
    {
        return $this->state(function (array $attributes) use ($daysToComplete) {
            $openedAt = $attributes['opened_at'] ?? now();
            if (is_string($openedAt)) {
                $openedAt = \Carbon\Carbon::parse($openedAt);
            } elseif ($openedAt instanceof \DateTime) {
                $openedAt = \Carbon\Carbon::instance($openedAt);
            }

            $closedAt = $daysToComplete !== null
                ? $openedAt->copy()->addDays($daysToComplete)
                : $openedAt->copy()->addDays($this->faker->numberBetween(1, 14));

            return [
                'status' => 'completed',
                'closed_at' => $closedAt,
            ];
        });
    }

    /**
     * Set the work order as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            $openedAt = $attributes['opened_at'] ?? now();
            if (is_string($openedAt)) {
                $openedAt = \Carbon\Carbon::parse($openedAt);
            } elseif ($openedAt instanceof \DateTime) {
                $openedAt = \Carbon\Carbon::instance($openedAt);
            }

            return [
                'status' => 'cancelled',
                'closed_at' => $openedAt->copy()->addDays($this->faker->numberBetween(1, 7)),
            ];
        });
    }

    /**
     * Set the work order as in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    /**
     * Set a specific priority.
     */
    public function withPriority(string $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    /**
     * Set a specific category.
     */
    public function withCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Set the amount for the work order.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    /**
     * Set the opened_at date.
     */
    public function openedAt(\DateTime|string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'opened_at' => $date,
        ]);
    }

    /**
     * Create an emergency priority work order.
     */
    public function emergency(): static
    {
        return $this->withPriority('emergency');
    }

    /**
     * Create a high priority work order.
     */
    public function highPriority(): static
    {
        return $this->withPriority('high');
    }

    /**
     * Create a low priority work order.
     */
    public function lowPriority(): static
    {
        return $this->withPriority('low');
    }
}
