<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Role $adminRole;

    protected Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->admin()->create();
        $this->viewerRole = Role::factory()->viewer()->create();

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_create_user_requires_name(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'email' => 'test@example.com',
                'password' => 'XyZ#9kL$mN2pQ7rS!',
                'auth_provider' => 'password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_user_requires_valid_email(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'invalid-email',
                'password' => 'XyZ#9kL$mN2pQ7rS!',
                'auth_provider' => 'password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_user_requires_password(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'auth_provider' => 'password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_google_user_requires_google_id(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'auth_provider' => 'google',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['google_id']);
    }

    public function test_sso_user_cannot_have_password(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'auth_provider' => 'google',
                'google_id' => 'google-123',
                'password' => 'XyZ#9kL$mN2pQ7rS!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_user_cannot_have_google_id(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'auth_provider' => 'password',
                'password' => 'XyZ#9kL$mN2pQ7rS!',
                'google_id' => 'google-123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['google_id']);
    }

    public function test_update_validates_last_admin_deactivation(): void
    {
        // Only adminUser is an admin
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->adminUser->id}", [
                'is_active' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);
    }

    public function test_update_validates_last_admin_role_removal(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$this->adminUser->id}", [
                'role_id' => $this->viewerRole->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_switching_to_sso_requires_google_id(): void
    {
        $passwordUser = User::factory()->create([
            'auth_provider' => 'password',
            'google_id' => null,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/users/{$passwordUser->id}", [
                'auth_provider' => 'google',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['google_id']);
    }

    public function test_invalid_auth_provider_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'auth_provider' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['auth_provider']);
    }

    public function test_invalid_role_id_rejected(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'SecurePass123!',
                'auth_provider' => 'password',
                'role_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_duplicate_email_rejected(): void
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'existing@example.com',
                'password' => 'XyZ#9kL$mN2pQ7rS!',
                'auth_provider' => 'password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_duplicate_google_id_rejected(): void
    {
        $existingUser = User::factory()->googleSso()->create(['google_id' => 'existing-google-id']);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/users', [
                'name' => 'Test User',
                'email' => 'new@example.com',
                'auth_provider' => 'google',
                'google_id' => 'existing-google-id',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['google_id']);
    }
}
