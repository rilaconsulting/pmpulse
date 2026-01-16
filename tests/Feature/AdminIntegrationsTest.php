<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $viewerRole = Role::factory()->viewer()->create();

        $this->adminUser = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
        $this->viewerUser = User::factory()->create([
            'role_id' => $viewerRole->id,
        ]);
    }

    // ==================== Integrations Page Access Tests ====================

    public function test_guest_cannot_view_integrations_page(): void
    {
        $response = $this->get('/admin/integrations');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_integrations_page(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/integrations');

        $response->assertForbidden();
    }

    public function test_admin_can_view_integrations_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/integrations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Integrations')
            ->has('appfolio')
            ->has('googleMaps')
            ->has('googleSso')
        );
    }

    public function test_integrations_page_shows_unconfigured_appfolio(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/integrations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('appfolio', null)
        );
    }

    public function test_integrations_page_shows_configured_appfolio(): void
    {
        Setting::set('appfolio', 'client_id', 'test-client-id');
        Setting::set('appfolio', 'database', 'test-database');
        Setting::set('appfolio', 'client_secret', 'test-secret', encrypted: true);

        $response = $this->actingAs($this->adminUser)->get('/admin/integrations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('appfolio.client_id', 'test-client-id')
            ->where('appfolio.database', 'test-database')
            ->where('appfolio.has_secret', true)
        );
    }

    public function test_integrations_page_shows_google_sso_status(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/integrations');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('googleSso.enabled')
            ->has('googleSso.client_id')
            ->has('googleSso.has_secret')
            ->has('googleSso.configured')
            ->has('googleSso.redirect_uri')
        );
    }

    // ==================== Google SSO Settings Tests ====================

    public function test_guest_cannot_save_google_sso_settings(): void
    {
        $response = $this->post('/admin/integrations/google-sso', [
            'google_enabled' => true,
            'google_client_id' => 'test-client-id',
            'google_client_secret' => 'test-secret',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_save_google_sso_settings(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => 'test-client-id',
                'google_client_secret' => 'test-secret',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_enable_google_sso(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => 'new-client-id.apps.googleusercontent.com',
                'google_client_secret' => 'new-client-secret',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Google SSO settings saved successfully.');

        $this->assertEquals(true, Setting::get('google_sso', 'enabled'));
        $this->assertEquals('new-client-id.apps.googleusercontent.com', Setting::get('google_sso', 'client_id'));
        $this->assertNotEmpty(Setting::get('google_sso', 'client_secret'));
    }

    public function test_admin_can_disable_google_sso(): void
    {
        // First enable SSO
        Setting::set('google_sso', 'enabled', true);
        Setting::set('google_sso', 'client_id', 'existing-client-id');
        Setting::set('google_sso', 'client_secret', 'existing-secret', encrypted: true);

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Google SSO settings saved successfully.');

        $this->assertEquals(false, Setting::get('google_sso', 'enabled'));
    }

    public function test_enabling_sso_requires_client_id(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_secret' => 'test-secret',
            ]);

        $response->assertSessionHasErrors('google_client_id');
    }

    public function test_enabling_sso_first_time_requires_client_secret(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => 'test-client-id',
            ]);

        $response->assertSessionHasErrors('google_client_secret');
    }

    public function test_enabling_sso_with_existing_secret_does_not_require_new_secret(): void
    {
        // Set up existing secret
        Setting::set('google_sso', 'client_secret', 'existing-secret', encrypted: true);

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => 'updated-client-id',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $this->assertEquals('updated-client-id', Setting::get('google_sso', 'client_id'));
    }

    public function test_can_update_client_secret_when_already_configured(): void
    {
        // Set up existing configuration
        Setting::set('google_sso', 'enabled', true);
        Setting::set('google_sso', 'client_id', 'existing-client-id');
        Setting::set('google_sso', 'client_secret', 'existing-secret', encrypted: true);

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => 'existing-client-id',
                'google_client_secret' => 'new-secret',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify the secret was updated
        $this->assertNotEmpty(Setting::get('google_sso', 'client_secret'));
    }

    public function test_google_enabled_must_be_boolean(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => 'invalid',
            ]);

        $response->assertSessionHasErrors('google_enabled');
    }

    public function test_client_id_max_length_validation(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => str_repeat('a', 256),
                'google_client_secret' => 'test-secret',
            ]);

        $response->assertSessionHasErrors('google_client_id');
    }

    public function test_client_secret_max_length_validation(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-sso', [
                'google_enabled' => true,
                'google_client_id' => 'test-client-id',
                'google_client_secret' => str_repeat('a', 256),
            ]);

        $response->assertSessionHasErrors('google_client_secret');
    }

    // ==================== AppFolio Connection Tests ====================

    public function test_guest_cannot_save_appfolio_connection(): void
    {
        $response = $this->post('/admin/integrations/connection', [
            'client_id' => 'test-client',
            'database' => 'test-db',
            'client_secret' => 'test-secret',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_save_appfolio_connection(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->post('/admin/integrations/connection', [
                'client_id' => 'test-client',
                'database' => 'test-db',
                'client_secret' => 'test-secret',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_save_appfolio_connection(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/connection', [
                'client_id' => 'new-appfolio-client',
                'database' => 'my-database',
                'client_secret' => 'my-secret',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Connection settings saved successfully.');

        $this->assertEquals('new-appfolio-client', Setting::get('appfolio', 'client_id'));
        $this->assertEquals('my-database', Setting::get('appfolio', 'database'));
        $this->assertEquals('configured', Setting::get('appfolio', 'status'));
    }

    public function test_can_update_appfolio_connection_without_secret(): void
    {
        // Set up existing configuration
        Setting::set('appfolio', 'client_id', 'existing-client');
        Setting::set('appfolio', 'database', 'existing-db');
        Setting::set('appfolio', 'client_secret', 'existing-secret', encrypted: true);

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/connection', [
                'client_id' => 'updated-client',
                'database' => 'updated-db',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('updated-client', Setting::get('appfolio', 'client_id'));
        $this->assertEquals('updated-db', Setting::get('appfolio', 'database'));
        // Secret should remain unchanged
        $this->assertNotEmpty(Setting::get('appfolio', 'client_secret'));
    }

    // ==================== Google Maps Settings Tests ====================

    public function test_guest_cannot_save_google_maps_settings(): void
    {
        $response = $this->post('/admin/integrations/google-maps', [
            'maps_api_key' => 'test-api-key',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_save_google_maps_settings(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->post('/admin/integrations/google-maps', [
                'maps_api_key' => 'test-api-key',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_save_google_maps_api_key(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-maps', [
                'maps_api_key' => 'AIzaSyTestApiKey123',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Google Maps settings saved successfully.');

        $this->assertNotEmpty(Setting::get('google', 'maps_api_key'));
    }

    public function test_admin_can_remove_google_maps_api_key(): void
    {
        // Set up existing key
        Setting::set('google', 'maps_api_key', 'existing-key', encrypted: true);

        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-maps', [
                'maps_api_key' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertNull(Setting::get('google', 'maps_api_key'));
    }

    public function test_google_maps_api_key_max_length_validation(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post('/admin/integrations/google-maps', [
                'maps_api_key' => str_repeat('a', 101),
            ]);

        $response->assertSessionHasErrors('maps_api_key');
    }

    // ==================== Settings Page Tests ====================

    public function test_guest_cannot_view_settings_page(): void
    {
        $response = $this->get('/admin/settings');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_settings_page(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/settings');

        $response->assertForbidden();
    }

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings')
            ->has('features')
            ->has('features.incremental_sync')
            ->has('features.notifications')
        );
    }

    public function test_settings_page_shows_default_feature_flags(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('features.incremental_sync', true)
            ->where('features.notifications', true)
        );
    }

    public function test_settings_page_shows_custom_feature_flags(): void
    {
        Setting::set('features', 'incremental_sync', false);
        Setting::set('features', 'notifications', false);

        $response = $this->actingAs($this->adminUser)->get('/admin/settings');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('features.incremental_sync', false)
            ->where('features.notifications', false)
        );
    }
}
