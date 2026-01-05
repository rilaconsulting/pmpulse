<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleSsoTest extends TestCase
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

        // Enable Google SSO for tests
        Setting::set('google_sso', 'enabled', true);
        Setting::set('google_sso', 'client_id', 'test-client-id');
        Setting::set('google_sso', 'client_secret', 'test-client-secret', encrypted: true);
    }

    private function mockSocialiteUser(string $email, string $id, ?string $name = 'Test User'): SocialiteUser
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getName')->andReturn($name);

        return $socialiteUser;
    }

    public function test_google_redirect_redirects_to_google(): void
    {
        $response = $this->get(route('auth.google'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_google_redirect_fails_when_not_configured(): void
    {
        // Disable Google SSO
        Setting::set('google_sso', 'enabled', false);

        // Clear env config by setting to empty
        config(['services.google.client_id' => null]);
        config(['services.google.client_secret' => null]);

        $response = $this->get(route('auth.google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
    }

    public function test_google_callback_logs_in_sso_user(): void
    {
        $user = User::factory()->create([
            'email' => 'sso@example.com',
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-123',
            'is_active' => true,
        ]);

        $socialiteUser = $this->mockSocialiteUser('sso@example.com', 'google-123');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(Mockery::mock([
                'user' => $socialiteUser,
            ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_callback_rejects_password_user(): void
    {
        User::factory()->create([
            'email' => 'password@example.com',
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'is_active' => true,
        ]);

        $socialiteUser = $this->mockSocialiteUser('password@example.com', 'google-456');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(Mockery::mock([
                'user' => $socialiteUser,
            ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_callback_rejects_unregistered_user(): void
    {
        $socialiteUser = $this->mockSocialiteUser('unknown@example.com', 'google-789');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(Mockery::mock([
                'user' => $socialiteUser,
            ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_callback_rejects_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-inactive',
            'is_active' => false,
        ]);

        $socialiteUser = $this->mockSocialiteUser('inactive@example.com', 'google-inactive');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(Mockery::mock([
                'user' => $socialiteUser,
            ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_google_callback_links_google_id_for_first_login(): void
    {
        $user = User::factory()->create([
            'email' => 'newlink@example.com',
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => null,
            'is_active' => true,
        ]);

        $socialiteUser = $this->mockSocialiteUser('newlink@example.com', 'google-newlink');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(Mockery::mock([
                'user' => $socialiteUser,
            ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertEquals('google-newlink', $user->fresh()->google_id);
    }

    public function test_google_callback_rejects_mismatched_google_id(): void
    {
        User::factory()->create([
            'email' => 'mismatch@example.com',
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-original',
            'is_active' => true,
        ]);

        // Different google_id trying to login with same email
        $socialiteUser = $this->mockSocialiteUser('mismatch@example.com', 'google-different');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn(Mockery::mock([
                'user' => $socialiteUser,
            ]));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_service_prevents_linking_google_id_already_used_by_another_user(): void
    {
        // First user already has this google_id
        User::factory()->create([
            'email' => 'first@example.com',
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-shared',
            'is_active' => true,
        ]);

        // Second SSO user without google_id yet (admin created with email only)
        $secondUser = User::factory()->create([
            'email' => 'second@example.com',
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => null,
            'is_active' => true,
        ]);

        $service = app(GoogleSsoService::class);

        // Second user tries to login with Google, but their Google ID is already linked to first user
        // The service should find second user by email (since google_id is null) but reject linking
        $socialiteUser = $this->mockSocialiteUser('second@example.com', 'google-shared');

        $result = $service->resolveUser($socialiteUser);

        // The google_id is already in use by another user, so linking should fail
        $this->assertEquals(GoogleSsoService::RESULT_GOOGLE_ID_MISMATCH, $result['result']);
        // Ensure the second user's google_id was NOT updated
        $this->assertNull($secondUser->fresh()->google_id);
    }
}
