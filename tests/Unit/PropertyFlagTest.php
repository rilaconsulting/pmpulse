<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyFlag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyFlagTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'external_id' => 'test-prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        $role = Role::create(['name' => 'admin']);
        $this->user = User::factory()->create(['role_id' => $role->id]);
    }

    public function test_can_create_property_flag(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA managed property',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('property_flags', [
            'id' => $flag->id,
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);
    }

    public function test_flag_belongs_to_property(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $this->assertEquals($this->property->id, $flag->property->id);
    }

    public function test_flag_belongs_to_creator(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $flag->creator->id);
    }

    public function test_property_has_many_flags(): void
    {
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'under_renovation',
        ]);

        $this->assertCount(2, $this->property->flags);
    }

    public function test_unique_constraint_on_property_and_flag_type(): void
    {
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);
    }

    public function test_property_has_flag_method(): void
    {
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $this->assertTrue($this->property->hasFlag('hoa'));
        $this->assertFalse($this->property->hasFlag('sold'));
    }

    public function test_flag_label_attribute(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'tenant_pays_utilities',
        ]);

        $this->assertEquals('Tenant Pays Utilities', $flag->flag_label);
    }

    public function test_excludes_from_reports_method(): void
    {
        $excludeFlag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $hoaFlag = PropertyFlag::create([
            'property_id' => Property::create([
                'external_id' => 'test-prop-2',
                'name' => 'Test Property 2',
                'is_active' => true,
            ])->id,
            'flag_type' => 'hoa',
        ]);

        $this->assertTrue($excludeFlag->excludesFromReports());
        $this->assertFalse($hoaFlag->excludesFromReports());
    }

    public function test_excludes_from_utility_reports_method(): void
    {
        $hoaFlag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $tenantPaysFlag = PropertyFlag::create([
            'property_id' => Property::create([
                'external_id' => 'test-prop-2',
                'name' => 'Test Property 2',
                'is_active' => true,
            ])->id,
            'flag_type' => 'tenant_pays_utilities',
        ]);

        $excludeFromReportsFlag = PropertyFlag::create([
            'property_id' => Property::create([
                'external_id' => 'test-prop-3',
                'name' => 'Test Property 3',
                'is_active' => true,
            ])->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $renovationFlag = PropertyFlag::create([
            'property_id' => Property::create([
                'external_id' => 'test-prop-4',
                'name' => 'Test Property 4',
                'is_active' => true,
            ])->id,
            'flag_type' => 'under_renovation',
        ]);

        $this->assertTrue($hoaFlag->excludesFromUtilityReports());
        $this->assertTrue($tenantPaysFlag->excludesFromUtilityReports());
        $this->assertTrue($excludeFromReportsFlag->excludesFromUtilityReports());
        $this->assertFalse($renovationFlag->excludesFromUtilityReports());
    }

    public function test_property_is_excluded_from_reports(): void
    {
        $this->assertFalse($this->property->isExcludedFromReports());

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $this->assertTrue($this->property->fresh()->isExcludedFromReports());
    }

    public function test_property_is_excluded_from_utility_reports(): void
    {
        $this->assertFalse($this->property->isExcludedFromUtilityReports());

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $this->assertTrue($this->property->fresh()->isExcludedFromUtilityReports());
    }

    public function test_flag_cascade_deletes_with_property(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $flagId = $flag->id;
        $this->property->delete();

        $this->assertDatabaseMissing('property_flags', ['id' => $flagId]);
    }

    public function test_creator_set_to_null_when_user_deleted(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'created_by' => $this->user->id,
        ]);

        $this->user->delete();
        $flag->refresh();

        $this->assertNull($flag->created_by);
    }

    public function test_scope_without_flag_excludes_flagged_properties(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Property 2',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $results = Property::withoutFlag('exclude_from_reports')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($property2->id, $results->first()->id);
    }

    public function test_scope_without_flags_excludes_multiple_flag_types(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Property 2',
            'is_active' => true,
        ]);
        $property3 = Property::create([
            'external_id' => 'test-prop-3',
            'name' => 'Property 3',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);
        PropertyFlag::create([
            'property_id' => $property2->id,
            'flag_type' => 'tenant_pays_utilities',
        ]);

        $results = Property::withoutFlags(['hoa', 'tenant_pays_utilities'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($property3->id, $results->first()->id);
    }

    public function test_scope_for_reports_excludes_flagged_properties(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Property 2',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $results = Property::forReports()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($property2->id, $results->first()->id);
    }

    public function test_scope_for_utility_reports_excludes_utility_flags(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Property 2',
            'is_active' => true,
        ]);
        $property3 = Property::create([
            'external_id' => 'test-prop-3',
            'name' => 'Property 3',
            'is_active' => true,
        ]);
        $property4 = Property::create([
            'external_id' => 'test-prop-4',
            'name' => 'Property 4',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);
        PropertyFlag::create([
            'property_id' => $property2->id,
            'flag_type' => 'tenant_pays_utilities',
        ]);
        PropertyFlag::create([
            'property_id' => $property3->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $results = Property::forUtilityReports()->get();

        // Only property4 should be included (no exclusion flags)
        $this->assertCount(1, $results);
        $this->assertEquals($property4->id, $results->first()->id);
    }

    public function test_scope_with_flag_returns_only_flagged_properties(): void
    {
        Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Property 2',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'sold',
        ]);

        $results = Property::withFlag('sold')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($this->property->id, $results->first()->id);
    }

    public function test_combined_scopes_work_together(): void
    {
        $inactiveProperty = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Property 2',
            'is_active' => false,
        ]);
        $activeUnflagged = Property::create([
            'external_id' => 'test-prop-3',
            'name' => 'Property 3',
            'is_active' => true,
        ]);

        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'exclude_from_reports',
        ]);

        $results = Property::active()->forReports()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($activeUnflagged->id, $results->first()->id);
    }
}
