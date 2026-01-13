<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) $this->faker->unique()->numberBetween(10000, 99999),
            'company_name' => $this->faker->company(),
            'contact_name' => $this->faker->name(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address_street' => $this->faker->streetAddress(),
            'address_city' => $this->faker->city(),
            'address_state' => $this->faker->stateAbbr(),
            'address_zip' => $this->faker->postcode(),
            'vendor_type' => $this->faker->randomElement(['contractor', 'supplier', 'service']),
            'vendor_trades' => $this->faker->randomElement([
                'Plumbing',
                'Electrical',
                'HVAC',
                'Landscaping',
                'Plumbing, HVAC',
                'General Maintenance',
                'Roofing',
                'Painting',
            ]),
            'workers_comp_expires' => $this->faker->dateTimeBetween('now', '+2 years'),
            'liability_ins_expires' => $this->faker->dateTimeBetween('now', '+2 years'),
            'auto_ins_expires' => $this->faker->optional(0.7)->dateTimeBetween('now', '+2 years'),
            'state_lic_expires' => $this->faker->optional(0.5)->dateTimeBetween('now', '+2 years'),
            'do_not_use' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the vendor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the vendor should not be used.
     */
    public function doNotUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'do_not_use' => true,
        ]);
    }

    /**
     * Create a vendor with expired insurance.
     */
    public function withExpiredInsurance(): static
    {
        return $this->state(fn (array $attributes) => [
            'workers_comp_expires' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
            'liability_ins_expires' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /**
     * Create a vendor with expired license.
     */
    public function withExpiredLicense(): static
    {
        return $this->state(fn (array $attributes) => [
            'state_lic_expires' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /**
     * Create a vendor with a specific trade.
     */
    public function withTrade(string $trade): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_trades' => $trade,
        ]);
    }

    /**
     * Create a vendor with a specific external ID.
     */
    public function withExternalId(string $externalId): static
    {
        return $this->state(fn (array $attributes) => [
            'external_id' => $externalId,
        ]);
    }

    /**
     * Create a vendor as a duplicate of another vendor.
     */
    public function duplicateOf(Vendor $canonicalVendor): static
    {
        return $this->state(fn (array $attributes) => [
            'canonical_vendor_id' => $canonicalVendor->id,
            'company_name' => $canonicalVendor->company_name, // Same company
        ]);
    }

    /**
     * Create a vendor with a specific canonical vendor ID.
     */
    public function withCanonicalVendorId(string $canonicalVendorId): static
    {
        return $this->state(fn (array $attributes) => [
            'canonical_vendor_id' => $canonicalVendorId,
        ]);
    }
}
