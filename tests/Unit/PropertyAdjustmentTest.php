<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->property = Property::factory()->create([
            'unit_count' => 10,
            'total_sqft' => 5000,
        ]);
    }

    // ==================== Relationship Tests ====================

    public function test_belongs_to_property(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertInstanceOf(Property::class, $adjustment->property);
        $this->assertEquals($this->property->id, $adjustment->property->id);
    }

    public function test_belongs_to_creator(): void
    {
        $user = User::factory()->create();

        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $adjustment->creator);
        $this->assertEquals($user->id, $adjustment->creator->id);
    }

    public function test_creator_is_null_when_no_created_by(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertNull($adjustment->creator);
    }

    // ==================== isPermanent Tests ====================

    public function test_is_permanent_returns_true_when_no_end_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'effective_to' => null,
            'reason' => 'Permanent adjustment',
        ]);

        $this->assertTrue($adjustment->isPermanent());
    }

    public function test_is_permanent_returns_false_when_has_end_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'effective_to' => now()->addMonths(3),
            'reason' => 'Temporary adjustment',
        ]);

        $this->assertFalse($adjustment->isPermanent());
    }

    // ==================== isActiveOn Tests ====================

    public function test_is_active_on_returns_true_for_current_date_permanent(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'effective_to' => null,
            'reason' => 'Test',
        ]);

        $this->assertTrue($adjustment->isActiveOn());
    }

    public function test_is_active_on_returns_false_before_start_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->addDays(5),
            'effective_to' => null,
            'reason' => 'Test',
        ]);

        $this->assertFalse($adjustment->isActiveOn());
    }

    public function test_is_active_on_returns_false_after_end_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->subDays(5),
            'reason' => 'Test',
        ]);

        $this->assertFalse($adjustment->isActiveOn());
    }

    public function test_is_active_on_returns_true_on_start_date(): void
    {
        $today = Carbon::today();

        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => $today,
            'effective_to' => null,
            'reason' => 'Test',
        ]);

        $this->assertTrue($adjustment->isActiveOn($today));
    }

    public function test_is_active_on_returns_true_on_end_date(): void
    {
        $today = Carbon::today();

        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => $today->copy()->subDays(5),
            'effective_to' => $today,
            'reason' => 'Test',
        ]);

        $this->assertTrue($adjustment->isActiveOn($today));
    }

    public function test_is_active_on_checks_specific_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => Carbon::create(2025, 1, 1),
            'effective_to' => Carbon::create(2025, 6, 30),
            'reason' => 'First half of 2025',
        ]);

        // During active period
        $this->assertTrue($adjustment->isActiveOn(Carbon::create(2025, 3, 15)));

        // Before active period
        $this->assertFalse($adjustment->isActiveOn(Carbon::create(2024, 12, 31)));

        // After active period
        $this->assertFalse($adjustment->isActiveOn(Carbon::create(2025, 7, 1)));
    }

    // ==================== Accessor Tests ====================

    public function test_field_label_accessor_returns_label(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertEquals('Unit Count', $adjustment->field_label);
    }

    public function test_field_label_accessor_returns_field_name_for_unknown(): void
    {
        $adjustment = new PropertyAdjustment([
            'field_name' => 'unknown_field',
        ]);

        $this->assertEquals('unknown_field', $adjustment->field_label);
    }

    public function test_typed_adjusted_value_accessor_returns_integer(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertIsInt($adjustment->typed_adjusted_value);
        $this->assertEquals(20, $adjustment->typed_adjusted_value);
    }

    public function test_typed_adjusted_value_accessor_returns_float_for_decimal(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'market_rent',
            'original_value' => '1000',
            'adjusted_value' => '1500.50',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertIsFloat($adjustment->typed_adjusted_value);
        $this->assertEquals(1500.50, $adjustment->typed_adjusted_value);
    }

    public function test_typed_original_value_accessor_returns_integer(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertIsInt($adjustment->typed_original_value);
        $this->assertEquals(10, $adjustment->typed_original_value);
    }

    public function test_typed_original_value_accessor_returns_null_when_null(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => null,
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Test',
        ]);

        $this->assertNull($adjustment->typed_original_value);
    }

    // ==================== Scope Tests ====================

    public function test_scope_active_on_filters_by_date(): void
    {
        $today = Carbon::today();

        // Active adjustment
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => $today->copy()->subDays(5),
            'effective_to' => $today->copy()->addDays(5),
            'reason' => 'Active',
        ]);

        // Expired adjustment
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => $today->copy()->subDays(10),
            'effective_to' => $today->copy()->subDays(5),
            'reason' => 'Expired',
        ]);

        // Future adjustment
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'rentable_units',
            'original_value' => '10',
            'adjusted_value' => '8',
            'effective_from' => $today->copy()->addDays(5),
            'reason' => 'Future',
        ]);

        $active = PropertyAdjustment::activeOn($today)->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->reason);
    }

    public function test_scope_active_on_includes_permanent_adjustments(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'effective_to' => null,
            'reason' => 'Permanent',
        ]);

        $active = PropertyAdjustment::activeOn()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Permanent', $active->first()->reason);
    }

    public function test_scope_for_field_filters_by_field_name(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'reason' => 'Unit count',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => now(),
            'reason' => 'Sqft',
        ]);

        $unitCountAdjustments = PropertyAdjustment::forField('unit_count')->get();

        $this->assertCount(1, $unitCountAdjustments);
        $this->assertEquals('Unit count', $unitCountAdjustments->first()->reason);
    }

    public function test_scope_permanent_filters_null_effective_to(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'effective_to' => null,
            'reason' => 'Permanent',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => now(),
            'effective_to' => now()->addMonths(3),
            'reason' => 'Temporary',
        ]);

        $permanent = PropertyAdjustment::permanent()->get();

        $this->assertCount(1, $permanent);
        $this->assertEquals('Permanent', $permanent->first()->reason);
    }

    public function test_scope_date_ranged_filters_non_null_effective_to(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'effective_to' => null,
            'reason' => 'Permanent',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => now(),
            'effective_to' => now()->addMonths(3),
            'reason' => 'Temporary',
        ]);

        $dateRanged = PropertyAdjustment::dateRanged()->get();

        $this->assertCount(1, $dateRanged);
        $this->assertEquals('Temporary', $dateRanged->first()->reason);
    }

    // ==================== Static Method Tests ====================

    public function test_get_validation_rules_returns_integer_rules(): void
    {
        $rules = PropertyAdjustment::getValidationRules('unit_count');

        $this->assertContains('required', $rules);
        $this->assertContains('integer', $rules);
        $this->assertContains('min:0', $rules);
    }

    public function test_get_validation_rules_returns_decimal_rules(): void
    {
        $rules = PropertyAdjustment::getValidationRules('market_rent');

        $this->assertContains('required', $rules);
        $this->assertContains('numeric', $rules);
        $this->assertContains('min:0', $rules);
    }

    public function test_get_validation_rules_returns_string_for_unknown_field(): void
    {
        $rules = PropertyAdjustment::getValidationRules('unknown_field');

        $this->assertEquals(['string'], $rules);
    }

    public function test_get_adjustable_field_names_returns_all_field_names(): void
    {
        $fieldNames = PropertyAdjustment::getAdjustableFieldNames();

        $this->assertContains('unit_count', $fieldNames);
        $this->assertContains('total_sqft', $fieldNames);
        $this->assertContains('market_rent', $fieldNames);
        $this->assertContains('rentable_units', $fieldNames);
        $this->assertCount(4, $fieldNames);
    }

    // ==================== Date Casting Tests ====================

    public function test_effective_from_is_cast_to_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => '2025-06-15',
            'reason' => 'Test',
        ]);

        $this->assertInstanceOf(Carbon::class, $adjustment->effective_from);
        $this->assertEquals('2025-06-15', $adjustment->effective_from->toDateString());
    }

    public function test_effective_to_is_cast_to_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-06-30',
            'reason' => 'Test',
        ]);

        $this->assertInstanceOf(Carbon::class, $adjustment->effective_to);
        $this->assertEquals('2025-06-30', $adjustment->effective_to->toDateString());
    }

    // ==================== Combined Scope Tests ====================

    public function test_scopes_can_be_chained(): void
    {
        $today = Carbon::today();

        // Active permanent unit_count
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => $today->copy()->subDays(5),
            'effective_to' => null,
            'reason' => 'Active permanent unit_count',
        ]);

        // Active date-ranged unit_count
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '25',
            'effective_from' => $today->copy()->subDays(5),
            'effective_to' => $today->copy()->addDays(5),
            'reason' => 'Active date-ranged unit_count',
        ]);

        // Active permanent sqft
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => $today->copy()->subDays(5),
            'effective_to' => null,
            'reason' => 'Active permanent sqft',
        ]);

        // Chain: active + for field + permanent
        $result = PropertyAdjustment::activeOn($today)
            ->forField('unit_count')
            ->permanent()
            ->get();

        $this->assertCount(1, $result);
        $this->assertEquals('Active permanent unit_count', $result->first()->reason);
    }
}
