<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use App\Models\UtilityFormattingRule;
use App\Models\UtilityNote;
use App\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UtilityDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UtilityType $waterType;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'user']);
        $this->user = User::factory()->create(['role_id' => $role->id]);

        // Get utility types (seeded by migration)
        $this->waterType = UtilityType::where('key', 'water')->firstOrFail();
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    // ==================== Basic Access Tests ====================

    public function test_guest_cannot_access_data_endpoint(): void
    {
        $response = $this->get('/utilities/data');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_data_endpoint(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Data', shouldExist: false)
        );
    }

    public function test_data_endpoint_returns_expected_props(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Data', shouldExist: false)
            ->has('propertyComparison')
            ->has('selectedUtilityType')
            ->has('utilityTypes')
            ->has('heatMapStats')
            ->has('filters')
            ->has('propertyTypeOptions')
            ->has('excludedProperties')
        );
    }

    // ==================== Unit Count Filter Tests ====================

    public function test_filter_by_unit_count_min_returns_correct_properties(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        // Create properties with different unit counts
        Property::factory()->create(['name' => 'Small Property', 'unit_count' => 5, 'is_active' => true]);
        Property::factory()->create(['name' => 'Medium Property', 'unit_count' => 20, 'is_active' => true]);
        Property::factory()->create(['name' => 'Large Property', 'unit_count' => 50, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data?unit_count_min=15');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 2)
        );

        $filters = $response->viewData('page')['props']['filters'];
        $this->assertEquals(15, $filters['unit_count_min']);
    }

    public function test_filter_by_unit_count_max_returns_correct_properties(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['name' => 'Small Property', 'unit_count' => 5, 'is_active' => true]);
        Property::factory()->create(['name' => 'Medium Property', 'unit_count' => 20, 'is_active' => true]);
        Property::factory()->create(['name' => 'Large Property', 'unit_count' => 50, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data?unit_count_max=25');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 2)
        );

        $filters = $response->viewData('page')['props']['filters'];
        $this->assertEquals(25, $filters['unit_count_max']);
    }

    public function test_filter_by_unit_count_range_returns_correct_properties(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['name' => 'Small Property', 'unit_count' => 5, 'is_active' => true]);
        Property::factory()->create(['name' => 'Medium Property', 'unit_count' => 20, 'is_active' => true]);
        Property::factory()->create(['name' => 'Large Property', 'unit_count' => 50, 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data?unit_count_min=10&unit_count_max=30');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 1)
        );

        $filters = $response->viewData('page')['props']['filters'];
        $this->assertEquals(10, $filters['unit_count_min']);
        $this->assertEquals(30, $filters['unit_count_max']);
    }

    public function test_unit_count_max_must_be_greater_than_min(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->getJson('/utilities/data?unit_count_min=50&unit_count_max=10');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('unit_count_max');
    }

    // ==================== Property Type Filter Tests ====================

    public function test_filter_by_property_types_returns_correct_properties(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['name' => 'Residential 1', 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Residential 2', 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Commercial', 'property_type' => 'commercial', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data?property_types[]=residential');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('filters.property_types', ['residential'])
            ->has('propertyComparison.properties', 2)
        );
    }

    public function test_filter_by_multiple_property_types(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['name' => 'Residential', 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Commercial', 'property_type' => 'commercial', 'is_active' => true]);
        Property::factory()->create(['name' => 'Industrial', 'property_type' => 'industrial', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data?property_types[]=residential&property_types[]=commercial');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 2)
        );
    }

    // ==================== Combined Filters Tests ====================

    public function test_combined_filters_work_correctly(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        // Create various properties
        Property::factory()->create(['name' => 'Small Residential', 'unit_count' => 5, 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Large Residential', 'unit_count' => 50, 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Small Commercial', 'unit_count' => 5, 'property_type' => 'commercial', 'is_active' => true]);
        Property::factory()->create(['name' => 'Large Commercial', 'unit_count' => 50, 'property_type' => 'commercial', 'is_active' => true]);

        // Filter: residential with unit count >= 10
        $response = $this->actingAs($this->user)->get('/utilities/data?property_types[]=residential&unit_count_min=10');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 1)
        );
    }

    public function test_all_filters_combined(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['name' => 'Target', 'unit_count' => 25, 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Too Small', 'unit_count' => 5, 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Too Large', 'unit_count' => 100, 'property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['name' => 'Wrong Type', 'unit_count' => 25, 'property_type' => 'commercial', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data?property_types[]=residential&unit_count_min=10&unit_count_max=50');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 1)
        );
    }

    // ==================== Heat Map Stats Tests ====================

    public function test_heat_map_stats_calculated_for_filtered_data(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $waterAccount = UtilityAccount::factory()->water()->create();

        $property1 = Property::factory()->create(['name' => 'Property 1', 'unit_count' => 10, 'is_active' => true]);
        $property2 = Property::factory()->create(['name' => 'Property 2', 'unit_count' => 20, 'is_active' => true]);

        // Create expenses
        UtilityExpense::factory()->forProperty($property1)->forAccount($waterAccount)->create([
            'amount' => 100,
            'expense_date' => now(),
        ]);
        UtilityExpense::factory()->forProperty($property2)->forAccount($waterAccount)->create([
            'amount' => 200,
            'expense_date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/data');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('heatMapStats')
            ->has('heatMapStats.per_unit')
            ->has('heatMapStats.per_sqft')
        );
    }

    public function test_heat_map_stats_structure(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();
        Property::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data');

        $response->assertStatus(200);
        $heatMapStats = $response->viewData('page')['props']['heatMapStats'];

        // Check per_unit stats structure
        $this->assertArrayHasKey('per_unit', $heatMapStats);
        $this->assertArrayHasKey('avg', $heatMapStats['per_unit']);
        $this->assertArrayHasKey('min', $heatMapStats['per_unit']);
        $this->assertArrayHasKey('max', $heatMapStats['per_unit']);
        $this->assertArrayHasKey('count', $heatMapStats['per_unit']);

        // Check per_sqft stats structure
        $this->assertArrayHasKey('per_sqft', $heatMapStats);
        $this->assertArrayHasKey('avg', $heatMapStats['per_sqft']);
        $this->assertArrayHasKey('min', $heatMapStats['per_sqft']);
        $this->assertArrayHasKey('max', $heatMapStats['per_sqft']);
        $this->assertArrayHasKey('count', $heatMapStats['per_sqft']);
    }

    // ==================== Conditional Formatting Tests ====================

    public function test_conditional_formatting_applied_to_response(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $waterAccount = UtilityAccount::factory()->water()->create();

        // Create formatting rule
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 10,
            'color' => '#FF0000',
            'background_color' => '#FFEEEE',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $property = Property::factory()->create(['is_active' => true, 'unit_count' => 10]);

        // Create expenses with significant increase
        // Historical expenses (for 12-month average)
        for ($i = 1; $i <= 12; $i++) {
            UtilityExpense::factory()->forProperty($property)->forAccount($waterAccount)->create([
                'amount' => 100,
                'expense_date' => now()->subMonths($i),
            ]);
        }

        // Current month expense (50% higher)
        UtilityExpense::factory()->forProperty($property)->forAccount($waterAccount)->create([
            'amount' => 150,
            'expense_date' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=water');

        $response->assertStatus(200);
        $properties = $response->viewData('page')['props']['propertyComparison']['properties'];

        // Find our property - use explicit assertions instead of conditional checks
        $targetProperty = collect($properties)->firstWhere('property_id', $property->id);
        $this->assertNotNull($targetProperty, 'Expected property not found in response');

        // Should have formatting applied (current month is 50% higher than average)
        $this->assertArrayHasKey('formatting', $targetProperty, 'Formatting should be applied when value exceeds threshold');
        $this->assertArrayHasKey('current_month', $targetProperty['formatting']);
    }

    public function test_formatting_not_applied_when_no_rules_match(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $waterAccount = UtilityAccount::factory()->water()->create();

        // Create formatting rule with high threshold
        UtilityFormattingRule::factory()->create([
            'utility_type_id' => $this->waterType->id,
            'operator' => 'increase_percent',
            'threshold' => 100, // Very high threshold
            'color' => '#FF0000',
            'enabled' => true,
            'created_by' => $this->user->id,
        ]);

        $property = Property::factory()->create(['is_active' => true, 'unit_count' => 10]);

        // Create consistent expenses (no significant change)
        for ($i = 0; $i <= 12; $i++) {
            UtilityExpense::factory()->forProperty($property)->forAccount($waterAccount)->create([
                'amount' => 100,
                'expense_date' => now()->subMonths($i),
            ]);
        }

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=water');

        $response->assertStatus(200);
        $properties = $response->viewData('page')['props']['propertyComparison']['properties'];

        // Use explicit assertions instead of conditional checks
        $targetProperty = collect($properties)->firstWhere('property_id', $property->id);
        $this->assertNotNull($targetProperty, 'Expected property not found in response');

        // Should not have formatting (no significant change - threshold not met)
        $this->assertTrue(
            ! isset($targetProperty['formatting']) ||
            empty($targetProperty['formatting']),
            'Formatting should not be applied when threshold is not met'
        );
    }

    // ==================== Utility Type Selection Tests ====================

    public function test_utility_type_selection_filters_data(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();
        UtilityAccount::factory()->electric()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=electric');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('selectedUtilityType', 'electric')
        );
    }

    public function test_invalid_utility_type_defaults_to_first_available(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=invalid_type');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('selectedUtilityType', 'water')
        );
    }

    // ==================== Property Type Options Tests ====================

    public function test_property_type_options_returned(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['property_type' => 'residential', 'is_active' => true]);
        Property::factory()->create(['property_type' => 'commercial', 'is_active' => true]);

        $response = $this->actingAs($this->user)->get('/utilities/data');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyTypeOptions')
        );

        // propertyTypeOptions is an associative array: property_type => count
        $propertyTypes = $response->viewData('page')['props']['propertyTypeOptions'];
        $this->assertArrayHasKey('residential', $propertyTypes);
        $this->assertArrayHasKey('commercial', $propertyTypes);
        $this->assertEquals(2, $propertyTypes['residential']);
        $this->assertEquals(1, $propertyTypes['commercial']);
    }

    // ==================== Notes Integration Tests ====================

    public function test_notes_attached_to_property_comparison_data(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $property = Property::factory()->create(['is_active' => true]);

        UtilityNote::factory()->create([
            'property_id' => $property->id,
            'utility_type_id' => $this->waterType->id,
            'note' => 'Test note content',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=water');

        $response->assertStatus(200);

        $properties = $response->viewData('page')['props']['propertyComparison']['properties'];

        // Use explicit assertions instead of conditional checks
        $targetProperty = collect($properties)->firstWhere('property_id', $property->id);
        $this->assertNotNull($targetProperty, 'Expected property not found in response');

        $this->assertArrayHasKey('note', $targetProperty, 'Note should be attached to property data');
        $this->assertEquals('Test note content', $targetProperty['note']['note']);
    }

    // ==================== Validation Tests ====================

    public function test_unit_count_min_must_be_non_negative(): void
    {
        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->getJson('/utilities/data?unit_count_min=-5');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('unit_count_min');
    }

    public function test_unit_count_max_must_be_non_negative(): void
    {
        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->getJson('/utilities/data?unit_count_max=-5');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('unit_count_max');
    }

    public function test_property_types_must_be_array(): void
    {
        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->getJson('/utilities/data?property_types=residential');

        // When passing a string instead of array, Laravel may convert it or reject it
        // The behavior depends on how the request is parsed
        $response->assertStatus(422);
    }

    // ==================== Empty Data Tests ====================

    public function test_returns_empty_properties_when_no_properties_exist(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 0)
        );
    }

    public function test_returns_empty_when_filters_match_no_properties(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        Property::factory()->create(['unit_count' => 10, 'is_active' => true]);

        // Filter for properties with 1000+ units (none exist)
        $response = $this->actingAs($this->user)->get('/utilities/data?unit_count_min=1000');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('propertyComparison.properties', 0)
        );
    }
}
