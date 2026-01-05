<?php

namespace Tests\Unit;

use App\Models\DailyKpi;
use App\Models\Property;
use App\Models\PropertyRollup;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\AdjustmentService;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalyticsService(new AdjustmentService);
    }

    public function test_calculates_occupancy_rate_correctly(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        // Create 10 units: 7 occupied, 3 vacant
        for ($i = 1; $i <= 7; $i++) {
            Unit::create([
                'external_id' => "unit-{$i}",
                'property_id' => $property->id,
                'unit_number' => (string) $i,
                'status' => 'occupied',
                'is_active' => true,
            ]);
        }

        for ($i = 8; $i <= 10; $i++) {
            Unit::create([
                'external_id' => "unit-{$i}",
                'property_id' => $property->id,
                'unit_number' => (string) $i,
                'status' => 'vacant',
                'is_active' => true,
            ]);
        }

        $this->service->refreshForDate(now());

        $kpi = DailyKpi::latest('date')->first();

        $this->assertEquals(70.00, $kpi->occupancy_rate);
        $this->assertEquals(3, $kpi->vacancy_count);
        $this->assertEquals(10, $kpi->total_units);
    }

    public function test_calculates_zero_occupancy_with_no_units(): void
    {
        $this->service->refreshForDate(now());

        $kpi = DailyKpi::latest('date')->first();

        $this->assertEquals(0, $kpi->occupancy_rate);
        $this->assertEquals(0, $kpi->vacancy_count);
        $this->assertEquals(0, $kpi->total_units);
    }

    public function test_calculates_open_work_order_count(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
        ]);

        // Create 5 open work orders
        for ($i = 1; $i <= 5; $i++) {
            WorkOrder::create([
                'external_id' => "wo-{$i}",
                'property_id' => $property->id,
                'status' => 'open',
                'opened_at' => now()->subDays($i),
            ]);
        }

        // Create 3 closed work orders
        for ($i = 6; $i <= 8; $i++) {
            WorkOrder::create([
                'external_id' => "wo-{$i}",
                'property_id' => $property->id,
                'status' => 'completed',
                'opened_at' => now()->subDays(10),
                'closed_at' => now()->subDays(5),
            ]);
        }

        $this->service->refreshForDate(now());

        $kpi = DailyKpi::latest('date')->first();

        $this->assertEquals(5, $kpi->open_work_orders);
    }

    public function test_calculates_average_days_open_work_orders(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
        ]);

        // Create work orders open for 2, 4, and 6 days (average = 4)
        WorkOrder::create([
            'external_id' => 'wo-1',
            'property_id' => $property->id,
            'status' => 'open',
            'opened_at' => now()->subDays(2),
        ]);

        WorkOrder::create([
            'external_id' => 'wo-2',
            'property_id' => $property->id,
            'status' => 'open',
            'opened_at' => now()->subDays(4),
        ]);

        WorkOrder::create([
            'external_id' => 'wo-3',
            'property_id' => $property->id,
            'status' => 'in_progress',
            'opened_at' => now()->subDays(6),
        ]);

        $this->service->refreshForDate(now());

        $kpi = DailyKpi::latest('date')->first();

        $this->assertEquals(4.00, $kpi->avg_days_open_work_orders);
    }

    public function test_creates_property_rollups(): void
    {
        $property1 = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Property One',
            'is_active' => true,
        ]);

        $property2 = Property::create([
            'external_id' => 'prop-2',
            'name' => 'Property Two',
            'is_active' => true,
        ]);

        // Property 1: 5 units, 1 vacant
        for ($i = 1; $i <= 4; $i++) {
            Unit::create([
                'external_id' => "p1-unit-{$i}",
                'property_id' => $property1->id,
                'unit_number' => (string) $i,
                'status' => 'occupied',
                'is_active' => true,
            ]);
        }
        Unit::create([
            'external_id' => 'p1-unit-5',
            'property_id' => $property1->id,
            'unit_number' => '5',
            'status' => 'vacant',
            'is_active' => true,
        ]);

        // Property 2: 3 units, all occupied
        for ($i = 1; $i <= 3; $i++) {
            Unit::create([
                'external_id' => "p2-unit-{$i}",
                'property_id' => $property2->id,
                'unit_number' => (string) $i,
                'status' => 'occupied',
                'is_active' => true,
            ]);
        }

        $this->service->refreshForDate(now());

        $rollup1 = PropertyRollup::where('property_id', $property1->id)->first();
        $rollup2 = PropertyRollup::where('property_id', $property2->id)->first();

        $this->assertEquals(5, $rollup1->total_units);
        $this->assertEquals(1, $rollup1->vacancy_count);
        $this->assertEquals(80.00, $rollup1->occupancy_rate);

        $this->assertEquals(3, $rollup2->total_units);
        $this->assertEquals(0, $rollup2->vacancy_count);
        $this->assertEquals(100.00, $rollup2->occupancy_rate);
    }

    public function test_updates_existing_kpi_for_same_date(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        Unit::create([
            'external_id' => 'unit-1',
            'property_id' => $property->id,
            'unit_number' => '1',
            'status' => 'vacant',
            'is_active' => true,
        ]);

        // First refresh
        $this->service->refreshForDate(now());

        // Modify data
        Unit::where('external_id', 'unit-1')->update(['status' => 'occupied']);

        // Second refresh
        $this->service->refreshForDate(now());

        // Should still only have one record for today
        $this->assertDatabaseCount('daily_kpis', 1);

        $kpi = DailyKpi::latest('date')->first();
        $this->assertEquals(100.00, $kpi->occupancy_rate);
    }

    public function test_get_property_rollups_returns_correct_data(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        Unit::create([
            'external_id' => 'unit-1',
            'property_id' => $property->id,
            'unit_number' => '1',
            'status' => 'vacant',
            'is_active' => true,
        ]);

        $this->service->refreshForDate(now());

        $rollups = $this->service->getPropertyRollups();

        $this->assertCount(1, $rollups);
        $this->assertEquals('Test Property', $rollups[0]['property_name']);
        $this->assertEquals(1, $rollups[0]['vacancy_count']);
    }

    public function test_get_kpi_trend_returns_date_range(): void
    {
        // Create KPIs for multiple days
        for ($i = 5; $i >= 0; $i--) {
            DailyKpi::create([
                'date' => now()->subDays($i)->toDateString(),
                'occupancy_rate' => 90 + $i,
                'vacancy_count' => $i,
                'total_units' => 10,
            ]);
        }

        $trend = $this->service->getKpiTrend(now()->subDays(3), now());

        $this->assertCount(4, $trend);
    }
}
