<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\Unit;
use App\Services\AdjustmentService;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PropertyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PropertyService(app(AdjustmentService::class));
    }

    // ==================== getFilteredProperties Tests ====================

    public function test_get_filtered_properties_returns_all_when_no_filters(): void
    {
        Property::factory()->count(3)->create();

        $result = $this->service->getFilteredProperties([]);

        $this->assertCount(3, $result);
    }

    public function test_get_filtered_properties_filters_by_search(): void
    {
        Property::factory()->create(['name' => 'Sunset Apartments']);
        Property::factory()->create(['name' => 'Ocean View']);

        $result = $this->service->getFilteredProperties(['search' => 'Sunset']);

        $this->assertCount(1, $result);
        $this->assertEquals('Sunset Apartments', $result->first()->name);
    }

    public function test_get_filtered_properties_filters_by_portfolio(): void
    {
        Property::factory()->create(['portfolio' => 'Portfolio A']);
        Property::factory()->create(['portfolio' => 'Portfolio B']);

        $result = $this->service->getFilteredProperties(['portfolio' => 'Portfolio A']);

        $this->assertCount(1, $result);
        $this->assertEquals('Portfolio A', $result->first()->portfolio);
    }

    public function test_get_filtered_properties_filters_by_property_type(): void
    {
        Property::factory()->create(['property_type' => 'Residential']);
        Property::factory()->create(['property_type' => 'Commercial']);

        $result = $this->service->getFilteredProperties(['property_type' => 'Residential']);

        $this->assertCount(1, $result);
        $this->assertEquals('Residential', $result->first()->property_type);
    }

    public function test_get_filtered_properties_filters_by_active_status(): void
    {
        Property::factory()->create(['is_active' => true]);
        Property::factory()->create(['is_active' => false]);

        $activeResult = $this->service->getFilteredProperties(['is_active' => true]);
        $inactiveResult = $this->service->getFilteredProperties(['is_active' => false]);

        $this->assertCount(1, $activeResult);
        $this->assertTrue($activeResult->first()->is_active);
        $this->assertCount(1, $inactiveResult);
        $this->assertFalse($inactiveResult->first()->is_active);
    }

    public function test_get_filtered_properties_sorts_by_name_ascending(): void
    {
        Property::factory()->create(['name' => 'Zebra Tower']);
        Property::factory()->create(['name' => 'Alpha Building']);

        $result = $this->service->getFilteredProperties(['sort' => 'name', 'direction' => 'asc']);

        $this->assertEquals('Alpha Building', $result->first()->name);
        $this->assertEquals('Zebra Tower', $result->last()->name);
    }

    public function test_get_filtered_properties_sorts_by_name_descending(): void
    {
        Property::factory()->create(['name' => 'Zebra Tower']);
        Property::factory()->create(['name' => 'Alpha Building']);

        $result = $this->service->getFilteredProperties(['sort' => 'name', 'direction' => 'desc']);

        $this->assertEquals('Zebra Tower', $result->first()->name);
        $this->assertEquals('Alpha Building', $result->last()->name);
    }

    public function test_get_filtered_properties_calculates_occupancy_rate(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->count(2)->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->count(2)->create(['property_id' => $property->id, 'status' => 'vacant']);

        $result = $this->service->getFilteredProperties([]);

        $this->assertEquals(50.0, $result->first()->occupancy_rate);
    }

    // ==================== getPortfolios Tests ====================

    public function test_get_portfolios_returns_unique_sorted_values(): void
    {
        Property::factory()->create(['portfolio' => 'Portfolio C']);
        Property::factory()->create(['portfolio' => 'Portfolio A']);
        Property::factory()->create(['portfolio' => 'Portfolio B']);
        Property::factory()->create(['portfolio' => 'Portfolio A']); // Duplicate

        $result = $this->service->getPortfolios();

        $this->assertCount(3, $result);
        $this->assertEquals(['Portfolio A', 'Portfolio B', 'Portfolio C'], $result->toArray());
    }

    public function test_get_portfolios_excludes_null(): void
    {
        Property::factory()->create(['portfolio' => 'Portfolio A']);
        Property::factory()->create(['portfolio' => null]);

        $result = $this->service->getPortfolios();

        $this->assertCount(1, $result);
    }

    // ==================== getPropertyTypes Tests ====================

    public function test_get_property_types_returns_unique_sorted_values(): void
    {
        Property::factory()->create(['property_type' => 'Commercial']);
        Property::factory()->create(['property_type' => 'Residential']);
        Property::factory()->create(['property_type' => 'Commercial']); // Duplicate

        $result = $this->service->getPropertyTypes();

        $this->assertCount(2, $result);
        $this->assertEquals(['Commercial', 'Residential'], $result->toArray());
    }

    // ==================== getPropertyStats Tests ====================

    public function test_get_property_stats_counts_units_by_status(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->count(3)->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->count(2)->create(['property_id' => $property->id, 'status' => 'vacant']);
        Unit::factory()->count(1)->create(['property_id' => $property->id, 'status' => 'not_ready']);

        $stats = $this->service->getPropertyStats($property);

        $this->assertEquals(6, $stats['total_units']);
        $this->assertEquals(3, $stats['occupied_units']);
        $this->assertEquals(2, $stats['vacant_units']);
        $this->assertEquals(1, $stats['not_ready_units']);
    }

    public function test_get_property_stats_calculates_occupancy_rate(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->count(3)->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->count(1)->create(['property_id' => $property->id, 'status' => 'vacant']);

        $stats = $this->service->getPropertyStats($property);

        $this->assertEquals(75.0, $stats['occupancy_rate']);
    }

    public function test_get_property_stats_handles_no_units(): void
    {
        $property = Property::factory()->create();

        $stats = $this->service->getPropertyStats($property);

        $this->assertEquals(0, $stats['total_units']);
        $this->assertEquals(0, $stats['occupancy_rate']);
    }

    public function test_get_property_stats_calculates_market_rent(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->create(['property_id' => $property->id, 'market_rent' => 1500.00]);
        Unit::factory()->create(['property_id' => $property->id, 'market_rent' => 2500.00]);

        $stats = $this->service->getPropertyStats($property);

        $this->assertEquals(4000.0, $stats['total_market_rent']);
        $this->assertEquals(2000.0, $stats['avg_market_rent']);
    }

    public function test_get_property_stats_handles_null_market_rent(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->create(['property_id' => $property->id, 'market_rent' => null]);
        Unit::factory()->create(['property_id' => $property->id, 'market_rent' => null]);

        $stats = $this->service->getPropertyStats($property);

        $this->assertEquals(0.0, $stats['total_market_rent']);
        $this->assertEquals(0.0, $stats['avg_market_rent']);
    }

    // ==================== searchProperties Tests ====================

    public function test_search_properties_returns_matching_results(): void
    {
        Property::factory()->create(['name' => 'Sunset Apartments', 'is_active' => true]);
        Property::factory()->create(['name' => 'Ocean View', 'is_active' => true]);

        $result = $this->service->searchProperties('Sunset');

        $this->assertCount(1, $result);
        $this->assertEquals('Sunset Apartments', $result->first()['name']);
    }

    public function test_search_properties_returns_empty_for_short_query(): void
    {
        Property::factory()->create(['name' => 'Sunset Apartments', 'is_active' => true]);

        $result = $this->service->searchProperties('S');

        $this->assertCount(0, $result);
    }

    public function test_search_properties_only_returns_active(): void
    {
        Property::factory()->create(['name' => 'Active Property', 'is_active' => true]);
        Property::factory()->create(['name' => 'Inactive Property', 'is_active' => false]);

        $result = $this->service->searchProperties('Property');

        $this->assertCount(1, $result);
        $this->assertEquals('Active Property', $result->first()['name']);
    }

    public function test_search_properties_formats_address(): void
    {
        Property::factory()->create([
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'is_active' => true,
        ]);

        $result = $this->service->searchProperties('Test');

        $this->assertEquals('123 Main St, San Francisco, CA', $result->first()['address']);
    }

    public function test_search_properties_respects_limit(): void
    {
        // Create 15 properties with 'Tower' in the name (matches factory pattern)
        Property::factory()->count(15)->create([
            'name' => 'Sample Tower',
            'is_active' => true,
        ]);

        $result = $this->service->searchProperties('Tower', 5);

        $this->assertCount(5, $result);
    }

    // ==================== getFilteredUnits Tests ====================

    public function test_get_filtered_units_returns_paginated_results(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->count(30)->create(['property_id' => $property->id]);

        $result = $this->service->getFilteredUnits($property, [], 10);

        $this->assertCount(10, $result->items());
        $this->assertEquals(30, $result->total());
        $this->assertEquals(3, $result->lastPage());
    }

    public function test_get_filtered_units_filters_by_status(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->count(5)->create(['property_id' => $property->id, 'status' => 'occupied']);
        Unit::factory()->count(3)->create(['property_id' => $property->id, 'status' => 'vacant']);

        $result = $this->service->getFilteredUnits($property, ['status' => 'vacant']);

        $this->assertEquals(3, $result->total());
        $this->assertTrue($result->items()[0]->status === 'vacant');
    }

    public function test_get_filtered_units_sorts_by_field(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => '101', 'market_rent' => 1000]);
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => '102', 'market_rent' => 2000]);
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => '103', 'market_rent' => 1500]);

        $result = $this->service->getFilteredUnits($property, ['sort' => 'market_rent', 'direction' => 'desc']);

        $this->assertEquals(2000, $result->items()[0]->market_rent);
        $this->assertEquals(1500, $result->items()[1]->market_rent);
        $this->assertEquals(1000, $result->items()[2]->market_rent);
    }

    public function test_get_filtered_units_defaults_to_unit_number_sort(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => '301']);
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => '101']);
        Unit::factory()->create(['property_id' => $property->id, 'unit_number' => '201']);

        $result = $this->service->getFilteredUnits($property, []);

        $this->assertEquals('101', $result->items()[0]->unit_number);
        $this->assertEquals('201', $result->items()[1]->unit_number);
        $this->assertEquals('301', $result->items()[2]->unit_number);
    }

    public function test_get_filtered_units_ignores_invalid_sort_field(): void
    {
        $property = Property::factory()->create();
        Unit::factory()->count(3)->create(['property_id' => $property->id]);

        // Should not throw, should fall back to unit_number
        $result = $this->service->getFilteredUnits($property, ['sort' => 'invalid_field']);

        $this->assertEquals(3, $result->total());
    }
}
