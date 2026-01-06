<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\PropertyFlag;
use App\Models\UtilityAccount;
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

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        // Create utility expenses totaling $500
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 300,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
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

        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property->id,
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

        $gasAccount = UtilityAccount::factory()->create(['utility_type' => 'gas']);

        UtilityExpense::factory()->forAccount($gasAccount)->create([
            'property_id' => $property->id,
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

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
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
        // Use a fixed reference date in mid-year for predictable quarter/YTD calculations
        $referenceDate = Carbon::create(2025, 6, 15);
        $property = Property::factory()->create(['is_active' => true]);
        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        // Current month expense (June 2025)
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 200,
            'expense_date' => $referenceDate->copy()->startOfMonth()->addDays(5),
        ]);

        // Previous month expense (May 2025)
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 180,
            'expense_date' => $referenceDate->copy()->subMonth()->startOfMonth()->addDays(5),
        ]);

        // Current quarter expense (Q2 2025 - April)
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 150,
            'expense_date' => $referenceDate->copy()->startOfQuarter()->addDays(5),
        ]);

        // Previous quarter expense (Q1 2025 - February)
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 120,
            'expense_date' => $referenceDate->copy()->subQuarter()->startOfQuarter()->addMonth()->addDays(5),
        ]);

        // Previous year same period expense (June 2024)
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 160,
            'expense_date' => $referenceDate->copy()->subYear()->startOfMonth()->addDays(5),
        ]);

        $comparison = $this->service->getPeriodComparison($property, 'water', $referenceDate);

        // Month assertions
        $this->assertEquals(200, $comparison['current_month']);
        $this->assertEquals(180, $comparison['previous_month']);
        $this->assertNotNull($comparison['month_change']);
        // (200 - 180) / 180 * 100 = 11.1%
        $this->assertEquals(11.1, $comparison['month_change']);

        // Quarter assertions (current Q2 = 200 + 180 + 150 = 530, previous Q1 = 120)
        $this->assertEquals(530, $comparison['current_quarter']);
        $this->assertEquals(120, $comparison['previous_quarter']);
        $this->assertNotNull($comparison['quarter_change']);

        // YTD assertions (Jan-Jun 2025 = 530 + 120 = 650, Jan-Jun 2024 = 160)
        $this->assertEquals(650, $comparison['current_ytd']);
        $this->assertEquals(160, $comparison['previous_ytd']);
        $this->assertNotNull($comparison['ytd_change']);
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

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        // Add expenses to both
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
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

        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        foreach ($costs as $i => $cost) {
            $property = Property::factory()->create([
                'unit_count' => 10,
                'is_active' => true,
            ]);
            $properties[] = $property;

            UtilityExpense::factory()->forAccount($electricAccount)->create([
                'property_id' => $property->id,
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

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);
        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);
        $gasAccount = UtilityAccount::factory()->create(['utility_type' => 'gas']);

        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property->id,
            'amount' => 300,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($gasAccount)->create([
            'property_id' => $property->id,
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

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        // Create expenses for last 3 months
        for ($i = 0; $i < 3; $i++) {
            UtilityExpense::factory()->forAccount($waterAccount)->create([
                'property_id' => $property->id,
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

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        // Create expenses for current quarter
        $quarterStart = now()->startOfQuarter();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => $quarterStart->copy()->addDays(10),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
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

        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        $yearStart = $referenceDate->copy()->startOfYear();
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property->id,
            'amount' => 200,
            'expense_date' => $yearStart->copy()->addMonth(), // Feb
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property->id,
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
