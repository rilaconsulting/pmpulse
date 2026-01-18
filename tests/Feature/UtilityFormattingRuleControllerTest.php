<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityFormattingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityFormattingRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['name' => 'viewer']);
        $adminRole = Role::factory()->admin()->create();

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);

        // Ensure at least one utility type exists
        UtilityAccount::factory()->create(['utility_type' => 'water']);
        UtilityAccount::factory()->create(['utility_type' => 'electric']);
    }

    // ==================== Index Tests ====================

    public function test_guest_cannot_access_formatting_rules_index(): void
    {
        $response = $this->get('/admin/utility-formatting-rules');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_formatting_rules_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/utility-formatting-rules');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_formatting_rules_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/utility-formatting-rules');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->component('Admin/UtilityFormattingRules', shouldExist: false)
                ->has('rules')
                ->has('rulesByType')
                ->has('utilityTypes')
                ->has('operators')
        );
    }

    public function test_index_returns_rules_grouped_by_type(): void
    {
        UtilityFormattingRule::factory()->create([
            'utility_type' => 'water',
            'name' => 'Water Rule 1',
            'created_by' => $this->adminUser->id,
        ]);

        UtilityFormattingRule::factory()->create([
            'utility_type' => 'electric',
            'name' => 'Electric Rule 1',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/utility-formatting-rules');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->has('rules', 2)
                ->has('rulesByType.water', 1)
                ->has('rulesByType.electric', 1)
        );
    }

    public function test_index_returns_operator_options(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/utility-formatting-rules');

        $response->assertStatus(200);
        $response->assertInertia(
            fn ($page) => $page
                ->where('operators', UtilityFormattingRule::OPERATORS)
        );
    }

    // ==================== Store Tests ====================

    public function test_guest_cannot_create_formatting_rule(): void
    {
        $response = $this->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_create_formatting_rule(): void
    {
        $response = $this->actingAs($this->user)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_formatting_rule(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'High Increase Alert',
            'operator' => 'increase_percent',
            'threshold' => 25.5,
            'color' => '#FF0000',
            'background_color' => '#FFEEEE',
            'priority' => 10,
            'enabled' => true,
        ]);

        $response->assertRedirect('/admin/utility-formatting-rules');
        $response->assertSessionHas('success', 'Formatting rule created successfully.');

        $this->assertDatabaseHas('utility_formatting_rules', [
            'utility_type' => 'water',
            'name' => 'High Increase Alert',
            'operator' => 'increase_percent',
            'threshold' => 25.5,
            'color' => '#FF0000',
            'background_color' => '#FFEEEE',
            'priority' => 10,
            'enabled' => true,
            'created_by' => $this->adminUser->id,
        ]);
    }

    public function test_store_requires_utility_type(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('utility_type');
    }

    public function test_store_validates_utility_type(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'invalid_type',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('utility_type');
    }

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_operator(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('operator');
    }

    public function test_store_validates_operator(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'invalid_operator',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('operator');
    }

    public function test_store_requires_threshold(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('threshold');
    }

    public function test_store_validates_threshold_range(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => -1,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('threshold');

        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 1001,
            'color' => '#FF0000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('threshold');
    }

    public function test_store_requires_color(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('color');
    }

    public function test_store_validates_color_format(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => 'red',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('color');

        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FFF', // 3-character hex
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('color');
    }

    public function test_store_validates_background_color_format(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
            'background_color' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('background_color');
    }

    public function test_store_allows_null_background_color(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertRedirect('/admin/utility-formatting-rules');

        $this->assertDatabaseHas('utility_formatting_rules', [
            'name' => 'Test Rule',
            'background_color' => null,
        ]);
    }

    public function test_store_uses_default_priority(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/admin/utility-formatting-rules', [
            'utility_type' => 'water',
            'name' => 'Test Rule',
            'operator' => 'increase_percent',
            'threshold' => 20,
            'color' => '#FF0000',
        ]);

        $response->assertRedirect('/admin/utility-formatting-rules');

        $this->assertDatabaseHas('utility_formatting_rules', [
            'name' => 'Test Rule',
            'priority' => 0,
        ]);
    }

    // ==================== Update Tests ====================

    public function test_guest_cannot_update_formatting_rule(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->patchJson("/admin/utility-formatting-rules/{$rule->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_update_formatting_rule(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/admin/utility-formatting-rules/{$rule->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_formatting_rule(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'utility_type' => 'water',
            'name' => 'Original Name',
            'threshold' => 10,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->patch("/admin/utility-formatting-rules/{$rule->id}", [
            'name' => 'Updated Name',
            'threshold' => 25,
            'enabled' => false,
        ]);

        $response->assertRedirect('/admin/utility-formatting-rules');
        $response->assertSessionHas('success', 'Formatting rule updated successfully.');

        $this->assertDatabaseHas('utility_formatting_rules', [
            'id' => $rule->id,
            'name' => 'Updated Name',
            'threshold' => 25,
            'enabled' => false,
        ]);
    }

    public function test_update_validates_fields(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->patchJson("/admin/utility-formatting-rules/{$rule->id}", [
            'threshold' => -5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('threshold');
    }

    public function test_update_returns_404_for_nonexistent_rule(): void
    {
        $response = $this->actingAs($this->adminUser)->patchJson('/admin/utility-formatting-rules/nonexistent-id', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
    }

    // ==================== Destroy Tests ====================

    public function test_guest_cannot_delete_formatting_rule(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->deleteJson("/admin/utility-formatting-rules/{$rule->id}");

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_delete_formatting_rule(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/admin/utility-formatting-rules/{$rule->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_formatting_rule(): void
    {
        $rule = UtilityFormattingRule::factory()->create([
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/admin/utility-formatting-rules/{$rule->id}");

        $response->assertRedirect('/admin/utility-formatting-rules');
        $response->assertSessionHas('success', 'Formatting rule deleted successfully.');

        $this->assertDatabaseMissing('utility_formatting_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_rule(): void
    {
        $response = $this->actingAs($this->adminUser)->deleteJson('/admin/utility-formatting-rules/nonexistent-id');

        $response->assertStatus(404);
    }

    // ==================== Authorization Tests ====================

    public function test_viewer_cannot_access_formatting_rules(): void
    {
        // $this->user is already a viewer from setUp()
        $response = $this->actingAs($this->user)->get('/admin/utility-formatting-rules');

        $response->assertStatus(403);
    }
}
