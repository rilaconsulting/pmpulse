<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'user']);
        $this->user = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_property_list_page_displays(): void
    {
        $response = $this->actingAs($this->user)->get('/properties');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Properties/Index')
            ->has('properties')
            ->has('portfolios')
            ->has('propertyTypes')
            ->has('filters')
        );
    }

    public function test_property_list_shows_properties(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Properties/Index')
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Test Property')
        );
    }

    public function test_property_list_search_filters_by_name(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Sunset Apartments',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Ocean View Condos',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties?search=Sunset');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Sunset Apartments')
        );
    }

    public function test_property_list_filters_by_portfolio(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Property A',
            'portfolio' => 'Portfolio A',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Property B',
            'portfolio' => 'Portfolio B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties?portfolio=Portfolio%20A');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Property A')
        );
    }

    public function test_property_list_filters_by_active_status(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Active Property',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Inactive Property',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/properties?is_active=true');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.name', 'Active Property')
        );
    }

    public function test_property_list_sorts_by_name(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Zebra Towers',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Alpha Building',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties?sort=name&direction=asc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('properties.data.0.name', 'Alpha Building')
            ->where('properties.data.1.name', 'Zebra Towers')
        );
    }

    public function test_property_detail_page_displays(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'address_line1' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/properties/{$property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Properties/Show')
            ->has('property')
            ->has('stats')
            ->where('property.name', 'Test Property')
        );
    }

    public function test_property_detail_shows_units(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        Unit::create([
            'external_id' => 'unit-1',
            'property_id' => $property->id,
            'unit_number' => '101',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        Unit::create([
            'external_id' => 'unit-2',
            'property_id' => $property->id,
            'unit_number' => '102',
            'status' => 'vacant',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/properties/{$property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('property.units', 2)
            ->where('stats.total_units', 2)
            ->where('stats.occupied_units', 1)
            ->where('stats.vacant_units', 1)
        );
    }

    public function test_property_pages_require_authentication(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        $this->get('/properties')->assertRedirect('/login');
        $this->get("/properties/{$property->id}")->assertRedirect('/login');
    }

    public function test_property_search_api_returns_matching_properties(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Sunset Apartments',
            'address_line1' => '123 Sunset Blvd',
            'city' => 'Los Angeles',
            'state' => 'CA',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Ocean View',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties/search?q=sunset');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Sunset Apartments']);
    }

    public function test_property_search_api_requires_minimum_query_length(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties/search?q=a');

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    }

    public function test_property_search_api_only_returns_active_properties(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Active Building',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Inactive Building',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/properties/search?q=building');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Active Building']);
    }

    public function test_property_list_sorts_by_name_descending(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Alpha Building',
            'is_active' => true,
        ]);
        Property::create([
            'external_id' => 'prop-2',
            'name' => 'Zebra Towers',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties?sort=name&direction=desc');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('properties.data.0.name', 'Zebra Towers')
            ->where('properties.data.1.name', 'Alpha Building')
        );
    }

    public function test_property_detail_shows_zero_occupancy_for_all_vacant(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        Unit::create([
            'external_id' => 'unit-1',
            'property_id' => $property->id,
            'unit_number' => '101',
            'status' => 'vacant',
            'is_active' => true,
        ]);
        Unit::create([
            'external_id' => 'unit-2',
            'property_id' => $property->id,
            'unit_number' => '102',
            'status' => 'vacant',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/properties/{$property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('stats.occupancy_rate', 0)
            ->where('stats.occupied_units', 0)
            ->where('stats.vacant_units', 2)
        );
    }

    public function test_property_detail_shows_full_occupancy(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        Unit::create([
            'external_id' => 'unit-1',
            'property_id' => $property->id,
            'unit_number' => '101',
            'status' => 'occupied',
            'is_active' => true,
        ]);
        Unit::create([
            'external_id' => 'unit-2',
            'property_id' => $property->id,
            'unit_number' => '102',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/properties/{$property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('stats.occupancy_rate', 100)
            ->where('stats.occupied_units', 2)
            ->where('stats.vacant_units', 0)
        );
    }

    public function test_property_detail_shows_zero_occupancy_for_no_units(): void
    {
        $property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get("/properties/{$property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('stats.occupancy_rate', 0)
            ->where('stats.total_units', 0)
        );
    }

    public function test_property_search_handles_special_characters(): void
    {
        Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test & Property',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get('/properties/search?q=Test%20%26');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    public function test_property_search_validates_max_length(): void
    {
        $longQuery = str_repeat('a', 101);

        $response = $this->actingAs($this->user)->get('/properties/search?q='.$longQuery);

        $response->assertStatus(302); // Validation redirect
    }
}
