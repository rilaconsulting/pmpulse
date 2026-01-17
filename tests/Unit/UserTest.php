<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    // ==================== scopeFilter Tests ====================

    public function test_scope_filter_by_active_status_true(): void
    {
        $role = Role::factory()->create();
        $activeUser = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $inactiveUser = User::factory()->inactive()->create(['role_id' => $role->id]);

        $results = User::filter(['active' => true])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($activeUser->id, $results->first()->id);
    }

    public function test_scope_filter_by_active_status_false(): void
    {
        $role = Role::factory()->create();
        User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $inactiveUser = User::factory()->inactive()->create(['role_id' => $role->id]);

        $results = User::filter(['active' => false])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($inactiveUser->id, $results->first()->id);
    }

    public function test_scope_filter_ignores_empty_active_filter(): void
    {
        $role = Role::factory()->create();
        User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        User::factory()->inactive()->create(['role_id' => $role->id]);

        $results = User::filter(['active' => ''])->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_filter_by_auth_provider(): void
    {
        $role = Role::factory()->create();
        $passwordUser = User::factory()->create([
            'role_id' => $role->id,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
        ]);
        User::factory()->create([
            'role_id' => $role->id,
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-123',
        ]);

        $results = User::filter(['auth_provider' => 'password'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($passwordUser->id, $results->first()->id);
    }

    public function test_scope_filter_by_role_id(): void
    {
        $adminRole = Role::factory()->admin()->create();
        $viewerRole = Role::factory()->viewer()->create();
        $adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        User::factory()->create(['role_id' => $viewerRole->id]);

        $results = User::filter(['role_id' => $adminRole->id])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($adminUser->id, $results->first()->id);
    }

    public function test_scope_filter_by_search_matches_name(): void
    {
        $role = Role::factory()->create();
        $targetUser = User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Unique Name Here',
        ]);
        User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Other Person',
        ]);

        $results = User::filter(['search' => 'Unique'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($targetUser->id, $results->first()->id);
    }

    public function test_scope_filter_by_search_matches_email(): void
    {
        $role = Role::factory()->create();
        $targetUser = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'searchable@example.com',
        ]);
        User::factory()->create([
            'role_id' => $role->id,
            'email' => 'other@example.com',
        ]);

        $results = User::filter(['search' => 'searchable@'])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($targetUser->id, $results->first()->id);
    }

    public function test_scope_filter_combines_multiple_filters(): void
    {
        $adminRole = Role::factory()->admin()->create();
        $viewerRole = Role::factory()->viewer()->create();

        // Active admin with password auth
        $targetUser = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
            'name' => 'Target Admin',
        ]);

        // Active admin with Google auth
        User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-123',
        ]);

        // Inactive admin with password auth
        User::factory()->inactive()->create([
            'role_id' => $adminRole->id,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
        ]);

        // Active viewer with password auth
        User::factory()->create([
            'role_id' => $viewerRole->id,
            'is_active' => true,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
        ]);

        $results = User::filter([
            'active' => true,
            'auth_provider' => 'password',
            'role_id' => $adminRole->id,
            'search' => 'Target',
        ])->get();

        $this->assertCount(1, $results);
        $this->assertEquals($targetUser->id, $results->first()->id);
    }

    public function test_scope_filter_with_no_filters_returns_all(): void
    {
        $role = Role::factory()->create();
        User::factory()->count(3)->create(['role_id' => $role->id]);

        $results = User::filter([])->get();

        $this->assertCount(3, $results);
    }

    // ==================== Existing Scope Tests ====================

    public function test_scope_active_filters_active_users(): void
    {
        $role = Role::factory()->create();
        $activeUser = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        User::factory()->inactive()->create(['role_id' => $role->id]);

        $results = User::active()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($activeUser->id, $results->first()->id);
    }

    public function test_scope_with_auth_provider_filters_by_provider(): void
    {
        $role = Role::factory()->create();
        $passwordUser = User::factory()->create([
            'role_id' => $role->id,
            'auth_provider' => User::AUTH_PROVIDER_PASSWORD,
        ]);
        User::factory()->create([
            'role_id' => $role->id,
            'auth_provider' => User::AUTH_PROVIDER_GOOGLE,
            'google_id' => 'google-123',
        ]);

        $results = User::withAuthProvider(User::AUTH_PROVIDER_PASSWORD)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($passwordUser->id, $results->first()->id);
    }
}
