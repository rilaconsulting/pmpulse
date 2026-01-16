<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Services\VendorAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private VendorAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VendorAnalyticsService;
    }

    /**
     * Check if the current database driver is PostgreSQL.
     */
    protected function isPostgres(): bool
    {
        return config('database.default') === 'pgsql';
    }

    /**
     * Skip test if not using PostgreSQL (for tests using PostgreSQL-specific features).
     */
    protected function skipIfNotPostgres(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('This test requires PostgreSQL for date calculations.');
        }
    }

    // ==================== Core Metrics Tests ====================

    public function test_get_work_order_count_returns_correct_count(): void
    {
        $vendor = Vendor::factory()->create();

        // Create 3 work orders in current month
        WorkOrder::factory()->count(3)
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        // Create 2 work orders outside the period
        WorkOrder::factory()->count(2)
            ->forVendor($vendor)
            ->openedAt(now()->subMonths(2))
            ->create();

        $count = $this->service->getWorkOrderCount($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(3, $count);
    }

    public function test_get_work_order_count_returns_zero_when_no_work_orders(): void
    {
        $vendor = Vendor::factory()->create();

        $count = $this->service->getWorkOrderCount($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(0, $count);
    }

    public function test_get_work_order_count_includes_duplicate_vendor_group(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        // Create work orders for both vendors
        WorkOrder::factory()->count(2)
            ->forVendor($canonicalVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(3)
            ->forVendor($duplicateVendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->create();

        $count = $this->service->getWorkOrderCount($canonicalVendor, [
            'type' => 'month',
            'date' => now(),
        ], includeGroup: true);

        // Should include work orders from both canonical and duplicate
        $this->assertEquals(5, $count);
    }

    public function test_get_work_order_count_excludes_group_when_flag_is_false(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        WorkOrder::factory()->count(2)
            ->forVendor($canonicalVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(3)
            ->forVendor($duplicateVendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->create();

        $count = $this->service->getWorkOrderCount($canonicalVendor, [
            'type' => 'month',
            'date' => now(),
        ], includeGroup: false);

        // Should only include canonical vendor's work orders
        $this->assertEquals(2, $count);
    }

    public function test_get_total_spend_calculates_correctly(): void
    {
        $vendor = Vendor::factory()->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->withAmount(150.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->withAmount(250.00)
            ->create();

        $spend = $this->service->getTotalSpend($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(400.00, $spend);
    }

    public function test_get_total_spend_returns_zero_when_no_work_orders(): void
    {
        $vendor = Vendor::factory()->create();

        $spend = $this->service->getTotalSpend($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(0.0, $spend);
    }

    public function test_get_total_spend_includes_canonical_vendor_group(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        WorkOrder::factory()
            ->forVendor($canonicalVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->withAmount(100.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($duplicateVendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->withAmount(200.00)
            ->create();

        $spend = $this->service->getTotalSpend($canonicalVendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(300.00, $spend);
    }

    public function test_get_average_cost_per_wo_calculates_correctly(): void
    {
        $vendor = Vendor::factory()->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->withAmount(100.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->withAmount(200.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(15))
            ->withAmount(300.00)
            ->create();

        $avgCost = $this->service->getAverageCostPerWO($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        // (100 + 200 + 300) / 3 = 200
        $this->assertEquals(200.00, $avgCost);
    }

    public function test_get_average_cost_per_wo_returns_null_when_no_work_orders(): void
    {
        $vendor = Vendor::factory()->create();

        $avgCost = $this->service->getAverageCostPerWO($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertNull($avgCost);
    }

    public function test_get_average_cost_per_wo_excludes_zero_amounts(): void
    {
        $vendor = Vendor::factory()->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->withAmount(150.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->withAmount(0)
            ->create();

        $avgCost = $this->service->getAverageCostPerWO($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        // Only the $150 work order should be counted
        $this->assertEquals(150.00, $avgCost);
    }

    // ==================== Completion Time Tests (PostgreSQL-specific) ====================

    public function test_get_average_completion_time_calculates_correctly(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Work order completed in 3 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 3)
            ->create();

        // Work order completed in 7 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->completed(daysToComplete: 7)
            ->create();

        $avgTime = $this->service->getAverageCompletionTime($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        // (3 + 7) / 2 = 5 days
        $this->assertEquals(5.0, $avgTime);
    }

    public function test_get_average_completion_time_returns_null_when_no_completed_work_orders(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Only open work orders
        WorkOrder::factory()->count(3)
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $avgTime = $this->service->getAverageCompletionTime($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertNull($avgTime);
    }

    public function test_get_average_completion_time_only_counts_completed_or_cancelled(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Completed work order (5 days)
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 5)
            ->create();

        // Cancelled work order (3 days)
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->cancelled()
            ->state(['closed_at' => now()->startOfMonth()->addDays(13)])
            ->create();

        // Open work order (should not be counted)
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(1))
            ->create();

        $avgTime = $this->service->getAverageCompletionTime($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        // Should only average the completed and cancelled work orders
        $this->assertNotNull($avgTime);
    }

    // ==================== Trade-Based Analysis Tests ====================

    public function test_get_all_trades_returns_unique_trades(): void
    {
        Vendor::factory()->withTrade('Plumbing')->create();
        Vendor::factory()->withTrade('Electrical')->create();
        Vendor::factory()->withTrade('Plumbing')->create(); // Duplicate
        Vendor::factory()->withTrade('HVAC, Electrical')->create();

        $trades = $this->service->getAllTrades();

        $this->assertContains('Plumbing', $trades);
        $this->assertContains('Electrical', $trades);
        $this->assertContains('HVAC', $trades);
        // Should be unique
        $this->assertEquals(count($trades), count(array_unique($trades)));
    }

    public function test_get_all_trades_excludes_inactive_vendors(): void
    {
        Vendor::factory()->withTrade('Plumbing')->create();
        Vendor::factory()->withTrade('Electrical')->inactive()->create();

        $trades = $this->service->getAllTrades(activeOnly: true);

        $this->assertContains('Plumbing', $trades);
        $this->assertNotContains('Electrical', $trades);
    }

    public function test_get_all_trades_excludes_do_not_use_vendors(): void
    {
        Vendor::factory()->withTrade('Plumbing')->create();
        Vendor::factory()->withTrade('Roofing')->doNotUse()->create();

        $trades = $this->service->getAllTrades(activeOnly: true);

        $this->assertContains('Plumbing', $trades);
        $this->assertNotContains('Roofing', $trades);
    }

    public function test_get_vendors_by_trade_returns_matching_vendors(): void
    {
        $this->skipIfNotPostgres();

        $plumber1 = Vendor::factory()->withTrade('Plumbing')->create();
        $plumber2 = Vendor::factory()->withTrade('Plumbing, HVAC')->create();
        Vendor::factory()->withTrade('Electrical')->create();

        $vendors = $this->service->getVendorsByTrade('Plumbing');

        $this->assertCount(2, $vendors);
        $this->assertTrue($vendors->contains('id', $plumber1->id));
        $this->assertTrue($vendors->contains('id', $plumber2->id));
    }

    public function test_get_vendors_by_trade_excludes_inactive_and_do_not_use(): void
    {
        $this->skipIfNotPostgres();

        $activeVendor = Vendor::factory()->withTrade('Plumbing')->create();
        Vendor::factory()->withTrade('Plumbing')->inactive()->create();
        Vendor::factory()->withTrade('Plumbing')->doNotUse()->create();

        $vendors = $this->service->getVendorsByTrade('Plumbing', activeOnly: true);

        $this->assertCount(1, $vendors);
        $this->assertEquals($activeVendor->id, $vendors->first()->id);
    }

    public function test_parse_trades_splits_comma_separated_values(): void
    {
        $trades = $this->service->parseTrades('Plumbing, HVAC, Electrical');

        $this->assertCount(3, $trades);
        $this->assertEquals('Plumbing', $trades[0]);
        $this->assertEquals('HVAC', $trades[1]);
        $this->assertEquals('Electrical', $trades[2]);
    }

    public function test_parse_trades_returns_empty_array_for_null(): void
    {
        $trades = $this->service->parseTrades(null);

        $this->assertEmpty($trades);
    }

    public function test_get_primary_trade_returns_first_trade(): void
    {
        $vendor = Vendor::factory()->withTrade('Plumbing, HVAC')->create();

        $primaryTrade = $this->service->getPrimaryTrade($vendor);

        $this->assertEquals('Plumbing', $primaryTrade);
    }

    public function test_get_primary_trade_returns_null_when_no_trades(): void
    {
        $vendor = Vendor::factory()->state(['vendor_trades' => null])->create();

        $primaryTrade = $this->service->getPrimaryTrade($vendor);

        $this->assertNull($primaryTrade);
    }

    public function test_get_trade_averages_calculates_correctly(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor2 = Vendor::factory()->withTrade('Plumbing')->create();

        // Vendor 1: 3 work orders, $600 total
        WorkOrder::factory()->count(3)
            ->forVendor($vendor1)
            ->withAmount(200.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        // Vendor 2: 2 work orders, $300 total
        WorkOrder::factory()->count(2)
            ->forVendor($vendor2)
            ->withAmount(150.00)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->create();

        $averages = $this->service->getTradeAverages('Plumbing', [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals('Plumbing', $averages['trade']);
        $this->assertEquals(2, $averages['vendor_count']);
        // Average work order count: (3 + 2) / 2 = 2.5
        $this->assertEquals(2.5, $averages['avg_work_order_count']);
        // Average spend: (600 + 300) / 2 = 450
        $this->assertEquals(450.00, $averages['avg_total_spend']);
        // Total work orders: 5
        $this->assertEquals(5, $averages['total_work_orders']);
        // Total spend: 900
        $this->assertEquals(900.00, $averages['total_spend']);
    }

    public function test_get_trade_averages_returns_nulls_for_empty_trade(): void
    {
        $this->skipIfNotPostgres();

        $averages = $this->service->getTradeAverages('NonexistentTrade', [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals('NonexistentTrade', $averages['trade']);
        $this->assertEquals(0, $averages['vendor_count']);
        $this->assertNull($averages['avg_work_order_count']);
        $this->assertNull($averages['avg_total_spend']);
    }

    public function test_compare_vendor_to_trade_average(): void
    {
        $this->skipIfNotPostgres();

        // Create 3 plumbers with different performance
        $vendor1 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor2 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor3 = Vendor::factory()->withTrade('Plumbing')->create();

        // Vendor 1: 10 work orders, $1000 total (high performer)
        WorkOrder::factory()->count(10)
            ->forVendor($vendor1)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        // Vendor 2: 2 work orders, $200 total
        WorkOrder::factory()->count(2)
            ->forVendor($vendor2)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        // Vendor 3: 3 work orders, $300 total
        WorkOrder::factory()->count(3)
            ->forVendor($vendor3)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $comparison = $this->service->compareVendorToTradeAverage($vendor1, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals($vendor1->id, $comparison['vendor_id']);
        $this->assertEquals('Plumbing', $comparison['trade']);
        $this->assertTrue($comparison['has_trade']);
        $this->assertNotNull($comparison['vendor_metrics']);
        $this->assertNotNull($comparison['trade_averages']);
        $this->assertNotNull($comparison['comparison']);

        // Vendor 1 is above average for work order count
        $this->assertEquals('above', $comparison['comparison']['work_order_count']['direction']);
    }

    public function test_compare_vendor_to_trade_average_handles_no_trade(): void
    {
        $vendor = Vendor::factory()->state(['vendor_trades' => null])->create();

        $comparison = $this->service->compareVendorToTradeAverage($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertFalse($comparison['has_trade']);
        $this->assertNull($comparison['trade']);
        $this->assertNull($comparison['vendor_metrics']);
    }

    public function test_rank_vendors_in_trade(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor2 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor3 = Vendor::factory()->withTrade('Plumbing')->create();

        // Different work order counts
        WorkOrder::factory()->count(10)
            ->forVendor($vendor1)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(5)
            ->forVendor($vendor2)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(3)
            ->forVendor($vendor3)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $ranked = $this->service->rankVendorsInTrade('Plumbing', 'work_order_count', [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertCount(3, $ranked);
        // Vendor1 should be ranked first (most work orders)
        $this->assertEquals($vendor1->id, $ranked[0]['vendor_id']);
        $this->assertEquals(1, $ranked[0]['rank']);
        $this->assertEquals(10, $ranked[0]['value']);

        // Vendor2 should be second
        $this->assertEquals($vendor2->id, $ranked[1]['vendor_id']);
        $this->assertEquals(2, $ranked[1]['rank']);

        // Vendor3 should be third
        $this->assertEquals($vendor3->id, $ranked[2]['vendor_id']);
        $this->assertEquals(3, $ranked[2]['rank']);
    }

    public function test_rank_vendors_in_trade_ascending_order(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor2 = Vendor::factory()->withTrade('Plumbing')->create();

        WorkOrder::factory()
            ->forVendor($vendor1)
            ->withAmount(500.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor2)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        // For avg_cost, ascending = lower is better
        $ranked = $this->service->rankVendorsInTrade('Plumbing', 'avg_cost', [
            'type' => 'month',
            'date' => now(),
        ], ascending: true);

        // Vendor2 (lower cost) should be first
        $this->assertEquals($vendor2->id, $ranked[0]['vendor_id']);
        $this->assertEquals(100.00, $ranked[0]['value']);
    }

    // ==================== Period Tests ====================

    public function test_handles_quarter_period(): void
    {
        $vendor = Vendor::factory()->create();

        // Create work orders across the quarter
        $quarterStart = now()->startOfQuarter();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt($quarterStart->copy()->addDays(5))
            ->withAmount(100.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt($quarterStart->copy()->addMonth()->addDays(10))
            ->withAmount(200.00)
            ->create();

        $spend = $this->service->getTotalSpend($vendor, [
            'type' => 'quarter',
            'date' => now(),
        ]);

        $this->assertEquals(300.00, $spend);
    }

    public function test_handles_year_period(): void
    {
        $vendor = Vendor::factory()->create();

        $yearStart = now()->startOfYear();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt($yearStart->copy()->addMonth())
            ->withAmount(500.00)
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt($yearStart->copy()->addMonths(6))
            ->withAmount(700.00)
            ->create();

        $spend = $this->service->getTotalSpend($vendor, [
            'type' => 'year',
            'date' => now(),
        ]);

        $this->assertEquals(1200.00, $spend);
    }

    public function test_handles_last_30_days_period(): void
    {
        $vendor = Vendor::factory()->create();

        // Work order within last 30 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->subDays(15))
            ->create();

        // Work order outside last 30 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->subDays(45))
            ->create();

        $count = $this->service->getWorkOrderCount($vendor, [
            'type' => 'last_30_days',
            'date' => now(),
        ]);

        $this->assertEquals(1, $count);
    }

    public function test_handles_ytd_period(): void
    {
        $referenceDate = Carbon::create(2025, 6, 15);
        $vendor = Vendor::factory()->create();

        // Work order in current year
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt($referenceDate->copy()->startOfYear()->addMonth())
            ->withAmount(300.00)
            ->create();

        // Work order in previous year (should be excluded)
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt($referenceDate->copy()->subYear())
            ->withAmount(500.00)
            ->create();

        $spend = $this->service->getTotalSpend($vendor, [
            'type' => 'ytd',
            'date' => $referenceDate,
        ]);

        $this->assertEquals(300.00, $spend);
    }

    // ==================== Vendor Summary Tests ====================

    public function test_get_vendor_summary_returns_complete_data(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        WorkOrder::factory()->count(5)
            ->forVendor($vendor)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $summary = $this->service->getVendorSummary($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals($vendor->id, $summary['vendor_id']);
        $this->assertEquals($vendor->company_name, $summary['company_name']);
        $this->assertEquals(5, $summary['work_order_count']);
        $this->assertEquals(500.00, $summary['total_spend']);
        $this->assertArrayHasKey('avg_cost_per_wo', $summary);
        $this->assertArrayHasKey('avg_completion_time', $summary);
        $this->assertArrayHasKey('period', $summary);
    }

    public function test_get_vendor_summary_includes_duplicate_count_for_canonical(): void
    {
        $this->skipIfNotPostgres();

        $canonicalVendor = Vendor::factory()->create();
        Vendor::factory()->duplicateOf($canonicalVendor)->create();
        Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $summary = $this->service->getVendorSummary($canonicalVendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertTrue($summary['is_canonical']);
        $this->assertEquals(2, $summary['duplicate_count']);
    }

    // ==================== Portfolio Stats Tests ====================

    public function test_get_portfolio_stats_calculates_totals(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();

        WorkOrder::factory()->count(3)
            ->forVendor($vendor1)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(2)
            ->forVendor($vendor2)
            ->withAmount(200.00)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->create();

        $stats = $this->service->getPortfolioStats([
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(5, $stats['total_work_orders']);
        $this->assertEquals(2, $stats['unique_vendors']);
        // (3 * 100) + (2 * 200) = 700
        $this->assertEquals(700.00, $stats['total_spend']);
        $this->assertArrayHasKey('avg_cost_per_wo', $stats);
    }

    public function test_get_portfolio_stats_returns_zeros_when_empty(): void
    {
        $this->skipIfNotPostgres();

        $stats = $this->service->getPortfolioStats([
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(0, $stats['total_work_orders']);
        $this->assertEquals(0, $stats['unique_vendors']);
        $this->assertEquals(0.0, $stats['total_spend']);
    }

    // ==================== Top Vendors Tests ====================

    public function test_get_top_vendors_by_work_order_count(): void
    {
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();
        $vendor3 = Vendor::factory()->create();

        WorkOrder::factory()->count(10)
            ->forVendor($vendor1)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(5)
            ->forVendor($vendor2)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(2)
            ->forVendor($vendor3)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $topVendors = $this->service->getTopVendors('work_order_count', 2, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertCount(2, $topVendors);
        $this->assertEquals($vendor1->id, $topVendors[0]['vendor_id']);
        $this->assertEquals(10, $topVendors[0]['value']);
        $this->assertEquals($vendor2->id, $topVendors[1]['vendor_id']);
        $this->assertEquals(5, $topVendors[1]['value']);
    }

    public function test_get_top_vendors_by_total_spend(): void
    {
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();

        WorkOrder::factory()
            ->forVendor($vendor1)
            ->withAmount(1000.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor2)
            ->withAmount(500.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $topVendors = $this->service->getTopVendors('total_spend', 5, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals($vendor1->id, $topVendors[0]['vendor_id']);
        $this->assertEquals(1000.00, $topVendors[0]['value']);
    }

    public function test_get_top_vendors_throws_for_invalid_metric(): void
    {
        // Create a vendor with work order so it has data
        $vendor = Vendor::factory()->create();
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid metric: invalid_metric');

        $this->service->getTopVendors('invalid_metric', 5, [
            'type' => 'month',
            'date' => now(),
        ]);
    }

    // ==================== Response Time Metrics Tests (PostgreSQL-specific) ====================

    public function test_get_response_time_metrics_calculates_correctly(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Completed in 2 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 2)
            ->create();

        // Completed in 4 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->completed(daysToComplete: 4)
            ->create();

        // Completed in 6 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(15))
            ->completed(daysToComplete: 6)
            ->create();

        $metrics = $this->service->getResponseTimeMetrics($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(3, $metrics['total_completed']);
        // Average: (2 + 4 + 6) / 3 = 4
        $this->assertEquals(4.0, $metrics['avg_days_to_complete']);
        // Median: 4
        $this->assertEquals(4.0, $metrics['median_days_to_complete']);
        $this->assertEquals(2.0, $metrics['min_days_to_complete']);
        $this->assertEquals(6.0, $metrics['max_days_to_complete']);
    }

    public function test_get_response_time_metrics_returns_empty_when_no_completed(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Only open work orders
        WorkOrder::factory()->count(3)
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $metrics = $this->service->getResponseTimeMetrics($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals(0, $metrics['total_completed']);
        $this->assertNull($metrics['avg_days_to_complete']);
        $this->assertEmpty($metrics['by_priority']);
    }

    public function test_get_response_time_metrics_groups_by_priority(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Emergency priority - 1 day
        WorkOrder::factory()
            ->forVendor($vendor)
            ->withPriority('emergency')
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 1)
            ->create();

        // Normal priority - 5 days
        WorkOrder::factory()
            ->forVendor($vendor)
            ->withPriority('normal')
            ->openedAt(now()->startOfMonth()->addDays(10))
            ->completed(daysToComplete: 5)
            ->create();

        $metrics = $this->service->getResponseTimeMetrics($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertArrayHasKey('emergency', $metrics['by_priority']);
        $this->assertArrayHasKey('normal', $metrics['by_priority']);
        $this->assertEquals(1, $metrics['by_priority']['emergency']['count']);
        $this->assertEquals(1, $metrics['by_priority']['normal']['count']);
    }

    public function test_compare_response_time_to_portfolio(): void
    {
        $this->skipIfNotPostgres();

        // Create a fast vendor
        $fastVendor = Vendor::factory()->create();
        WorkOrder::factory()->count(3)
            ->forVendor($fastVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 2)
            ->create();

        // Create a slow vendor
        $slowVendor = Vendor::factory()->create();
        WorkOrder::factory()->count(3)
            ->forVendor($slowVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 10)
            ->create();

        $fastComparison = $this->service->compareResponseTimeToPortfolio($fastVendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertTrue($fastComparison['is_faster_than_average']);
        $this->assertNotNull($fastComparison['vendor_metrics']);
        $this->assertNotNull($fastComparison['portfolio_metrics']);

        $slowComparison = $this->service->compareResponseTimeToPortfolio($slowVendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertFalse($slowComparison['is_faster_than_average']);
    }

    public function test_rank_vendors_by_response_time(): void
    {
        $this->skipIfNotPostgres();

        $fastVendor = Vendor::factory()->create();
        $slowVendor = Vendor::factory()->create();

        // Fast vendor: 3 work orders, 2 days each
        WorkOrder::factory()->count(3)
            ->forVendor($fastVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 2)
            ->create();

        // Slow vendor: 3 work orders, 10 days each
        WorkOrder::factory()->count(3)
            ->forVendor($slowVendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->completed(daysToComplete: 10)
            ->create();

        $ranked = $this->service->rankVendorsByResponseTime([
            'type' => 'month',
            'date' => now(),
        ], limit: 10, minWorkOrders: 3);

        $this->assertNotEmpty($ranked);
        // Fast vendor should be first (fastest)
        $this->assertEquals($fastVendor->id, $ranked[0]['vendor_id']);
        $this->assertEquals(1, $ranked[0]['rank']);
    }

    // ==================== Trend Tests ====================

    public function test_get_vendor_trend_returns_multiple_periods(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Create work orders for the last 3 months
        for ($i = 0; $i < 3; $i++) {
            WorkOrder::factory()->count(5 - $i)
                ->forVendor($vendor)
                ->openedAt(now()->subMonths($i)->startOfMonth()->addDays(5))
                ->withAmount(100.00 * ($i + 1))
                ->create();
        }

        $trend = $this->service->getVendorTrend($vendor, periods: 3, periodType: 'month');

        $this->assertCount(3, $trend['data']);
        $this->assertEquals('month', $trend['period_type']);
        $this->assertEquals(3, $trend['periods']);
        $this->assertArrayHasKey('trends', $trend);
    }

    public function test_get_vendor_trend_detects_increasing_trend(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Create increasing work orders over 6 months
        for ($i = 5; $i >= 0; $i--) {
            WorkOrder::factory()->count(10 - $i) // 5, 6, 7, 8, 9, 10
                ->forVendor($vendor)
                ->openedAt(now()->subMonths($i)->startOfMonth()->addDays(5))
                ->create();
        }

        $trend = $this->service->getVendorTrend($vendor, periods: 6, periodType: 'month');

        $this->assertEquals('increasing', $trend['trends']['work_order_count']);
    }

    public function test_get_vendor_trend_reports_insufficient_data(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Only 2 periods of data
        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()
            ->forVendor($vendor)
            ->openedAt(now()->subMonth()->startOfMonth()->addDays(5))
            ->create();

        $trend = $this->service->getVendorTrend($vendor, periods: 2, periodType: 'month');

        $this->assertEquals('insufficient_data', $trend['trends']['work_order_count']);
    }

    // ==================== Period Comparison Tests ====================

    public function test_get_period_comparison_compares_all_periods(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Current 30 days
        WorkOrder::factory()->count(10)
            ->forVendor($vendor)
            ->openedAt(now()->subDays(15))
            ->withAmount(100.00)
            ->create();

        // Previous 30 days
        WorkOrder::factory()->count(5)
            ->forVendor($vendor)
            ->openedAt(now()->subDays(45))
            ->withAmount(200.00)
            ->create();

        $comparison = $this->service->getPeriodComparison($vendor, now());

        $this->assertArrayHasKey('last_30_days', $comparison);
        $this->assertArrayHasKey('last_90_days', $comparison);
        $this->assertArrayHasKey('last_12_months', $comparison);
        $this->assertArrayHasKey('year_to_date', $comparison);

        // Check structure
        $this->assertArrayHasKey('current', $comparison['last_30_days']);
        $this->assertArrayHasKey('previous', $comparison['last_30_days']);
        $this->assertArrayHasKey('changes', $comparison['last_30_days']);
    }

    public function test_get_period_comparison_calculates_changes(): void
    {
        $this->skipIfNotPostgres();

        $vendor = Vendor::factory()->create();

        // Current period: 10 work orders
        WorkOrder::factory()->count(10)
            ->forVendor($vendor)
            ->openedAt(now()->subDays(15))
            ->create();

        // Previous period: 5 work orders (100% increase)
        WorkOrder::factory()->count(5)
            ->forVendor($vendor)
            ->openedAt(now()->subDays(45))
            ->create();

        $comparison = $this->service->getPeriodComparison($vendor, now());

        $this->assertEquals(10, $comparison['last_30_days']['current']['work_order_count']);
        $this->assertEquals(5, $comparison['last_30_days']['previous']['work_order_count']);
        // 100% increase
        $this->assertEquals(100.0, $comparison['last_30_days']['changes']['work_order_count']);
    }

    // ==================== Trade Summary Tests ====================

    public function test_get_trade_summary_returns_all_trades(): void
    {
        $this->skipIfNotPostgres();

        $plumber = Vendor::factory()->withTrade('Plumbing')->create();
        $electrician = Vendor::factory()->withTrade('Electrical')->create();

        WorkOrder::factory()->count(5)
            ->forVendor($plumber)
            ->withAmount(200.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(3)
            ->forVendor($electrician)
            ->withAmount(300.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $summary = $this->service->getTradeSummary([
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertCount(2, $summary);
        // Should be sorted by total work orders (Plumbing first with 5)
        $this->assertEquals('Plumbing', $summary[0]['trade']);
        $this->assertEquals(5, $summary[0]['total_work_orders']);
        $this->assertEquals('Electrical', $summary[1]['trade']);
        $this->assertEquals(3, $summary[1]['total_work_orders']);
    }

    // ==================== Vendor Trade Analysis Tests ====================

    public function test_get_vendor_trade_analysis_complete(): void
    {
        $this->skipIfNotPostgres();

        $vendor1 = Vendor::factory()->withTrade('Plumbing')->create();
        $vendor2 = Vendor::factory()->withTrade('Plumbing')->create();

        WorkOrder::factory()->count(10)
            ->forVendor($vendor1)
            ->withAmount(100.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        WorkOrder::factory()->count(5)
            ->forVendor($vendor2)
            ->withAmount(200.00)
            ->openedAt(now()->startOfMonth()->addDays(5))
            ->create();

        $analysis = $this->service->getVendorTradeAnalysis($vendor1, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertEquals($vendor1->id, $analysis['vendor_id']);
        $this->assertEquals('Plumbing', $analysis['primary_trade']);
        $this->assertContains('Plumbing', $analysis['all_trades']);
        $this->assertNotNull($analysis['comparison']);
        $this->assertNotNull($analysis['rankings']);
        $this->assertArrayHasKey('work_order_count', $analysis['rankings']);
    }

    public function test_get_vendor_trade_analysis_handles_vendor_without_trade(): void
    {
        $vendor = Vendor::factory()->state(['vendor_trades' => null])->create();

        $analysis = $this->service->getVendorTradeAnalysis($vendor, [
            'type' => 'month',
            'date' => now(),
        ]);

        $this->assertNull($analysis['primary_trade']);
        $this->assertEmpty($analysis['all_trades']);
        $this->assertNull($analysis['rankings']);
    }

    // ==================== Grouped Vendors Tests ====================

    public function test_get_vendors_grouped_by_trade(): void
    {
        Vendor::factory()->withTrade('Plumbing')->create();
        Vendor::factory()->withTrade('Plumbing')->create();
        Vendor::factory()->withTrade('Electrical')->create();

        $grouped = $this->service->getVendorsGroupedByTrade();

        $this->assertArrayHasKey('Plumbing', $grouped);
        $this->assertArrayHasKey('Electrical', $grouped);
        $this->assertCount(2, $grouped['Plumbing']);
        $this->assertCount(1, $grouped['Electrical']);
    }

    public function test_get_vendors_grouped_by_trade_excludes_duplicates(): void
    {
        $canonical = Vendor::factory()->withTrade('Plumbing')->create();
        $duplicate = Vendor::factory()->withTrade('Plumbing')->duplicateOf($canonical)->create();

        $grouped = $this->service->getVendorsGroupedByTrade(canonicalOnly: true);

        // Should only include the canonical vendor
        $this->assertCount(1, $grouped['Plumbing']);
        $this->assertEquals($canonical->id, $grouped['Plumbing'][0]->id);
    }
}
