<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Models\UtilityFormattingRule;
use App\Models\UtilityType;
use App\Services\UtilityFormattingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityFormattingServiceTest extends TestCase
{
    use RefreshDatabase;

    private UtilityFormattingService $service;

    private User $user;

    private UtilityType $waterType;

    private UtilityType $electricType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UtilityFormattingService;
        $this->user = User::factory()->create();

        // Get utility types (seeded by migration)
        $this->waterType = UtilityType::where('key', 'water')->firstOrFail();
        $this->electricType = UtilityType::where('key', 'electric')->firstOrFail();
    }

    // ==================== getFormatting Tests ====================

    public function test_get_formatting_returns_null_when_no_rules_exist(): void
    {
        $result = $this->service->getFormatting('water', 100.0, 80.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_returns_null_for_null_value(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getFormatting('water', null, 100.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_returns_null_for_null_average(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getFormatting('water', 100.0, null);

        $this->assertNull($result);
    }

    public function test_get_formatting_returns_null_for_zero_average(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getFormatting('water', 100.0, 0.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_returns_null_for_negative_average(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'created_by' => $this->user->id,
        ]);

        $result = $this->service->getFormatting('water', 100.0, -50.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_returns_formatting_when_rule_matches(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
            'background_color' => '#FFEEEE',
            'name' => 'High Water Alert',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // 30% increase should match
        $result = $this->service->getFormatting('water', 130.0, 100.0);

        $this->assertNotNull($result);
        $this->assertEquals('#FF0000', $result['color']);
        $this->assertEquals('#FFEEEE', $result['background_color']);
        $this->assertEquals('High Water Alert', $result['rule_name']);
    }

    public function test_get_formatting_returns_null_when_no_rule_matches(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 50,
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // 20% increase should not match 50% threshold
        $result = $this->service->getFormatting('water', 120.0, 100.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_ignores_disabled_rules(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'enabled' => false,
            'created_by' => $this->user->id,
        ]);

        // Would match if rule was enabled
        $result = $this->service->getFormatting('water', 150.0, 100.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_ignores_rules_for_other_utility_types(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->electricType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // Requesting water formatting but rule is for electric
        $result = $this->service->getFormatting('water', 150.0, 100.0);

        $this->assertNull($result);
    }

    public function test_get_formatting_respects_priority_ordering(): void
    {
        // Low priority rule - more permissive
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'priority' => 10,
            'color' => '#YELLOW',
            'name' => 'Low Priority',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // High priority rule - more restrictive
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 30,
            'priority' => 100,
            'color' => '#RED',
            'name' => 'High Priority',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // Clear cache to ensure fresh query
        $this->service->clearCache();

        // 50% increase matches both rules
        // High priority should be checked first and match
        $result = $this->service->getFormatting('water', 150.0, 100.0);

        $this->assertNotNull($result);
        $this->assertEquals('High Priority', $result['rule_name']);
    }

    public function test_get_formatting_returns_first_matching_rule_by_priority(): void
    {
        // High priority but doesn't match
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 50, // Won't match 25% increase
            'priority' => 100,
            'color' => '#RED',
            'name' => 'High Priority',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // Low priority but matches
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'priority' => 10,
            'color' => '#YELLOW',
            'name' => 'Low Priority',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        // 25% increase - doesn't match high priority (50%) but matches low priority (10%)
        $result = $this->service->getFormatting('water', 125.0, 100.0);

        $this->assertNotNull($result);
        $this->assertEquals('Low Priority', $result['rule_name']);
    }

    // ==================== applyFormattingToProperty Tests ====================

    public function test_apply_formatting_to_property_adds_formatting_metadata(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#FF0000',
            'background_color' => '#FFEEEE',
            'name' => 'Alert Rule',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $propertyData = [
            'property_id' => 'test-id',
            'property_name' => 'Test Property',
            'current_month' => 150.0,
            'prev_month' => 130.0,
            'prev_3_months' => 120.0,
            'prev_12_months' => 100.0, // Average for comparison
        ];

        $result = $this->service->applyFormattingToProperty($propertyData, 'water');

        $this->assertArrayHasKey('formatting', $result);
        $this->assertArrayHasKey('current_month', $result['formatting']);
        $this->assertEquals('#FF0000', $result['formatting']['current_month']['color']);
    }

    public function test_apply_formatting_applies_to_all_columns(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#FF0000',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $propertyData = [
            'current_month' => 150.0,
            'prev_month' => 140.0,
            'prev_3_months' => 130.0,
            'prev_12_months' => 100.0,
        ];

        $result = $this->service->applyFormattingToProperty($propertyData, 'water');

        // All three columns should have formatting (all exceed 10% threshold)
        $this->assertArrayHasKey('current_month', $result['formatting']);
        $this->assertArrayHasKey('prev_month', $result['formatting']);
        $this->assertArrayHasKey('prev_3_months', $result['formatting']);
    }

    public function test_apply_formatting_only_formats_matching_columns(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 30,
            'color' => '#FF0000',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $propertyData = [
            'current_month' => 150.0, // 50% increase - matches
            'prev_month' => 110.0,    // 10% increase - doesn't match
            'prev_3_months' => 105.0, // 5% increase - doesn't match
            'prev_12_months' => 100.0,
        ];

        $result = $this->service->applyFormattingToProperty($propertyData, 'water');

        // Only current_month should have formatting
        $this->assertArrayHasKey('current_month', $result['formatting']);
        $this->assertArrayNotHasKey('prev_month', $result['formatting']);
        $this->assertArrayNotHasKey('prev_3_months', $result['formatting']);
    }

    public function test_apply_formatting_preserves_original_data(): void
    {
        $propertyData = [
            'property_id' => 'test-id',
            'property_name' => 'Test Property',
            'current_month' => 150.0,
            'prev_12_months' => 100.0,
        ];

        $result = $this->service->applyFormattingToProperty($propertyData, 'water');

        $this->assertEquals('test-id', $result['property_id']);
        $this->assertEquals('Test Property', $result['property_name']);
        $this->assertEquals(150.0, $result['current_month']);
        $this->assertEquals(100.0, $result['prev_12_months']);
    }

    public function test_apply_formatting_handles_null_average(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $propertyData = [
            'current_month' => 150.0,
            'prev_12_months' => null, // No average
        ];

        $result = $this->service->applyFormattingToProperty($propertyData, 'water');

        // No formatting should be added when average is null
        $this->assertArrayNotHasKey('formatting', $result);
    }

    public function test_apply_formatting_handles_null_values(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $propertyData = [
            'current_month' => null,
            'prev_month' => 150.0,
            'prev_3_months' => null,
            'prev_12_months' => 100.0,
        ];

        $result = $this->service->applyFormattingToProperty($propertyData, 'water');

        // Only prev_month should have formatting
        $this->assertArrayHasKey('prev_month', $result['formatting']);
        $this->assertArrayNotHasKey('current_month', $result['formatting']);
        $this->assertArrayNotHasKey('prev_3_months', $result['formatting']);
    }

    // ==================== applyFormattingToComparison Tests ====================

    public function test_apply_formatting_to_comparison_processes_all_properties(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#FF0000',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $comparisonData = [
            'properties' => [
                ['current_month' => 150.0, 'prev_12_months' => 100.0],
                ['current_month' => 120.0, 'prev_12_months' => 100.0],
                ['current_month' => 105.0, 'prev_12_months' => 100.0], // Below threshold
            ],
        ];

        $result = $this->service->applyFormattingToComparison($comparisonData, 'water');

        // First two properties should have formatting
        $this->assertArrayHasKey('formatting', $result['properties'][0]);
        $this->assertArrayHasKey('formatting', $result['properties'][1]);
        // Third property is below threshold, no formatting
        $this->assertArrayNotHasKey('formatting', $result['properties'][2]);
    }

    // ==================== Cache Tests ====================

    public function test_clear_cache_allows_fresh_rule_fetch(): void
    {
        // Initial call with no rules
        $result1 = $this->service->getFormatting('water', 150.0, 100.0);
        $this->assertNull($result1);

        // Create a rule
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#FF0000',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // Without clearing cache, should still be null
        $result2 = $this->service->getFormatting('water', 150.0, 100.0);
        $this->assertNull($result2);

        // Clear cache and try again
        $this->service->clearCache();
        $result3 = $this->service->getFormatting('water', 150.0, 100.0);
        $this->assertNotNull($result3);
    }

    public function test_cache_is_per_utility_type(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#BLUE',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->electricType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#YELLOW',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        $waterResult = $this->service->getFormatting('water', 150.0, 100.0);
        $electricResult = $this->service->getFormatting('electric', 150.0, 100.0);

        $this->assertEquals('#BLUE', $waterResult['color']);
        $this->assertEquals('#YELLOW', $electricResult['color']);
    }

    // ==================== Decrease Percent Tests ====================

    public function test_get_formatting_with_decrease_rule(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'decrease_percent',
            'threshold' => 20,
            'color' => '#00FF00',
            'name' => 'Cost Decrease',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        // 25% decrease should match
        $result = $this->service->getFormatting('water', 75.0, 100.0);

        $this->assertNotNull($result);
        $this->assertEquals('#00FF00', $result['color']);
        $this->assertEquals('Cost Decrease', $result['rule_name']);
    }

    public function test_get_formatting_with_mixed_rules(): void
    {
        // High priority decrease rule
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'decrease_percent',
            'threshold' => 20,
            'priority' => 100,
            'color' => '#GREEN',
            'name' => 'Big Decrease',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        // Low priority increase rule
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 20,
            'priority' => 50,
            'color' => '#RED',
            'name' => 'Big Increase',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $this->service->clearCache();

        // Test increase - should match increase rule
        $increaseResult = $this->service->getFormatting('water', 130.0, 100.0);
        $this->assertEquals('Big Increase', $increaseResult['rule_name']);

        // Test decrease - should match decrease rule
        $decreaseResult = $this->service->getFormatting('water', 70.0, 100.0);
        $this->assertEquals('Big Decrease', $decreaseResult['rule_name']);
    }
}
