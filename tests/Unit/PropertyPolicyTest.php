<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Policies\PropertyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PropertyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PropertyPolicy;
    }

    public function test_any_user_can_view_any_properties(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_any_user_can_view_a_property(): void
    {
        $user = User::factory()->create();
        $property = Property::factory()->create();

        $this->assertTrue($this->policy->view($user, $property));
    }

    public function test_admin_can_create_properties(): void
    {
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->assertTrue($this->policy->create($admin));
    }

    public function test_non_admin_cannot_create_properties(): void
    {
        $userRole = Role::factory()->create(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $userRole->id]);

        $this->assertFalse($this->policy->create($user));
    }

    public function test_admin_can_update_properties(): void
    {
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $property = Property::factory()->create();

        $this->assertTrue($this->policy->update($admin, $property));
    }

    public function test_non_admin_cannot_update_properties(): void
    {
        $userRole = Role::factory()->create(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $userRole->id]);
        $property = Property::factory()->create();

        $this->assertFalse($this->policy->update($user, $property));
    }

    public function test_admin_can_delete_properties(): void
    {
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $property = Property::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $property));
    }

    public function test_non_admin_cannot_delete_properties(): void
    {
        $userRole = Role::factory()->create(['name' => 'user']);
        $user = User::factory()->create(['role_id' => $userRole->id]);
        $property = Property::factory()->create();

        $this->assertFalse($this->policy->delete($user, $property));
    }
}
