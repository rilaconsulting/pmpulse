<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $passwordUser;

    private User $ssoUser;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'user']);

        $this->passwordUser = User::factory()->create([
            'role_id' => $role->id,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'password' => Hash::make('current-password'),
        ]);

        $this->ssoUser = User::factory()->create([
            'role_id' => $role->id,
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-123',
        ]);
    }

    // ==================== Profile View Tests ====================

    public function test_guest_cannot_view_profile(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_user_can_view_their_profile(): void
    {
        $response = $this->actingAs($this->passwordUser)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->has('user')
            ->where('user.id', $this->passwordUser->id)
            ->where('user.name', $this->passwordUser->name)
            ->where('user.email', $this->passwordUser->email)
        );
    }

    public function test_profile_page_loads_user_role(): void
    {
        $response = $this->actingAs($this->passwordUser)->get('/profile');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Show')
            ->has('user.role')
        );
    }

    // ==================== Profile Update Tests ====================

    public function test_guest_cannot_update_profile(): void
    {
        $response = $this->patch('/profile', ['name' => 'New Name']);

        $response->assertRedirect('/login');
    }

    public function test_user_can_update_their_name(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->patch('/profile', ['name' => 'Updated Name']);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $this->passwordUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_profile_update_requires_name(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->patch('/profile', ['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_profile_update_requires_name_to_be_string(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->patch('/profile', ['name' => 12345]);

        $response->assertSessionHasErrors('name');
    }

    public function test_profile_update_requires_name_max_255_characters(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->patch('/profile', ['name' => str_repeat('a', 256)]);

        $response->assertSessionHasErrors('name');
    }

    public function test_sso_user_can_update_their_name(): void
    {
        $response = $this->actingAs($this->ssoUser)
            ->patch('/profile', ['name' => 'Updated SSO Name']);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated successfully.');

        $this->assertDatabaseHas('users', [
            'id' => $this->ssoUser->id,
            'name' => 'Updated SSO Name',
        ]);
    }

    // ==================== Password Update Tests ====================

    public function test_guest_cannot_update_password(): void
    {
        $response = $this->put('/profile/password', [
            'current_password' => 'current-password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_password_user_can_change_password_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'current-password',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Password updated successfully.');

        // Verify password was changed
        $this->passwordUser->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $this->passwordUser->password));
    }

    public function test_password_user_cannot_change_password_with_wrong_current_password(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertSessionHasErrors('current_password');

        // Verify password was not changed
        $this->passwordUser->refresh();
        $this->assertTrue(Hash::check('current-password', $this->passwordUser->password));
    }

    public function test_sso_user_cannot_access_password_change_endpoint(): void
    {
        $response = $this->actingAs($this->ssoUser)
            ->put('/profile/password', [
                'current_password' => 'any-password',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertForbidden();
    }

    public function test_password_change_requires_current_password(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_password_change_requires_password_confirmation(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'current-password',
                'password' => 'NewPassword1',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_requires_password_match(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'current-password',
                'password' => 'NewPassword1',
                'password_confirmation' => 'DifferentPassword1',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_requires_minimum_length(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'current-password',
                'password' => 'Short1',
                'password_confirmation' => 'Short1',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_requires_mixed_case(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'current-password',
                'password' => 'alllowercase1',
                'password_confirmation' => 'alllowercase1',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_change_requires_number(): void
    {
        $response = $this->actingAs($this->passwordUser)
            ->put('/profile/password', [
                'current_password' => 'current-password',
                'password' => 'NoNumbersHere',
                'password_confirmation' => 'NoNumbersHere',
            ]);

        $response->assertSessionHasErrors('password');
    }
}
