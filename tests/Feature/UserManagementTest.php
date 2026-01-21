<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $viewerUser;

    private Role $adminRole;

    private Role $viewerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->admin()->create();
        $this->viewerRole = Role::factory()->viewer()->create();

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
        $this->viewerUser = User::factory()->create([
            'role_id' => $this->viewerRole->id,
        ]);
    }

    // ==================== User List Page Tests ====================

    public function test_guest_cannot_view_users_page(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_users_page(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_view_users_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users')
            ->has('roles')
            ->has('filters')
        );
    }

    public function test_users_page_displays_all_users(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 2)
        );
    }

    public function test_users_page_can_filter_by_active_status(): void
    {
        User::factory()->inactive()->create(['role_id' => $this->viewerRole->id]);

        $response = $this->actingAs($this->adminUser)->get('/admin/users?active=true');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 2)
            ->where('filters.active', true)
        );
    }

    public function test_users_page_can_filter_by_auth_provider(): void
    {
        User::factory()->create([
            'role_id' => $this->viewerRole->id,
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-test-123',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/users?auth_provider=google');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 1)
            ->where('filters.auth_provider', 'google')
        );
    }

    public function test_users_page_can_filter_by_role(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/users?role_id='.$this->adminRole->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 1)
            ->where('filters.role_id', $this->adminRole->id)
        );
    }

    public function test_users_page_can_search_by_name(): void
    {
        User::factory()->create([
            'name' => 'Unique Test Name',
            'role_id' => $this->viewerRole->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/users?search=Unique');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 1)
            ->where('filters.search', 'Unique')
        );
    }

    public function test_users_page_can_search_by_email(): void
    {
        User::factory()->create([
            'email' => 'searchable@example.com',
            'role_id' => $this->viewerRole->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get('/admin/users?search=searchable@example');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 1)
        );
    }

    public function test_users_page_invalid_auth_provider_fails_validation(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/users?auth_provider=invalid');

        $response->assertSessionHasErrors('auth_provider');
    }

    // ==================== Create User Form Tests ====================

    public function test_guest_cannot_view_create_user_form(): void
    {
        $response = $this->get('/admin/users/create');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_create_user_form(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/users/create');

        $response->assertForbidden();
    }

    public function test_admin_can_view_create_user_form(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/UserCreate')
            ->has('roles')
        );
    }

    // ==================== Store User Tests ====================

    public function test_guest_cannot_create_user(): void
    {
        $response = $this->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $response = $this->actingAs($this->viewerUser)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_create_password_user(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'New Password User',
            'email' => 'newpassword@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
            'role_id' => $this->viewerRole->id,
        ]);

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success', 'User created successfully.');

        $this->assertDatabaseHas('users', [
            'name' => 'New Password User',
            'email' => 'newpassword@example.com',
            'auth_provider' => 'password',
            'role_id' => $this->viewerRole->id,
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_admin_can_create_google_sso_user(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'New SSO User',
            'email' => 'newsso@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google-new-123',
            'force_sso' => true,
            'role_id' => $this->viewerRole->id,
        ]);

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success', 'User created successfully.');

        $this->assertDatabaseHas('users', [
            'email' => 'newsso@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google-new-123',
            'force_sso' => true,
        ]);
    }

    public function test_create_user_requires_name(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'email' => 'test@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_create_user_requires_email(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test User',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_create_user_requires_unique_email(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Duplicate User',
            'email' => $this->viewerUser->email,
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_create_user_requires_auth_provider(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
        ]);

        $response->assertSessionHasErrors('auth_provider');
    }

    public function test_create_password_user_requires_password(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'auth_provider' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_create_password_user_requires_strong_password(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'weak',
            'auth_provider' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_create_sso_user_without_google_id_succeeds(): void
    {
        // Admin can create SSO users without specifying a google_id.
        // The google_id will be linked automatically when the user first logs in with Google.
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test SSO User',
            'email' => 'testsso@example.com',
            'auth_provider' => 'google',
            'role_id' => $this->adminUser->role_id,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'testsso@example.com',
            'auth_provider' => 'google',
            'google_id' => null,
        ]);
    }

    public function test_create_sso_user_cannot_have_password(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test SSO User',
            'email' => 'testsso@example.com',
            'auth_provider' => 'google',
            'google_id' => 'google-test',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_create_password_user_cannot_have_google_id(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'XyZ#9kL$mN2pQ7rS!',
            'auth_provider' => 'password',
            'google_id' => 'google-test',
        ]);

        $response->assertSessionHasErrors('google_id');
    }

    // ==================== Edit User Form Tests ====================

    public function test_guest_cannot_view_edit_user_form(): void
    {
        $response = $this->get("/admin/users/{$this->viewerUser->id}/edit");

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_view_edit_user_form(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->get("/admin/users/{$this->viewerUser->id}/edit");

        $response->assertForbidden();
    }

    public function test_admin_can_view_edit_user_form(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get("/admin/users/{$this->viewerUser->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/UserEdit')
            ->has('user')
            ->has('roles')
            ->has('canDeactivate')
            ->where('user.id', $this->viewerUser->id)
        );
    }

    public function test_edit_form_shows_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get("/admin/users/{$this->adminUser->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('canDeactivate', false)
        );
    }

    public function test_edit_form_shows_cannot_deactivate_last_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get("/admin/users/{$this->adminUser->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('canDeactivate', false)
        );
    }

    public function test_edit_form_shows_can_deactivate_other_user(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get("/admin/users/{$this->viewerUser->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->where('canDeactivate', true)
        );
    }

    // ==================== Update User Tests ====================

    public function test_guest_cannot_update_user(): void
    {
        $response = $this->patch("/admin/users/{$this->viewerUser->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_update_user(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->patch("/admin/users/{$this->viewerUser->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_user_name(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->viewerUser->id}", [
                'name' => 'Updated User Name',
            ]);

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success', 'User updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $this->viewerUser->id,
            'name' => 'Updated User Name',
        ]);
    }

    public function test_admin_can_update_user_email(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->viewerUser->id}", [
                'email' => 'newemail@example.com',
            ]);

        $response->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $this->viewerUser->id,
            'email' => 'newemail@example.com',
        ]);
    }

    public function test_admin_can_update_user_role(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->viewerUser->id}", [
                'role_id' => $this->adminRole->id,
            ]);

        $response->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $this->viewerUser->id,
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_update_user_validates_unique_email(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->viewerUser->id}", [
                'email' => $this->adminUser->email,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_update_own_email_to_same_value(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->adminUser->id}", [
                'email' => $this->adminUser->email,
            ]);

        $response->assertRedirect('/admin/users');
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_cannot_deactivate_self_via_update(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->adminUser->id}", [
                'is_active' => false,
            ]);

        $response->assertSessionHasErrors('is_active');
    }

    public function test_cannot_deactivate_last_admin_via_update(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->adminUser->id}", [
                'is_active' => false,
            ]);

        $response->assertSessionHasErrors('is_active');
    }

    public function test_cannot_remove_admin_role_from_last_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$this->adminUser->id}", [
                'role_id' => $this->viewerRole->id,
            ]);

        $response->assertSessionHasErrors('role_id');
    }

    public function test_can_deactivate_other_admin_when_multiple_exist(): void
    {
        $secondAdmin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$secondAdmin->id}", [
                'is_active' => false,
            ]);

        $response->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $secondAdmin->id,
            'is_active' => false,
        ]);
    }

    public function test_update_password_user_to_sso_requires_google_id(): void
    {
        $passwordUser = User::factory()->create([
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'password' => Hash::make('password'),
            'role_id' => $this->viewerRole->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$passwordUser->id}", [
                'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            ]);

        $response->assertSessionHasErrors('google_id');
    }

    public function test_update_sso_user_to_password_requires_password(): void
    {
        $ssoUser = User::factory()->create([
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-test-123',
            'role_id' => $this->viewerRole->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->patch("/admin/users/{$ssoUser->id}", [
                'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            ]);

        $response->assertSessionHasErrors('password');
    }

    // ==================== Deactivate User Tests ====================

    public function test_guest_cannot_deactivate_user(): void
    {
        $response = $this->delete("/admin/users/{$this->viewerUser->id}");

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_deactivate_user(): void
    {
        $response = $this->actingAs($this->viewerUser)
            ->delete("/admin/users/{$this->viewerUser->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_deactivate_user(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->delete("/admin/users/{$this->viewerUser->id}");

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success', 'User deactivated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $this->viewerUser->id,
            'is_active' => false,
        ]);
    }

    public function test_cannot_deactivate_self(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->delete("/admin/users/{$this->adminUser->id}");

        $response->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $this->adminUser->id,
            'is_active' => true,
        ]);
    }

    public function test_cannot_deactivate_last_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->delete("/admin/users/{$this->adminUser->id}");

        $response->assertSessionHasErrors('user');
    }

    public function test_can_deactivate_admin_when_multiple_exist(): void
    {
        $secondAdmin = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete("/admin/users/{$secondAdmin->id}");

        $response->assertRedirect('/admin/users');
        $response->assertSessionHas('success', 'User deactivated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $secondAdmin->id,
            'is_active' => false,
        ]);
    }

    // ==================== Roles in Forms ====================

    public function test_create_form_includes_all_roles(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('roles', 2)
        );
    }

    public function test_edit_form_includes_all_roles(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get("/admin/users/{$this->viewerUser->id}/edit");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('roles', 2)
        );
    }

    public function test_users_list_includes_user_roles(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('users.data.0.role')
        );
    }
}
