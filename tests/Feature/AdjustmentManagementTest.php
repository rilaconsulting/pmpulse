<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyAdjustment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin']);
        $viewerRole = Role::create(['name' => 'viewer']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->viewer = User::factory()->create(['role_id' => $viewerRole->id]);
        $this->property = Property::create([
            'external_id' => 'test-prop',
            'name' => 'Test Property',
            'is_active' => true,
            'unit_count' => 10,
            'total_sqft' => 5000,
        ]);
    }

    public function test_admin_can_create_adjustment(): void
    {
        $response = $this->actingAs($this->admin)->post("/properties/{$this->property->id}/adjustments", [
            'field_name' => 'unit_count',
            'adjusted_value' => 15,
            'effective_from' => now()->toDateString(),
            'reason' => 'Adding new units under construction',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('property_adjustments', [
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'adjusted_value' => '15',
        ]);
    }

    public function test_non_admin_cannot_create_adjustment(): void
    {
        $response = $this->actingAs($this->viewer)->post("/properties/{$this->property->id}/adjustments", [
            'field_name' => 'unit_count',
            'adjusted_value' => 15,
            'effective_from' => now()->toDateString(),
            'reason' => 'Test reason',
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_adjustment(): void
    {
        $response = $this->post("/properties/{$this->property->id}/adjustments", [
            'field_name' => 'unit_count',
            'adjusted_value' => 15,
            'effective_from' => now()->toDateString(),
            'reason' => 'Test reason',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_update_adjustment(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now(),
            'reason' => 'Original reason',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->patch(
            "/properties/{$this->property->id}/adjustments/{$adjustment->id}",
            [
                'adjusted_value' => 20,
                'reason' => 'Updated reason',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('property_adjustments', [
            'id' => $adjustment->id,
            'adjusted_value' => '20',
            'reason' => 'Updated reason',
        ]);
    }

    public function test_admin_can_end_permanent_adjustment(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now()->subDays(10),
            'effective_to' => null,
            'reason' => 'Permanent adjustment',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/properties/{$this->property->id}/adjustments/{$adjustment->id}/end"
        );

        $response->assertRedirect();
        $adjustment->refresh();
        $this->assertNotNull($adjustment->effective_to);
    }

    public function test_admin_can_delete_adjustment(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now(),
            'reason' => 'Test adjustment',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(
            "/properties/{$this->property->id}/adjustments/{$adjustment->id}"
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('property_adjustments', [
            'id' => $adjustment->id,
        ]);
    }

    public function test_invalid_field_name_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post("/properties/{$this->property->id}/adjustments", [
            'field_name' => 'invalid_field',
            'adjusted_value' => 15,
            'effective_from' => now()->toDateString(),
            'reason' => 'Test reason',
        ]);

        $response->assertSessionHasErrors('field_name');
    }

    public function test_effective_to_must_be_after_effective_from(): void
    {
        $response = $this->actingAs($this->admin)->post("/properties/{$this->property->id}/adjustments", [
            'field_name' => 'unit_count',
            'adjusted_value' => 15,
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->subDay()->toDateString(),
            'reason' => 'Test reason',
        ]);

        $response->assertSessionHasErrors('effective_to');
    }

    public function test_property_show_includes_adjustments_for_admin(): void
    {
        PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now(),
            'reason' => 'Test adjustment',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get("/properties/{$this->property->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Properties/Show')
            ->has('activeAdjustments', 1)
            ->has('adjustableFields')
            ->has('effectiveValues')
        );
    }

    public function test_non_admin_cannot_update_adjustment(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now(),
            'reason' => 'Original reason',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->viewer)->patch(
            "/properties/{$this->property->id}/adjustments/{$adjustment->id}",
            [
                'adjusted_value' => 20,
                'reason' => 'Updated reason',
            ]
        );

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_end_adjustment(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now()->subDays(10),
            'effective_to' => null,
            'reason' => 'Permanent adjustment',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->viewer)->post(
            "/properties/{$this->property->id}/adjustments/{$adjustment->id}/end"
        );

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_delete_adjustment(): void
    {
        $adjustment = PropertyAdjustment::create([
            'property_id' => $this->property->id,
            'field_name' => 'unit_count',
            'original_value' => '10',
            'adjusted_value' => '15',
            'effective_from' => now(),
            'reason' => 'Test adjustment',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->viewer)->delete(
            "/properties/{$this->property->id}/adjustments/{$adjustment->id}"
        );

        $response->assertForbidden();
    }
}
