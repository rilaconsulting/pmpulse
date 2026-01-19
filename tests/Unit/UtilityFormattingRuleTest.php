<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Models\UtilityFormattingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityFormattingRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ==================== Evaluate Method Tests ====================

    public function test_evaluate_increase_percent_matches_when_value_exceeds_threshold(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // 25% increase (125 vs 100) should match 20% threshold
        $this->assertTrue($rule->evaluate(125.0, 100.0));
    }

    public function test_evaluate_increase_percent_matches_at_exact_threshold(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // Exactly 20% increase
        $this->assertTrue($rule->evaluate(120.0, 100.0));
    }

    public function test_evaluate_increase_percent_does_not_match_below_threshold(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // 15% increase should not match 20% threshold
        $this->assertFalse($rule->evaluate(115.0, 100.0));
    }

    public function test_evaluate_decrease_percent_matches_when_value_drops_below_threshold(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'decrease_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // 25% decrease (75 vs 100) should match 20% threshold
        $this->assertTrue($rule->evaluate(75.0, 100.0));
    }

    public function test_evaluate_decrease_percent_matches_at_exact_threshold(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'decrease_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // Exactly 20% decrease
        $this->assertTrue($rule->evaluate(80.0, 100.0));
    }

    public function test_evaluate_decrease_percent_does_not_match_above_threshold(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'decrease_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // 10% decrease should not match 20% threshold
        $this->assertFalse($rule->evaluate(90.0, 100.0));
    }

    // ==================== Edge Cases ====================

    public function test_evaluate_returns_false_for_zero_average(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // Division by zero case - should return false
        $this->assertFalse($rule->evaluate(100.0, 0.0));
    }

    public function test_evaluate_returns_false_for_negative_average(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 20,
            'created_by' => $this->user->id,
        ]);

        // Negative average case - should return false
        $this->assertFalse($rule->evaluate(100.0, -50.0));
    }

    public function test_evaluate_handles_zero_value(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'decrease_percent',
            'threshold' => 50,
            'created_by' => $this->user->id,
        ]);

        // Zero value = 100% decrease, should match 50% threshold
        $this->assertTrue($rule->evaluate(0.0, 100.0));
    }

    public function test_evaluate_handles_small_decimal_values(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 10,
            'created_by' => $this->user->id,
        ]);

        // 20% increase with small values (0.12 vs 0.10 = 20%)
        $this->assertTrue($rule->evaluate(0.12, 0.10));
    }

    public function test_evaluate_handles_large_values(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'threshold' => 25,
            'created_by' => $this->user->id,
        ]);

        // Large values with 30% increase
        $this->assertTrue($rule->evaluate(130000.0, 100000.0));
    }

    public function test_evaluate_returns_false_for_unknown_operator(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'created_by' => $this->user->id,
        ]);

        // Force an unknown operator via direct property update
        $rule->operator = 'unknown_operator';

        $this->assertFalse($rule->evaluate(150.0, 100.0));
    }

    // ==================== Scope Tests ====================

    public function test_scope_enabled_filters_disabled_rules(): void
    {
        UtilityFormattingRule::factory()->create([
            'enabled' => true,
            'name' => 'Enabled Rule',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'enabled' => false,
            'name' => 'Disabled Rule',
            'created_by' => $this->user->id,
        ]);

        $enabledRules = UtilityFormattingRule::enabled()->get();

        $this->assertCount(1, $enabledRules);
        $this->assertEquals('Enabled Rule', $enabledRules->first()->name);
    }

    public function test_scope_for_utility_type_filters_by_type(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type' => 'water',
            'name' => 'Water Rule',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'utility_type' => 'electric',
            'name' => 'Electric Rule',
            'created_by' => $this->user->id,
        ]);

        $waterRules = UtilityFormattingRule::forUtilityType('water')->get();

        $this->assertCount(1, $waterRules);
        $this->assertEquals('Water Rule', $waterRules->first()->name);
    }

    public function test_scope_by_priority_orders_descending(): void
    {
        UtilityFormattingRule::factory()->create([
            'priority' => 10,
            'name' => 'Low Priority',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'priority' => 100,
            'name' => 'High Priority',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'priority' => 50,
            'name' => 'Medium Priority',
            'created_by' => $this->user->id,
        ]);

        $rules = UtilityFormattingRule::byPriority()->get();

        $this->assertEquals('High Priority', $rules->get(0)->name);
        $this->assertEquals('Medium Priority', $rules->get(1)->name);
        $this->assertEquals('Low Priority', $rules->get(2)->name);
    }

    public function test_scopes_can_be_chained(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type' => 'water',
            'enabled' => true,
            'priority' => 100,
            'name' => 'Water High',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'utility_type' => 'water',
            'enabled' => true,
            'priority' => 50,
            'name' => 'Water Low',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'utility_type' => 'water',
            'enabled' => false,
            'priority' => 200,
            'name' => 'Water Disabled',
            'created_by' => $this->user->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'utility_type' => 'electric',
            'enabled' => true,
            'priority' => 150,
            'name' => 'Electric',
            'created_by' => $this->user->id,
        ]);

        $rules = UtilityFormattingRule::enabled()
            ->forUtilityType('water')
            ->byPriority()
            ->get();

        $this->assertCount(2, $rules);
        $this->assertEquals('Water High', $rules->get(0)->name);
        $this->assertEquals('Water Low', $rules->get(1)->name);
    }

    // ==================== Attribute Tests ====================

    public function test_operator_label_attribute_returns_human_readable_label(): void
    {
        $increaseRule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'created_by' => $this->user->id,
        ]);

        $decreaseRule = UtilityFormattingRule::factory()->create([
            'operator' => 'decrease_percent',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('Increase % over average', $increaseRule->operator_label);
        $this->assertEquals('Decrease % below average', $decreaseRule->operator_label);
    }

    public function test_operator_label_returns_raw_value_for_unknown_operator(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'operator' => 'increase_percent',
            'created_by' => $this->user->id,
        ]);

        // Force unknown operator
        $rule->operator = 'custom_operator';

        $this->assertEquals('custom_operator', $rule->operator_label);
    }

    // ==================== Relationship Tests ====================

    public function test_creator_relationship_returns_user(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $rule->creator);
        $this->assertEquals($this->user->id, $rule->creator->id);
    }

    // ==================== Constants Tests ====================

    public function test_operators_constant_contains_expected_values(): void
    {
        $this->assertArrayHasKey('increase_percent', UtilityFormattingRule::OPERATORS);
        $this->assertArrayHasKey('decrease_percent', UtilityFormattingRule::OPERATORS);
        $this->assertCount(2, UtilityFormattingRule::OPERATORS);
    }

    // ==================== Cast Tests ====================

    public function test_threshold_is_cast_to_decimal(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'threshold' => 25.555,
            'created_by' => $this->user->id,
        ]);

        $rule->refresh();

        // Should be cast to decimal with 2 places
        $this->assertEquals('25.56', $rule->threshold);
    }

    public function test_enabled_is_cast_to_boolean(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'enabled' => 1,
            'created_by' => $this->user->id,
        ]);

        $rule->refresh();

        $this->assertTrue($rule->enabled);
        $this->assertIsBool($rule->enabled);
    }

    public function test_priority_is_cast_to_integer(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'priority' => '50',
            'created_by' => $this->user->id,
        ]);

        $rule->refresh();

        $this->assertEquals(50, $rule->priority);
        $this->assertIsInt($rule->priority);
    }
}
