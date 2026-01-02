<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $viewerUser;

    protected Role $adminRole;

    protected Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->adminRole = Role::factory()->admin()->create();
        $this->viewerRole = Role::factory()->viewer()->create();

        // Create users
        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->viewerUser = User::factory()->create([
            'role_id' => $this->viewerRole->id,
        ]);
    }

    public function test_list_users_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_list_users_requires_admin_role(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'auth_provider', 'is_active', 'role'],
                ],
            ]);
    }

    public function test_list_users_can_filter_by_active_status(): void
    {
        User::factory()->inactive()->create();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/users?active=true');

        $response->assertStatus(200);
        $this->assertTrue(
            collect($response->json('data'))->every(fn ($user) => $user['is_active'] === true)
        );
    }

    public function test_list_users_can_search_by_name(): void
    {
        User::factory()->create(['name' => 'Unique Test Name']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/users?search=Unique');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_admin_can_view_single_user(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/users/{$this->viewerUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->viewerUser->id)
            ->assertJsonPath('data.email', $this->viewerUser->email);
    }

    public function test_admin_can_create_password_user(): void
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
            'role_id' => $this->viewerRole->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', $userData);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'newuser@example.com')
            ->assertJsonPath('data.auth_provider', 'password');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_create_google_sso_user(): void
    {
        $userData = [
            'name' => 'SSO User',
            'email' => 'ssouser@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google-123456',
            'force_sso' => true,
            'role_id' => $this->viewerRole->id,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', $userData);

        $response->assertStatus(201)
            ->assertJsonPath('data.auth_provider', 'google')
            ->assertJsonPath('data.force_sso', true);
    }

    public function test_create_user_validates_unique_email(): void
    {
        $userData = [
            'name' => 'Duplicate',
            'email' => $this->viewerUser->email,
            'password' => 'SecurePassword123!',
            'auth_provider' => 'password',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_update_user(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->viewerUser->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $this->viewerUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_user_validates_unique_email(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->viewerUser->id}", [
                'email' => $this->adminUser->email,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_deactivate_user(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/users/{$this->viewerUser->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->viewerUser->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/users/{$this->adminUser->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot deactivate your own account');
    }

    public function test_cannot_deactivate_last_admin(): void
    {
        // Only one admin exists (adminUser)
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->adminUser->id}", [
                'is_active' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_cannot_remove_admin_role_from_last_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->adminUser->id}", [
                'role_id' => $this->viewerRole->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_can_get_roles_list(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/users/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ]);
    }
}
