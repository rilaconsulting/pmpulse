<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\PropertyFlag;
use App\Models\UtilityExpense;
use App\Services\AdjustmentService;
use App\Services\UtilityAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private UtilityAnalyticsService $service;

    private AdjustmentService $adjustmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adjustmentService = new AdjustmentService;
        $this->service = new UtilityAnalyticsService($this->adjustmentService);
    }

    public function test_get_cost_per_unit_calculates_correctly(): void
    {
        $property = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        // Create utility expenses totaling $500
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 300,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 200,
            'expense_date' => now()->startOfMonth()->addDays(10),
        ]);

        $costPerUnit = $this->service->getCostPerUnit($property, 'water', [
            'type' => 'month',
            'date' => now(),
        ]);

        // $500 / 10 units = $50 per unit
        $this->assertEquals(50.00, $costPerUnit);
    }

    public function test_get_cost_per_unit_uses_adjusted_value(): void
    {
        $property = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        // Create an adjustment to 20 units
        PropertyAdjustment::create([
            'property_id' => $property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '20',
            'effective_from' => now()->subDays(10),
            'reason' => 'Test adjustment',
        ]);

        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'electric',
            'amount' => 400,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        $costPerUnit = $this->service->getCostPerUnit($property, 'electric', [
            'type' => 'month',
            'date' => now(),
        ]);

        // $400 / 20 adjusted units = $20 per unit
        $this->assertEquals(20.00, $costPerUnit);
    }

    public function test_get_cost_per_sqft_calculates_correctly(): void
    {
        $property = Property::factory()->create([
            'total_sqft' => 10000,
            'is_active' => true,
        ]);

        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'gas',
            'amount' => 250,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        $costPerSqft = $this->service->getCostPerSqft($property, 'gas', [
            'type' => 'month',
            'date' => now(),
        ]);

        // $250 / 10000 sqft = $0.025 per sqft
        $this->assertEquals(0.0250, $costPerSqft);
    }

    public function test_get_cost_per_unit_returns_null_when_no_units(): void
    {
        $property = Property::factory()->create([
            'unit_count' => 0,
            'is_active' => true,
        ]);

        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 100,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        $costPerUnit = $this->service->getCostPerUnit($property, 'water', [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertNull($costPerUnit);
    }

    public function test_get_period_comparison_calculates_all_periods(): void
    {
        $property = Property::factory()->create(['is_active' => true]);

        // Current month expense
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 200,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        // Previous month expense
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 180,
            'expense_date' => now()->subMonth()->startOfMonth()->addDays(5),
        ]);

        $comparison = $this->service->getPeriodComparison($property, 'water', now());

        $this->assertEquals(200, $comparison['current_month']);
        $this->assertEquals(180, $comparison['previous_month']);
        $this->assertNotNull($comparison['month_change']);
        // (200 - 180) / 180 * 100 = 11.1%
        $this->assertEquals(11.1, $comparison['month_change']);
    }

    public function test_get_portfolio_average_excludes_flagged_properties(): void
    {
        // Create two active properties
        $property1 = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);
        $property2 = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        // Flag property2 as HOA (should be excluded)
        PropertyFlag::create([
            'property_id' => $property2->id,
            'flag_type' => 'hoa',
            'reason' => 'Test',
        ]);

        // Add expenses to both
        UtilityExpense::factory()->create([
            'property_id' => $property1->id,
            'utility_type' => 'water',
            'amount' => 100,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
            'amount' => 500,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        $average = $this->service->getPortfolioAverage('water', [
            'type' => 'month',
            'date' => now(),
        ], 'per_unit');

        // Only property1 should be included: $100 / 10 = $10
        $this->assertEquals(1, $average['property_count']);
        $this->assertEquals(10.00, $average['average']);
    }

    public function test_get_anomalies_detects_outliers(): void
    {
        // Create multiple properties with varying costs
        $properties = [];
        $costs = [100, 105, 95, 102, 98, 500]; // Last one is an outlier

        foreach ($costs as $i => $cost) {
            $property = Property::factory()->create([
                'unit_count' => 10,
                'is_active' => true,
            ]);
            $properties[] = $property;

            UtilityExpense::factory()->create([
                'property_id' => $property->id,
                'utility_type' => 'electric',
                'amount' => $cost,
                'expense_date' => now()->startOfMonth()->addDays(5),
            ]);
        }

        $anomalies = $this->service->getAnomalies('electric', [
            'type' => 'month',
            'date' => now(),
        ], 2.0, 'per_unit');

        // The property with $500 cost should be detected as an anomaly
        $this->assertNotEmpty($anomalies);
        $this->assertEquals($properties[5]->id, $anomalies[0]['property_id']);
        $this->assertEquals('high', $anomalies[0]['type']);
    }

    public function test_get_cost_breakdown_includes_all_utility_types(): void
    {
        $property = Property::factory()->create(['is_active' => true]);

        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 100,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'electric',
            'amount' => 300,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'gas',
            'amount' => 100,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        $breakdown = $this->service->getCostBreakdown($property, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(500, $breakdown['total']);

        $typeMap = collect($breakdown['breakdown'])->keyBy('type');
        $this->assertEquals(100, $typeMap['water']['cost']);
        $this->assertEquals(300, $typeMap['electric']['cost']);
        $this->assertEquals(100, $typeMap['gas']['cost']);
        $this->assertEquals(60.0, $typeMap['electric']['percentage']); // 300/500 = 60%
    }

    public function test_get_trend_returns_multiple_periods(): void
    {
        $property = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        // Create expenses for last 3 months
        for ($i = 0; $i < 3; $i++) {
            UtilityExpense::factory()->create([
                'property_id' => $property->id,
                'utility_type' => 'water',
                'amount' => 100 + ($i * 10),
                'expense_date' => now()->subMonths($i)->startOfMonth()->addDays(5),
            ]);
        }

        $trend = $this->service->getTrend($property, 'water', 3, 'month');

        $this->assertCount(3, $trend);
        // Trend should be ordered oldest to newest
        $this->assertEquals(120, $trend[0]['cost']); // 2 months ago
        $this->assertEquals(110, $trend[1]['cost']); // 1 month ago
        $this->assertEquals(100, $trend[2]['cost']); // current month
    }

    public function test_handles_quarter_period_correctly(): void
    {
        $property = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        // Create expenses for current quarter
        $quarterStart = now()->startOfQuarter();
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 100,
            'expense_date' => $quarterStart->copy()->addDays(10),
        ]);
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'water',
            'amount' => 150,
            'expense_date' => $quarterStart->copy()->addMonth()->addDays(10),
        ]);

        $costPerUnit = $this->service->getCostPerUnit($property, 'water', [
            'type' => 'quarter',
            'date' => now(),
        ]);

        // $250 / 10 units = $25 per unit
        $this->assertEquals(25.00, $costPerUnit);
    }

    public function test_handles_year_to_date_period(): void
    {
        // Use a fixed reference date in mid-year to ensure YTD has data
        $referenceDate = Carbon::create(2025, 6, 15);

        $property = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        $yearStart = $referenceDate->copy()->startOfYear();
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'electric',
            'amount' => 200,
            'expense_date' => $yearStart->copy()->addMonth(), // Feb
        ]);
        UtilityExpense::factory()->create([
            'property_id' => $property->id,
            'utility_type' => 'electric',
            'amount' => 300,
            'expense_date' => $yearStart->copy()->addMonths(3), // April
        ]);

        $costPerUnit = $this->service->getCostPerUnit($property, 'electric', [
            'type' => 'ytd',
            'date' => $referenceDate,
        ]);

        // $500 / 10 units = $50 per unit
        $this->assertEquals(50.00, $costPerUnit);
    }
}
