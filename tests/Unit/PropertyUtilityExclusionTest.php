<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\PropertyUtilityExclusion;
use App\Models\Role;
use App\Models\User;
use App\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyUtilityExclusionTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private User $user;

    private UtilityType $electricType;

    private UtilityType $waterType;

    private UtilityType $gasType;

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

        // Get the seeded utility types
        $this->electricType = UtilityType::findByKey('electric');
        $this->waterType = UtilityType::findByKey('water');
        $this->gasType = UtilityType::findByKey('gas');
    }

    public function test_can_create_property_utility_exclusion(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
            'reason' => 'Tenant pays electric',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('property_utility_exclusions', [
            'id' => $exclusion->id,
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);
    }

    public function test_exclusion_belongs_to_property(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->waterType->id,
        ]);

        $this->assertEquals($this->property->id, $exclusion->property->id);
    }

    public function test_exclusion_belongs_to_creator(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->gasType->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals($this->user->id, $exclusion->creator->id);
    }

    public function test_exclusion_creator_can_be_null(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->gasType->id,
            'created_by' => null,
        ]);

        $this->assertNull($exclusion->creator);
    }

    public function test_utility_type_label_attribute_returns_known_type(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $this->assertEquals('Electric', $exclusion->utility_type_label);
    }

    public function test_utility_type_relationship_returns_utility_type(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $this->assertInstanceOf(UtilityType::class, $exclusion->utilityType);
        $this->assertEquals($this->electricType->id, $exclusion->utilityType->id);
        $this->assertEquals('electric', $exclusion->utilityType->key);
    }

    public function test_scope_of_type_filters_by_utility_type_id(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->waterType->id,
        ]);

        $electricExclusions = PropertyUtilityExclusion::ofType($this->electricType->id)->get();
        $waterExclusions = PropertyUtilityExclusion::ofType($this->waterType->id)->get();

        $this->assertCount(1, $electricExclusions);
        $this->assertCount(1, $waterExclusions);
        $this->assertEquals($this->electricType->id, $electricExclusions->first()->utility_type_id);
        $this->assertEquals($this->waterType->id, $waterExclusions->first()->utility_type_id);
    }

    public function test_scope_of_type_key_filters_by_utility_type_key(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->waterType->id,
        ]);

        $electricExclusions = PropertyUtilityExclusion::ofTypeKey('electric')->get();
        $waterExclusions = PropertyUtilityExclusion::ofTypeKey('water')->get();

        $this->assertCount(1, $electricExclusions);
        $this->assertCount(1, $waterExclusions);
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
            'utility_type_id' => $this->electricType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type_id' => $this->waterType->id,
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
            'utility_type_id' => $this->electricType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type_id' => $this->electricType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property3->id,
            'utility_type_id' => $this->waterType->id,
        ]);

        $electricExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds($this->electricType->id);
        $waterExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds($this->waterType->id);
        $gasExcludedIds = PropertyUtilityExclusion::getExcludedPropertyIds($this->gasType->id);

        $this->assertCount(2, $electricExcludedIds);
        $this->assertContains($this->property->id, $electricExcludedIds);
        $this->assertContains($property2->id, $electricExcludedIds);

        $this->assertCount(1, $waterExcludedIds);
        $this->assertContains($property3->id, $waterExcludedIds);

        $this->assertEmpty($gasExcludedIds);
    }

    public function test_get_excluded_property_ids_by_type_key(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $excludedIds = PropertyUtilityExclusion::getExcludedPropertyIdsByTypeKey('electric');

        $this->assertCount(1, $excludedIds);
        $this->assertContains($this->property->id, $excludedIds);
    }

    public function test_is_property_excluded_returns_true_when_excluded(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $this->assertTrue(
            PropertyUtilityExclusion::isPropertyExcluded($this->property->id, $this->electricType->id)
        );
    }

    public function test_is_property_excluded_returns_false_when_not_excluded(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $this->assertFalse(
            PropertyUtilityExclusion::isPropertyExcluded($this->property->id, $this->waterType->id)
        );
    }

    public function test_is_property_excluded_by_type_key(): void
    {
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $this->assertTrue(
            PropertyUtilityExclusion::isPropertyExcludedByTypeKey($this->property->id, 'electric')
        );
        $this->assertFalse(
            PropertyUtilityExclusion::isPropertyExcludedByTypeKey($this->property->id, 'water')
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
            'utility_type_id' => $this->electricType->id,
        ]);

        $this->assertFalse(
            PropertyUtilityExclusion::isPropertyExcluded($property2->id, $this->electricType->id)
        );
    }

    public function test_exclusion_cascade_deletes_with_property(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $exclusionId = $exclusion->id;
        $this->property->delete();

        $this->assertDatabaseMissing('property_utility_exclusions', ['id' => $exclusionId]);
    }

    public function test_creator_set_to_null_when_user_deleted(): void
    {
        $exclusion = PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->electricType->id,
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
        $this->assertNotNull($exclusion->utility_type_id);
    }

    public function test_factory_for_utility_type_creates_with_specific_type(): void
    {
        $sewerType = UtilityType::findByKey('sewer');
        $exclusion = PropertyUtilityExclusion::factory()
            ->forUtilityType($sewerType)
            ->create();

        $this->assertEquals($sewerType->id, $exclusion->utility_type_id);
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
            'utility_type_id' => $this->electricType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $this->property->id,
            'utility_type_id' => $this->waterType->id,
        ]);
        PropertyUtilityExclusion::create([
            'property_id' => $property2->id,
            'utility_type_id' => $this->electricType->id,
        ]);

        $results = PropertyUtilityExclusion::forProperty($this->property->id)
            ->ofType($this->electricType->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($this->property->id, $results->first()->property_id);
        $this->assertEquals($this->electricType->id, $results->first()->utility_type_id);
    }
}
