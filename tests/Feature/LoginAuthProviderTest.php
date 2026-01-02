<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginAuthProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(
            ['name' => Role::ADMIN],
            ['description' => 'Administrator', 'permissions' => ['*']]
        );
        Role::firstOrCreate(
            ['name' => Role::VIEWER],
            ['description' => 'Viewer', 'permissions' => ['view']]
        );
    }

    public function test_password_user_can_login_with_password(): void
    {
        $user = User::factory()->create([
            'email' => 'password@example.com',
            'password' => Hash::make('password123'),
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'password@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_sso_user_cannot_login_with_password(): void
    {
        User::factory()->create([
            'email' => 'sso@example.com',
            'password' => Hash::make('password123'),
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'sso@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Google SSO', session('errors')->get('email')[0]);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'is_active' => false,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_returns_generic_error(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('correctpassword'),
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_nonexistent_user_returns_generic_error(): void
    {
        $response = $this->post(route('login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'anypassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
