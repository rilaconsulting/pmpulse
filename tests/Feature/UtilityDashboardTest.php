<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyFlag;
use App\Models\PropertyUtilityExclusion;
use App\Models\Role;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityExpense;
use App\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UtilityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $property;

    private UtilityType $waterType;

    private UtilityType $electricType;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'user']);
        $this->user = User::factory()->create(['role_id' => $role->id]);

        $this->property = Property::create([
            'external_id' => 'prop-1',
            'name' => 'Test Property',
            'is_active' => true,
            'unit_count' => 10,
            'total_sqft' => 10000,
        ]);

        // Get utility types (seeded by migration)
        $this->waterType = UtilityType::where('key', 'water')->firstOrFail();
        $this->electricType = UtilityType::where('key', 'electric')->firstOrFail();
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    // ==================== Index Page Tests ====================
    // Note: Index endpoint tests require PostgreSQL due to DATE_TRUNC usage

    public function test_guest_cannot_view_utilities_index(): void
    {
        $response = $this->get('/utilities');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_utilities_index(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Index endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Dashboard', shouldExist: false)
        );
    }

    public function test_utilities_index_returns_expected_props(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Index endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Dashboard', shouldExist: false)
            ->has('period')
            ->has('periodLabel')
            ->has('utilitySummary')
            ->has('portfolioTotal')
            ->has('anomalies')
            ->has('trendData')
            ->has('utilityTypes')
            ->has('excludedProperties')
        );
    }

    public function test_utilities_index_default_period_is_month(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Index endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'month')
        );
    }

    public function test_utilities_index_accepts_valid_period_parameter(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Index endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $validPeriods = ['month', 'last_month', 'last_3_months', 'last_6_months', 'last_12_months', 'quarter', 'ytd', 'year'];

        foreach ($validPeriods as $period) {
            $response = $this->actingAs($this->user)->get("/utilities/dashboard?period={$period}");

            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page
                ->where('period', $period)
            );
        }
    }

    public function test_utilities_index_invalid_period_defaults_to_month(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Index endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $response = $this->actingAs($this->user)->get('/utilities/dashboard?period=invalid_period');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'month')
        );
    }

    public function test_utilities_data_accepts_utility_type_parameter(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->electric()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=electric');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Data', shouldExist: false)
            ->where('selectedUtilityType', 'electric')
        );
    }

    public function test_utilities_data_invalid_utility_type_uses_first_available(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Data endpoint requires PostgreSQL for DATE_TRUNC');
        }

        UtilityAccount::factory()->water()->create();

        $response = $this->actingAs($this->user)->get('/utilities/data?utility_type=invalid_type');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Data', shouldExist: false)
            ->where('selectedUtilityType', 'water')
        );
    }

    // ==================== Show Page Tests ====================
    // Note: Show endpoint tests work on all databases

    public function test_guest_cannot_view_property_utilities(): void
    {
        $response = $this->get("/utilities/property/{$this->property->id}");

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_property_utilities(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Show')
        );
    }

    public function test_property_utilities_returns_expected_props(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Utilities/Show')
            ->has('property')
            ->has('period')
            ->has('periodLabel')
            ->has('costBreakdown')
            ->has('comparisons')
            ->has('propertyTrend')
            ->has('recentExpenses')
            ->has('utilityTypes')
        );
    }

    public function test_property_utilities_returns_correct_property_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('property.id', $this->property->id)
            ->where('property.name', 'Test Property')
            ->where('property.unit_count', 10)
            ->where('property.total_sqft', 10000)
        );
    }

    public function test_property_utilities_returns_404_for_nonexistent_property(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$fakeId}");

        $response->assertStatus(404);
    }

    public function test_property_utilities_default_period_is_month(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'month')
        );
    }

    public function test_property_utilities_accepts_valid_period_parameter(): void
    {
        $validPeriods = ['month', 'last_month', 'last_3_months', 'quarter', 'ytd', 'year'];

        foreach ($validPeriods as $period) {
            $response = $this->actingAs($this->user)
                ->get("/utilities/property/{$this->property->id}?period={$period}");

            $response->assertStatus(200);
            $response->assertInertia(fn ($page) => $page
                ->where('period', $period)
            );
        }
    }

    public function test_property_utilities_invalid_period_defaults_to_month(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}?period=invalid_period");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('period', 'month')
        );
    }

    public function test_recent_expenses_returns_expense_data(): void
    {
        $utilityAccount = UtilityAccount::factory()->electric()->create();

        UtilityExpense::factory()
            ->forProperty($this->property)
            ->forAccount($utilityAccount)
            ->create([
                'amount' => 150.00,
                'expense_date' => now()->subDays(5),
                'vendor_name' => 'Electric Co',
            ]);

        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('recentExpenses', 1)
            ->has('recentExpenses.0', fn ($expense) => $expense
                ->has('id')
                ->has('utility_type')
                ->has('utility_label')
                ->has('amount')
                ->has('expense_date')
                ->has('vendor_name')
            )
        );
    }

    public function test_property_utilities_returns_comparisons_for_all_utility_types(): void
    {
        // The controller returns comparisons for all configured utility types
        // even when no expenses exist (all values will be 0)
        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);

        // Get the comparisons from the response
        $comparisons = $response->viewData('page')['props']['comparisons'];

        // Should have 6 default utility types
        $this->assertCount(6, $comparisons);

        // Each comparison should have the expected structure
        foreach ($comparisons as $comparison) {
            $this->assertArrayHasKey('type', $comparison);
            $this->assertArrayHasKey('label', $comparison);
            $this->assertArrayHasKey('current_month', $comparison);
            $this->assertArrayHasKey('previous_month', $comparison);
            $this->assertArrayHasKey('month_change', $comparison);
        }
    }

    public function test_property_utilities_with_expenses_updates_comparison_values(): void
    {
        $waterAccount = UtilityAccount::factory()->water()->create();
        $electricAccount = UtilityAccount::factory()->electric()->create();

        UtilityExpense::factory()
            ->forProperty($this->property)
            ->forAccount($waterAccount)
            ->create(['amount' => 100.00, 'expense_date' => now()]);

        UtilityExpense::factory()
            ->forProperty($this->property)
            ->forAccount($electricAccount)
            ->create(['amount' => 200.00, 'expense_date' => now()]);

        $response = $this->actingAs($this->user)
            ->get("/utilities/property/{$this->property->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            // All default utility types are returned
            ->has('comparisons')
            // Both expenses are in recent expenses
            ->has('recentExpenses', 2)
        );
    }

    // ==================== Excluded Properties Info Tests ====================
    // Note: These tests verify the getExcludedPropertiesInfo method via the index endpoint

    public function test_excluded_properties_returns_empty_when_no_exclusions(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Index endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 0)
            ->where('excludedProperties.flag_excluded_count', 0)
            ->where('excludedProperties.utility_excluded_count', 0)
            ->has('excludedProperties.properties', 0)
        );
    }

    public function test_excluded_properties_returns_flag_based_exclusions(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        // Create a property with an HOA flag (utility exclusion flag)
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA manages utilities',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 1)
            ->where('excludedProperties.flag_excluded_count', 1)
            ->where('excludedProperties.utility_excluded_count', 0)
            ->has('excludedProperties.properties', 1)
            ->where('excludedProperties.properties.0.id', $this->property->id)
            ->where('excludedProperties.properties.0.exclusion_type', 'all_utilities')
            ->has('excludedProperties.properties.0.flags', 1)
            ->where('excludedProperties.properties.0.flags.0.type', 'hoa')
            ->where('excludedProperties.properties.0.flags.0.label', 'HOA Property')
        );
    }

    public function test_excluded_properties_returns_tenant_pays_utilities_flag(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'tenant_pays_utilities',
            'reason' => 'Tenants pay all utilities directly',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.flag_excluded_count', 1)
            ->where('excludedProperties.properties.0.flags.0.type', 'tenant_pays_utilities')
        );
    }

    public function test_excluded_properties_returns_utility_specific_exclusions(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        // Create a utility-specific exclusion
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
            'reason' => 'Tenant pays electric',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 1)
            ->where('excludedProperties.flag_excluded_count', 0)
            ->where('excludedProperties.utility_excluded_count', 1)
            ->has('excludedProperties.properties', 1)
            ->where('excludedProperties.properties.0.id', $this->property->id)
            ->where('excludedProperties.properties.0.exclusion_type', 'specific_utilities')
            ->has('excludedProperties.properties.0.utility_exclusions', 1)
            ->where('excludedProperties.properties.0.utility_exclusions.0.utility_type', 'electric')
        );
    }

    public function test_excluded_properties_utility_exclusion_includes_creator(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->waterType->id,
            'reason' => 'Well water',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.properties.0.utility_exclusions.0.created_by', $this->user->name)
        );
    }

    public function test_excluded_properties_filters_duplicates_flag_takes_priority(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        // Create both a flag and utility exclusion for the same property
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA manages utilities',
            'created_by' => $this->user->id,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
            'reason' => 'Tenant pays electric',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        // Should only appear once (as flag-based exclusion, which takes priority)
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 1)
            ->where('excludedProperties.flag_excluded_count', 1)
            ->where('excludedProperties.utility_excluded_count', 0)
            ->where('excludedProperties.properties.0.exclusion_type', 'all_utilities')
        );
    }

    public function test_excluded_properties_multiple_utility_exclusions_for_same_property(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        // Create multiple utility-specific exclusions for same property
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
            'reason' => 'Tenant pays electric',
            'created_by' => $this->user->id,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->waterType->id,
            'reason' => 'Well water',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 1)
            ->has('excludedProperties.properties', 1)
            ->has('excludedProperties.properties.0.utility_exclusions', 2)
        );
    }

    public function test_excluded_properties_excludes_inactive_properties(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        // Make the property inactive
        $this->property->update(['is_active' => false]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA manages utilities',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 0)
        );
    }

    public function test_excluded_properties_sorted_by_property_name(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        $propertyZ = Property::create([
            'external_id' => 'prop-z',
            'name' => 'Zebra Property',
            'is_active' => true,
        ]);

        $propertyA = Property::create([
            'external_id' => 'prop-a',
            'name' => 'Alpha Property',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $propertyZ->id,
            'flag_type' => 'hoa',
            'created_by' => $this->user->id,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $propertyA->id,
            'utility_type_id' => $this->electricType->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 2)
            // Alpha should come before Zebra (sorted by name)
            ->where('excludedProperties.properties.0.name', 'Alpha Property')
            ->where('excludedProperties.properties.1.name', 'Zebra Property')
        );
    }

    public function test_excluded_properties_multiple_flags_same_property(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA manages utilities',
            'created_by' => $this->user->id,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'tenant_pays_utilities',
            'reason' => 'Tenants pay all utilities',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 1)
            ->has('excludedProperties.properties.0.flags', 2)
        );
    }

    public function test_excluded_properties_non_utility_flags_not_included(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        // Create a non-utility exclusion flag (under_renovation)
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'under_renovation',
            'reason' => 'Major renovation',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('excludedProperties.total_count', 0)
        );
    }

    public function test_excluded_properties_response_format(): void
    {
        if (! $this->isPostgres()) {
            $this->markTestSkipped('Dashboard endpoint requires PostgreSQL for DATE_TRUNC');
        }

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA reason',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/utilities/dashboard');

        $excludedProperties = $response->viewData('page')['props']['excludedProperties'];

        // Check top-level structure
        $this->assertArrayHasKey('total_count', $excludedProperties);
        $this->assertArrayHasKey('flag_excluded_count', $excludedProperties);
        $this->assertArrayHasKey('utility_excluded_count', $excludedProperties);
        $this->assertArrayHasKey('properties', $excludedProperties);

        // Check property structure
        $property = $excludedProperties['properties'][0];
        $this->assertArrayHasKey('id', $property);
        $this->assertArrayHasKey('name', $property);
        $this->assertArrayHasKey('exclusion_type', $property);
        $this->assertArrayHasKey('flags', $property);
        $this->assertArrayHasKey('utility_exclusions', $property);

        // Check flag structure
        $flag = $property['flags'][0];
        $this->assertArrayHasKey('type', $flag);
        $this->assertArrayHasKey('label', $flag);
        $this->assertArrayHasKey('reason', $flag);
    }
}
