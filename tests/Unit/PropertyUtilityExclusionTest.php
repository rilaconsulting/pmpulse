<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyUtilityExclusion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyUtilityExclusionTest extends TestCase
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

    public function test_can_create_property_utility_exclusion(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
            'reason' => 'Tenant pays electric',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('property_utility_exclusions', [
            'id' => $exclusion->id,
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);
    }

    public function test_exclusion_belongs_to_property(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
        ]);

        $this->assertEquals($this->property->id, $exclusion->property->id);
    }

    public function test_exclusion_belongs_to_creator(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'gas',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $exclusion->creator->id);
    }

    public function test_exclusion_creator_can_be_null(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'gas',
            'created_by' => null,
        ]);

        $this->assertNull($exclusion->creator);
    }

    public function test_utility_type_label_attribute_returns_known_type(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);

        $this->assertEquals('Electric', $exclusion->utility_type_label);
    }

    public function test_utility_type_label_attribute_returns_ucfirst_for_unknown_type(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'custom_utility',
        ]);

        $this->assertEquals('Custom_utility', $exclusion->utility_type_label);
    }

    public function test_scope_of_type_filters_by_utility_type(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
        ]);

        $electricExclusions = PropertyUtilityExclusion::ofType('electric')->get();
        $waterExclusions = PropertyUtilityExclusion::ofType('water')->get();

        $this->assertCount(1, $electricExclusions);
        $this->assertCount(1, $waterExclusions);
        $this->assertEquals('electric', $electricExclusions->first()->utility_type);
        $this->assertEquals('water', $waterExclusions->first()->utility_type);
    }

    public function test_scope_for_property_filters_by_property_id(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Test Property 2',
            'is_active' => true,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
        ]);

        $property1Exclusions = PropertyUtilityExclusion::forProperty($this->property->id)->get();
        $property2Exclusions = PropertyUtilityExclusion::forProperty($property2->id)->get();

        $this->assertCount(1, $property1Exclusions);
        $this->assertCount(1, $property2Exclusions);
        $this->assertEquals($this->property->id, $property1Exclusions->first()->property_id);
        $this->assertEquals($property2->id, $property2Exclusions->first()->property_id);
    }

    public function test_get_excluded_property_ids_returns_property_ids_for_utility_type(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Test Property 2',
            'is_active' => true,
        ]);
        $property3 = Property::create([
            'external_id' => 'test-prop-3',
            'name' => 'Test Property 3',
            'is_active' => true,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'electric',
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property3->id,
            'utility_type' => 'water',
        ]);

        $electricExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds('electric');
        $waterExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds('water');
        $gasExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds('gas');

        $this->assertCount(2, $electricExcludedIds);
        $this->assertContains($this->property->id, $electricExcludedIds);
        $this->assertContains($property2->id, $electricExcludedIds);

        $this->assertCount(1, $waterExcludedIds);
        $this->assertContains($property3->id, $waterExcludedIds);

        $this->assertEmpty($gasExcludedIds);
    }

    public function test_is_property_excluded_returns_true_when_excluded(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);

        $this->assertTrue(
            PropertyUtilityExclusion::isPropertyExcluded($this->property->id, 'electric')
        );
    }

    public function test_is_property_excluded_returns_false_when_not_excluded(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);

        $this->assertFalse(
            PropertyUtilityExclusion::isPropertyExcluded($this->property->id, 'water')
        );
    }

    public function test_is_property_excluded_returns_false_for_different_property(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Test Property 2',
            'is_active' => true,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);

        $this->assertFalse(
            PropertyUtilityExclusion::isPropertyExcluded($property2->id, 'electric')
        );
    }

    public function test_exclusion_cascade_deletes_with_property(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);

        $exclusionId = $exclusion->id;
        $this->property->delete();

        $this->assertDatabaseMissing('property_utility_exclusions', ['id' => $exclusionId]);
    }

    public function test_creator_set_to_null_when_user_deleted(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
            'created_by' => $this->user->id,
        ]);

        $this->user->delete();
        $exclusion->refresh();

        $this->assertNull($exclusion->created_by);
    }

    public function test_factory_creates_valid_exclusion(): void
    {
        $exclusion = PropertyUtilityExclusion::factory()->create();

        $this->assertNotNull($exclusion->id);
        $this->assertNotNull($exclusion->property_id);
        $this->assertNotNull($exclusion->utility_type);
    }

    public function test_factory_for_utility_type_creates_with_specific_type(): void
    {
        $exclusion = PropertyUtilityExclusion::factory()
            ->forUtilityType('sewer')
            ->create();

        $this->assertEquals('sewer', $exclusion->utility_type);
    }

    public function test_factory_for_property_creates_with_specific_property(): void
    {
        $exclusion = PropertyUtilityExclusion::factory()
            ->forProperty($this->property)
            ->create();

        $this->assertEquals($this->property->id, $exclusion->property_id);
    }

    public function test_factory_created_by_creates_with_specific_user(): void
    {
        $exclusion = PropertyUtilityExclusion::factory()
            ->createdBy($this->user)
            ->create();

        $this->assertEquals($this->user->id, $exclusion->created_by);
    }

    public function test_combined_scopes_work_together(): void
    {
        $property2 = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Test Property 2',
            'is_active' => true,
        ]);

        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type' => 'electric',
        ]);

        $results = PropertyUtilityExclusion::forProperty($this->property->id)
            ->ofType('electric')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($this->property->id, $results->first()->property_id);
        $this->assertEquals('electric', $results->first()->utility_type);
    }
}
