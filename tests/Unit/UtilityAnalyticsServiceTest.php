<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\PropertyFlag;
use App\Models\PropertyUtilityExclusion;
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

    /**
     * Check if the current database connection is PostgreSQL.
     */
    private function isPostgres(): bool
    {
        return config('database.default') === 'pgsql' ||
               config('database.connections.'.config('database.default').'.driver') === 'pgsql';
    }

    /**
     * Skip the test if not running on PostgreSQL.
     * Some tests require PostgreSQL-specific features like DATE_TRUNC.
     */
    protected function skipIfNotPostgres(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('This test requires PostgreSQL for DATE_TRUNC function.');
        }
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

    public function test_get_portfolio_average_excludes_utility_specific_exclusions(): void
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

        // Exclude property2 from water reports only
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
            'reason' => 'Tenant pays water',
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);
        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        // Add water expenses to both
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

        // Add electric expenses to both
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property1->id,
            'amount' => 200,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property2->id,
            'amount' => 300,
            'expense_date' => now()->startOfMonth()->addDays(5),
        ]);

        $waterAverage = $this->service->getPortfolioAverage('water', [
            'type' => 'month',
            'date' => now(),
        ], 'per_unit');

        $electricAverage = $this->service->getPortfolioAverage('electric', [
            'type' => 'month',
            'date' => now(),
        ], 'per_unit');

        // Water: Only property1 should be included: $100 / 10 = $10
        $this->assertEquals(1, $waterAverage['property_count']);
        $this->assertEquals(10.00, $waterAverage['average']);

        // Electric: Both properties should be included: ($200 + $300) / 20 = $25
        // Average per property: ($20 + $30) / 2 = $25
        $this->assertEquals(2, $electricAverage['property_count']);
        $this->assertEquals(25.00, $electricAverage['average']);
    }

    // ==================== Bulk Query Optimization Method Tests ====================

    public function test_get_portfolio_trend_data_returns_monthly_totals(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create(['is_active' => true]);
        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        // Create expenses for 3 different months
        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 150,
            'expense_date' => $now->copy()->subMonth()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 200,
            'expense_date' => $now->copy()->subMonths(2)->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioTrendData(['water'], 3, $now);

        $this->assertCount(3, $result);

        // Verify totals are present for each month
        $totals = $result->pluck('total')->toArray();
        $this->assertContains('100', array_map('strval', $totals));
        $this->assertContains('150', array_map('strval', $totals));
        $this->assertContains('200', array_map('strval', $totals));
    }

    public function test_get_portfolio_trend_data_handles_multiple_utility_types(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create(['is_active' => true]);
        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);
        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property->id,
            'amount' => 250,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioTrendData(['water', 'electric'], 1, $now);

        $this->assertCount(2, $result);

        $byType = $result->groupBy('utility_type');
        $this->assertArrayHasKey('water', $byType->toArray());
        $this->assertArrayHasKey('electric', $byType->toArray());
    }

    public function test_get_portfolio_trend_data_excludes_utility_specific_exclusions(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['is_active' => true]);
        $property2 = Property::factory()->create(['is_active' => true]);

        // Exclude property2 from water only
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
            'reason' => 'Tenant pays water',
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 999,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioTrendData(['water'], 1, $now);

        // Only property1's expense should be included
        $this->assertCount(1, $result);
        $this->assertEquals(100, (float) $result->first()->total);
    }

    public function test_get_portfolio_trend_data_returns_empty_collection_when_no_data(): void
    {
        $this->skipIfNotPostgres();

        // No properties created
        $result = $this->service->getPortfolioTrendData(['water'], 3);

        $this->assertCount(0, $result);
    }

    public function test_get_portfolio_trend_data_excludes_inactive_properties(): void
    {
        $this->skipIfNotPostgres();

        $activeProperty = Property::factory()->create(['is_active' => true]);
        $inactiveProperty = Property::factory()->create(['is_active' => false]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $activeProperty->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $inactiveProperty->id,
            'amount' => 999,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioTrendData(['water'], 1, $now);

        $this->assertCount(1, $result);
        $this->assertEquals(100, (float) $result->first()->total);
    }

    public function test_get_portfolio_trend_data_excludes_flagged_properties(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['is_active' => true]);
        $property2 = Property::factory()->create(['is_active' => true]);

        // Flag property2 as HOA (should be excluded from utility reports)
        PropertyFlag::create([
            'property_id' => $property2->id,
            'flag_type' => 'hoa',
            'reason' => 'Test',
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 999,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioTrendData(['water'], 1, $now);

        $this->assertCount(1, $result);
        $this->assertEquals(100, (float) $result->first()->total);
    }

    public function test_get_property_comparison_data_bulk_returns_expected_structure(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create([
            'unit_count' => 10,
            'total_sqft' => 5000,
            'is_active' => true,
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 500,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPropertyComparisonDataBulk('water', $now);

        // Verify structure
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('averages', $result);
        $this->assertArrayHasKey('property_count', $result);

        // Verify totals structure
        $this->assertArrayHasKey('current_month', $result['totals']);
        $this->assertArrayHasKey('prev_month', $result['totals']);
        $this->assertArrayHasKey('prev_3_months', $result['totals']);
        $this->assertArrayHasKey('prev_12_months', $result['totals']);

        // Verify averages structure
        $this->assertArrayHasKey('current_month', $result['averages']);
        $this->assertArrayHasKey('prev_month', $result['averages']);
        $this->assertArrayHasKey('prev_3_months', $result['averages']);
        $this->assertArrayHasKey('prev_12_months', $result['averages']);
    }

    public function test_get_property_comparison_data_bulk_calculates_totals_correctly(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);
        $property2 = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        // Current month expenses
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        // Previous month expenses
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 150,
            'expense_date' => $now->copy()->subMonth()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPropertyComparisonDataBulk('water', $now);

        $this->assertEquals(300, $result['totals']['current_month']); // 100 + 200
        $this->assertEquals(150, $result['totals']['prev_month']);
        $this->assertEquals(2, $result['property_count']);
    }

    public function test_get_property_comparison_data_bulk_excludes_utility_specific_exclusions(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);
        $property2 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);

        // Exclude property2 from water
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
            'reason' => 'Tenant pays water',
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 999,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPropertyComparisonDataBulk('water', $now);

        // Only property1 should be included
        $this->assertEquals(1, $result['property_count']);
        $this->assertEquals(100, $result['totals']['current_month']);
    }

    public function test_get_property_comparison_data_bulk_returns_empty_when_no_properties(): void
    {
        $this->skipIfNotPostgres();

        $result = $this->service->getPropertyComparisonDataBulk('water');

        $this->assertEmpty($result['properties']);
        $this->assertEquals(0, $result['property_count']);
        $this->assertEquals(0, $result['totals']['current_month']);
        $this->assertEquals(0, $result['averages']['current_month']);
    }

    public function test_get_property_comparison_data_bulk_calculates_per_unit_and_sqft(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create([
            'unit_count' => 10,
            'total_sqft' => 10000,
            'is_active' => true,
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        // Create 12 months of expenses in PREVIOUS months (not current month)
        // The method calculates prev_12_months from (now - 12 months) to (now - 1 month end)
        for ($i = 1; $i <= 12; $i++) {
            UtilityExpense::factory()->forAccount($waterAccount)->create([
                'property_id' => $property->id,
                'amount' => 1200,
                'expense_date' => $now->copy()->subMonths($i)->startOfMonth()->addDays(5),
            ]);
        }

        $result = $this->service->getPropertyComparisonDataBulk('water', $now);

        $propertyData = $result['properties'][0];

        // 12-month total (prev months only) = $14,400, monthly avg = $1,200
        // avg_per_unit = $1200 / 10 units = $120
        $this->assertEquals(120.00, $propertyData['avg_per_unit']);
        // avg_per_sqft = $1200 / 10000 sqft = $0.12
        $this->assertEquals(0.12, $propertyData['avg_per_sqft']);
    }

    public function test_get_property_comparison_data_bulk_calculates_portfolio_averages(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);
        $property2 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPropertyComparisonDataBulk('water', $now);

        // Average = (100 + 200) / 2 = 150
        $this->assertEquals(150.00, $result['averages']['current_month']);
    }

    public function test_get_portfolio_summary_bulk_returns_expected_structure(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create([
            'unit_count' => 10,
            'is_active' => true,
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);
        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioSummaryBulk(['water', 'electric'], [
            'type' => 'month',
            'date' => $now,
        ]);

        // Verify keys for each utility type
        $this->assertArrayHasKey('water', $result);
        $this->assertArrayHasKey('electric', $result);

        // Verify structure for each type
        foreach (['water', 'electric'] as $type) {
            $this->assertArrayHasKey('total_cost', $result[$type]);
            $this->assertArrayHasKey('average_per_unit', $result[$type]);
            $this->assertArrayHasKey('property_count', $result[$type]);
        }
    }

    public function test_get_portfolio_summary_bulk_calculates_totals_per_utility_type(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);
        $property2 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);
        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        $now = now();
        // Water expenses
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 150,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        // Electric expenses
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property1->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioSummaryBulk(['water', 'electric'], [
            'type' => 'month',
            'date' => $now,
        ]);

        $this->assertEquals(250, $result['water']['total_cost']); // 100 + 150
        $this->assertEquals(2, $result['water']['property_count']);
        $this->assertEquals(200, $result['electric']['total_cost']);
        $this->assertEquals(1, $result['electric']['property_count']);
    }

    public function test_get_portfolio_summary_bulk_calculates_average_per_unit(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);
        $property2 = Property::factory()->create(['unit_count' => 20, 'is_active' => true]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioSummaryBulk(['water'], [
            'type' => 'month',
            'date' => $now,
        ]);

        // Total cost = 300, Total units = 30, Average = 300/30 = 10
        $this->assertEquals(10.00, $result['water']['average_per_unit']);
    }

    public function test_get_portfolio_summary_bulk_excludes_utility_specific_exclusions(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);
        $property2 = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);

        // Exclude property2 from water only
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
            'reason' => 'Tenant pays water',
        ]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);
        $electricAccount = UtilityAccount::factory()->create(['utility_type' => 'electric']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 999,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property1->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($electricAccount)->create([
            'property_id' => $property2->id,
            'amount' => 300,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioSummaryBulk(['water', 'electric'], [
            'type' => 'month',
            'date' => $now,
        ]);

        // Water: Only property1 included
        $this->assertEquals(100, $result['water']['total_cost']);
        $this->assertEquals(1, $result['water']['property_count']);

        // Electric: Both included
        $this->assertEquals(500, $result['electric']['total_cost']);
        $this->assertEquals(2, $result['electric']['property_count']);
    }

    public function test_get_portfolio_summary_bulk_returns_zeros_when_no_properties(): void
    {
        $this->skipIfNotPostgres();

        $result = $this->service->getPortfolioSummaryBulk(['water', 'electric'], [
            'type' => 'month',
            'date' => now(),
        ]);

        foreach (['water', 'electric'] as $type) {
            $this->assertEquals(0, $result[$type]['total_cost']);
            $this->assertEquals(0, $result[$type]['average_per_unit']);
            $this->assertEquals(0, $result[$type]['property_count']);
        }
    }

    public function test_get_portfolio_summary_bulk_handles_different_period_types(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create(['unit_count' => 10, 'is_active' => true]);
        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        // Create expenses in current quarter
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfQuarter()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 150,
            'expense_date' => $now->copy()->startOfQuarter()->addMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioSummaryBulk(['water'], [
            'type' => 'quarter',
            'date' => $now,
        ]);

        $this->assertEquals(250, $result['water']['total_cost']);
    }

    public function test_get_portfolio_trend_data_aggregates_multiple_expenses_per_month(): void
    {
        $this->skipIfNotPostgres();

        $property = Property::factory()->create(['is_active' => true]);
        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        // Multiple expenses in the same month
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 150,
            'expense_date' => $now->copy()->startOfMonth()->addDays(15),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property->id,
            'amount' => 50,
            'expense_date' => $now->copy()->startOfMonth()->addDays(25),
        ]);

        $result = $this->service->getPortfolioTrendData(['water'], 1, $now);

        $this->assertCount(1, $result);
        $this->assertEquals(300, (float) $result->first()->total); // 100 + 150 + 50
    }

    public function test_get_portfolio_trend_data_aggregates_expenses_from_multiple_properties(): void
    {
        $this->skipIfNotPostgres();

        $property1 = Property::factory()->create(['is_active' => true]);
        $property2 = Property::factory()->create(['is_active' => true]);
        $property3 = Property::factory()->create(['is_active' => true]);

        $waterAccount = UtilityAccount::factory()->create(['utility_type' => 'water']);

        $now = now();
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property1->id,
            'amount' => 100,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property2->id,
            'amount' => 200,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);
        UtilityExpense::factory()->forAccount($waterAccount)->create([
            'property_id' => $property3->id,
            'amount' => 300,
            'expense_date' => $now->copy()->startOfMonth()->addDays(5),
        ]);

        $result = $this->service->getPortfolioTrendData(['water'], 1, $now);

        $this->assertCount(1, $result);
        $this->assertEquals(600, (float) $result->first()->total); // 100 + 200 + 300
    }
}
