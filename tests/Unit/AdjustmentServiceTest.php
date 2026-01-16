<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\User;
use App\Services\AdjustmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdjustmentService $service;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdjustmentService;
        $this->property = Property::factory()->create([
            'unit_count' => 10,
            'total_sqft' => 5000,
        ]);
    }

    // ==================== getEffectiveValue Tests ====================

    public function test_get_effective_value_returns_original_when_no_adjustment(): void
    {
        $value = $this->service->getEffectiveValue($this->property, 'unit_count');

        $this->assertEquals(10, $value);
    }

    public function test_get_effective_value_returns_adjusted_value_when_active(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Test adjustment',
        ]);

        $value = $this->service->getEffectiveValue($this->property, 'unit_count');

        $this->assertEquals(20, $value);
    }

    public function test_get_effective_value_returns_original_when_adjustment_not_yet_active(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->addDays(5),
            'reason' => 'Future adjustment',
        ]);

        $value = $this->service->getEffectiveValue($this->property, 'unit_count');

        $this->assertEquals(10, $value);
    }

    public function test_get_effective_value_returns_original_when_adjustment_expired(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->subDays(5),
            'reason' => 'Expired adjustment',
        ]);

        $value = $this->service->getEffectiveValue($this->property, 'unit_count');

        $this->assertEquals(10, $value);
    }

    public function test_get_effective_value_respects_date_parameter(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => Carbon::create(2025, 1, 1),
            'effective_to' => Carbon::create(2025, 6, 30),
            'reason' => 'First half of 2025',
        ]);

        // Check during active period
        $valueDuring = $this->service->getEffectiveValue(
            $this->property,
            'unit_count',
            Carbon::create(2025, 3, 15)
        );
        $this->assertEquals(20, $valueDuring);

        // Check before active period
        $valueBefore = $this->service->getEffectiveValue(
            $this->property,
            'unit_count',
            Carbon::create(2024, 12, 15)
        );
        $this->assertEquals(10, $valueBefore);

        // Check after active period
        $valueAfter = $this->service->getEffectiveValue(
            $this->property,
            'unit_count',
            Carbon::create(2025, 7, 15)
        );
        $this->assertEquals(10, $valueAfter);
    }

    // ==================== hasAdjustment Tests ====================

    public function test_has_adjustment_returns_false_when_no_adjustment(): void
    {
        $result = $this->service->hasAdjustment($this->property, 'unit_count');

        $this->assertFalse($result);
    }

    public function test_has_adjustment_returns_true_when_active_adjustment_exists(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Test',
        ]);

        $result = $this->service->hasAdjustment($this->property, 'unit_count');

        $this->assertTrue($result);
    }

    public function test_has_adjustment_returns_false_for_different_field(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Test',
        ]);

        $result = $this->service->hasAdjustment($this->property, 'total_sqft');

        $this->assertFalse($result);
    }

    // ==================== getActiveAdjustments Tests ====================

    public function test_get_active_adjustments_returns_empty_when_none(): void
    {
        $adjustments = $this->service->getActiveAdjustments($this->property);

        $this->assertCount(0, $adjustments);
    }

    public function test_get_active_adjustments_returns_all_active_adjustments(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Unit adjustment',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => now()->subDays(3),
            'reason' => 'Sqft adjustment',
        ]);

        $adjustments = $this->service->getActiveAdjustments($this->property);

        $this->assertCount(2, $adjustments);
    }

    public function test_get_active_adjustments_excludes_expired(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(10),
            'effective_to' => now()->subDays(5),
            'reason' => 'Expired',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'total_sqft',
            'original_value' => '5000',
            'adjusted_value' => '6000',
            'effective_from' => now()->subDays(3),
            'reason' => 'Active',
        ]);

        $adjustments = $this->service->getActiveAdjustments($this->property);

        $this->assertCount(1, $adjustments);
        $this->assertEquals('total_sqft', $adjustments->first()->field_name);
    }

    // ==================== getAdjustmentHistory Tests ====================

    public function test_get_adjustment_history_returns_all_adjustments_for_field(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now()->subMonths(6),
            'effective_to' => now()->subMonths(3),
            'reason' => 'First adjustment',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subMonths(3),
            'reason' => 'Second adjustment',
        ]);

        $history = $this->service->getAdjustmentHistory($this->property, 'unit_count');

        $this->assertCount(2, $history);
    }

    public function test_get_adjustment_history_is_ordered_by_effective_from_desc(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now()->subMonths(6),
            'reason' => 'Older',
        ]);

        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subMonths(1),
            'reason' => 'Newer',
        ]);

        $history = $this->service->getAdjustmentHistory($this->property, 'unit_count');

        $this->assertEquals('Newer', $history->first()->reason);
        $this->assertEquals('Older', $history->last()->reason);
    }

    // ==================== getActiveAdjustment Tests ====================

    public function test_get_active_adjustment_returns_null_when_none(): void
    {
        $adjustment = $this->service->getActiveAdjustment($this->property, 'unit_count');

        $this->assertNull($adjustment);
    }

    public function test_get_active_adjustment_returns_most_recent_when_overlapping(): void
    {
        // Create older adjustment first
        $older = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now()->subDays(10),
            'reason' => 'Older adjustment',
        ]);
        // Manually backdate created_at
        $older->forceFill(['created_at' => now()->subDays(10)])->save();

        // Create newer adjustment second
        $newer = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Newer adjustment',
        ]);
        // Manually backdate created_at but still newer than the other
        $newer->forceFill(['created_at' => now()->subDays(5)])->save();

        $adjustment = $this->service->getActiveAdjustment($this->property, 'unit_count');

        $this->assertEquals('Newer adjustment', $adjustment->reason);
        $this->assertEquals(20, $adjustment->typed_adjusted_value);
    }

    // ==================== getOriginalValue Tests ====================

    public function test_get_original_value_returns_property_attribute(): void
    {
        $value = $this->service->getOriginalValue($this->property, 'unit_count');

        $this->assertEquals(10, $value);
    }

    public function test_get_original_value_returns_null_for_invalid_field(): void
    {
        $value = $this->service->getOriginalValue($this->property, 'invalid_field');

        $this->assertNull($value);
    }

    // ==================== getEffectiveValues Tests ====================

    public function test_get_effective_values_returns_multiple_fields(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Test',
        ]);

        $values = $this->service->getEffectiveValues(
            $this->property,
            ['unit_count', 'total_sqft']
        );

        $this->assertEquals(20, $values['unit_count']); // Adjusted
        $this->assertEquals(5000, $values['total_sqft']); // Original
    }

    // ==================== getEffectiveValuesWithMetadata Tests ====================

    public function test_get_effective_values_with_metadata_includes_adjustment_info(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(5),
            'reason' => 'Test',
        ]);

        $result = $this->service->getEffectiveValuesWithMetadata($this->property);

        // Unit count is adjusted
        $this->assertEquals(20, $result['unit_count']['value']);
        $this->assertTrue($result['unit_count']['is_adjusted']);
        $this->assertEquals(10, $result['unit_count']['original']);
        $this->assertNotNull($result['unit_count']['adjustment']);
        $this->assertEquals('Unit Count', $result['unit_count']['label']);
        $this->assertEquals('integer', $result['unit_count']['type']);

        // Total sqft is not adjusted
        $this->assertEquals(5000, $result['total_sqft']['value']);
        $this->assertFalse($result['total_sqft']['is_adjusted']);
        $this->assertNull($result['total_sqft']['original']);
        $this->assertNull($result['total_sqft']['adjustment']);
    }

    // ==================== createAdjustment Tests ====================

    public function test_create_adjustment_creates_new_adjustment(): void
    {
        $user = User::factory()->create();

        $adjustment = $this->service->createAdjustment(
            $this->property,
            'unit_count',
            25,
            now(),
            null,
            'Adding new units',
            $user->id
        );

        $this->assertDatabaseHas('property_adjustments', [
            'id' => $adjustment->id,
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'adjusted_value' => '25',
            'reason' => 'Adding new units',
            'created_by' => $user->id,
        ]);
    }

    public function test_create_adjustment_stores_original_value(): void
    {
        $adjustment = $this->service->createAdjustment(
            $this->property,
            'unit_count',
            25,
            now(),
            null,
            'Test'
        );

        $this->assertEquals('10', $adjustment->original_value);
    }

    public function test_create_adjustment_throws_for_invalid_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'invalid_field' is not adjustable");

        $this->service->createAdjustment(
            $this->property,
            'invalid_field',
            100,
            now(),
            null,
            'Test'
        );
    }

    public function test_create_adjustment_with_date_range(): void
    {
        $effectiveFrom = now();
        $effectiveTo = now()->addMonths(3);

        $adjustment = $this->service->createAdjustment(
            $this->property,
            'unit_count',
            25,
            $effectiveFrom,
            $effectiveTo,
            'Temporary adjustment'
        );

        $this->assertEquals($effectiveFrom->toDateString(), $adjustment->effective_from->toDateString());
        $this->assertEquals($effectiveTo->toDateString(), $adjustment->effective_to->toDateString());
    }

    // ==================== endAdjustment Tests ====================

    public function test_end_adjustment_sets_effective_to_to_today(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(10),
            'effective_to' => null,
            'reason' => 'Permanent adjustment',
        ]);

        $this->assertNull($adjustment->effective_to);

        $updated = $this->service->endAdjustment($adjustment);

        $this->assertNotNull($updated->effective_to);
        $this->assertEquals(now()->toDateString(), $updated->effective_to->toDateString());
    }

    public function test_end_adjustment_with_custom_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(10),
            'effective_to' => null,
            'reason' => 'Permanent adjustment',
        ]);

        $endDate = now()->addDays(5);
        $updated = $this->service->endAdjustment($adjustment, $endDate);

        $this->assertEquals($endDate->toDateString(), $updated->effective_to->toDateString());
    }

    public function test_end_adjustment_throws_when_end_date_before_start_date(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now(),
            'effective_to' => null,
            'reason' => 'Test',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('End date');

        $this->service->endAdjustment($adjustment, now()->subDays(5));
    }

    public function test_end_adjustment_allows_same_day_end(): void
    {
        $today = Carbon::today();

        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => $today,
            'effective_to' => null,
            'reason' => 'Same day adjustment',
        ]);

        $updated = $this->service->endAdjustment($adjustment, $today);

        $this->assertEquals($today->toDateString(), $updated->effective_to->toDateString());
    }

    // ==================== Edge Cases ====================

    public function test_handles_null_original_value(): void
    {
        // Market rent can be null on properties
        $property = Property::factory()->create([
            'unit_count' => 10,
            'total_sqft' => 5000,
        ]);

        // Market rent is typically null on a new property
        $this->assertNull($property->market_rent);

        $adjustment = $this->service->createAdjustment(
            $property,
            'market_rent',
            1500.00,
            now(),
            null,
            'Setting initial market rent'
        );

        $this->assertNull($adjustment->original_value);
        $this->assertEquals(1500.00, $adjustment->typed_adjusted_value);
    }

    public function test_decimal_field_type_is_handled_correctly(): void
    {
        $adjustment = $this->service->createAdjustment(
            $this->property,
            'market_rent',
            1500.50,
            now(),
            null,
            'Setting market rent'
        );

        $this->assertEquals('1500.5', $adjustment->adjusted_value);
        $this->assertIsFloat($adjustment->typed_adjusted_value);
        $this->assertEquals(1500.5, $adjustment->typed_adjusted_value);
    }
}
