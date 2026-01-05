<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyFlag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyFlagManagementTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'external_id' => 'test-prop-1',
            'name' => 'Test Property',
            'is_active' => true,
        ]);

        $adminRole = Role::create(['name' => 'admin']);
        $memberRole = Role::create(['name' => 'member']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $memberRole->id]);
    }

    public function test_admin_can_add_flag_to_property(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post("/properties/{$this->property->id}/flags", [
                'flag_type' => 'hoa',
                'reason' => 'HOA managed property',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('property_flags', [
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'HOA managed property',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_non_admin_cannot_add_flag(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->post("/properties/{$this->property->id}/flags", [
                'flag_type' => 'hoa',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('property_flags', [
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);
    }

    public function test_unauthenticated_user_cannot_add_flag(): void
    {
        $response = $this->post("/properties/{$this->property->id}/flags", [
            'flag_type' => 'hoa',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_remove_flag(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete("/properties/{$this->property->id}/flags/{$flag->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('property_flags', [
            'id' => $flag->id,
        ]);
    }

    public function test_non_admin_cannot_remove_flag(): void
    {
        $flag = PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->delete("/properties/{$this->property->id}/flags/{$flag->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('property_flags', [
            'id' => $flag->id,
        ]);
    }

    public function test_duplicate_flag_is_rejected(): void
    {
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post("/properties/{$this->property->id}/flags", [
                'flag_type' => 'hoa',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('flag_type');
    }

    public function test_invalid_flag_type_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post("/properties/{$this->property->id}/flags", [
                'flag_type' => 'invalid_type',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('flag_type');
    }

    public function test_reason_too_long_is_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post("/properties/{$this->property->id}/flags", [
                'flag_type' => 'hoa',
                'reason' => str_repeat('a', 501),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('reason');
    }

    public function test_cannot_delete_flag_from_different_property(): void
    {
        $otherProperty = Property::create([
            'external_id' => 'test-prop-2',
            'name' => 'Other Property',
            'is_active' => true,
        ]);

        $flag = PropertyFlag::create([
            'property_id' => $otherProperty->id,
            'flag_type' => 'hoa',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete("/properties/{$this->property->id}/flags/{$flag->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('property_flags', [
            'id' => $flag->id,
        ]);
    }

    public function test_property_show_includes_flags(): void
    {
        PropertyFlag::create([
            'property_id' => $this->property->id,
            'flag_type' => 'hoa',
            'reason' => 'Test reason',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get("/properties/{$this->property->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Properties/Show')
            ->has('property.flags', 1)
            ->where('property.flags.0.flag_type', 'hoa')
            ->has('flagTypes')
        );
    }

    public function test_flag_without_reason_is_valid(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post("/properties/{$this->property->id}/flags", [
                'flag_type' => 'sold',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('property_flags', [
            'property_id' => $this->property->id,
            'flag_type' => 'sold',
            'reason' => null,
        ]);
    }
}
