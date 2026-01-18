<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Role;
use App\Models\User;
use App\Models\UtilityAccount;
use App\Models\UtilityNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $adminUser;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['name' => 'viewer']);
        $adminRole = Role::factory()->admin()->create();

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        $this->property = Property::factory()->create();

        // Ensure at least one utility type exists
        UtilityAccount::factory()->create(['utility_type' => 'water']);
    }

    // ==================== Show Tests ====================

    public function test_guest_cannot_view_note(): void
    {
        $response = $this->getJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_note(): void
    {
        $note = UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'note' => 'Test note content',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(200);
        $response->assertJsonPath('note.id', $note->id);
        $response->assertJsonPath('note.note', 'Test note content');
        $response->assertJsonPath('note.utility_type', 'water');
    }

    public function test_show_returns_null_for_nonexistent_note(): void
    {
        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(200);
        $response->assertJsonPath('note', null);
    }

    public function test_show_returns_creator_name(): void
    {
        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(200);
        $response->assertJsonPath('note.created_by', $this->user->name);
    }

    public function test_show_validates_utility_type(): void
    {
        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$this->property->id}/invalid_type");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Invalid utility type.');
    }

    public function test_show_returns_404_for_nonexistent_property(): void
    {
        $response = $this->actingAs($this->user)->getJson('/utilities/notes/nonexistent-id/water');

        $response->assertStatus(404);
    }

    // ==================== Store Tests ====================

    public function test_guest_cannot_create_note(): void
    {
        $response = $this->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'water',
            'note' => 'Test note',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_note(): void
    {
        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'water',
            'note' => 'New note content',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('note.note', 'New note content');
        $response->assertJsonPath('note.utility_type', 'water');
        $response->assertJsonPath('message', 'Note saved successfully.');

        $this->assertDatabaseHas('utility_notes', [
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'note' => 'New note content',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_updates_existing_note(): void
    {
        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'note' => 'Original note',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'water',
            'note' => 'Updated note content',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('note.note', 'Updated note content');

        // Verify only one note exists
        $this->assertDatabaseCount('utility_notes', 1);
        $this->assertDatabaseHas('utility_notes', [
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'note' => 'Updated note content',
        ]);
    }

    public function test_store_requires_utility_type(): void
    {
        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'note' => 'Test note',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('utility_type');
    }

    public function test_store_requires_note(): void
    {
        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'water',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('note');
    }

    public function test_store_validates_utility_type(): void
    {
        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'invalid_type',
            'note' => 'Test note',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('utility_type');
    }

    public function test_store_validates_note_max_length(): void
    {
        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'water',
            'note' => str_repeat('a', 2001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('note');
    }

    public function test_store_allows_note_at_max_length(): void
    {
        $maxLengthNote = str_repeat('a', 2000);

        $response = $this->actingAs($this->user)->postJson("/utilities/notes/{$this->property->id}", [
            'utility_type' => 'water',
            'note' => $maxLengthNote,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('utility_notes', [
            'property_id' => $this->property->id,
            'note' => $maxLengthNote,
        ]);
    }

    public function test_store_returns_404_for_nonexistent_property(): void
    {
        $response = $this->actingAs($this->user)->postJson('/utilities/notes/nonexistent-id', [
            'utility_type' => 'water',
            'note' => 'Test note',
        ]);

        $response->assertStatus(404);
    }

    // ==================== Destroy Tests ====================

    public function test_guest_cannot_delete_note(): void
    {
        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
        ]);

        $response = $this->deleteJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_delete_note(): void
    {
        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Note deleted successfully.');

        $this->assertDatabaseMissing('utility_notes', [
            'property_id' => $this->property->id,
            'utility_type' => 'water',
        ]);
    }

    public function test_any_user_can_delete_any_note(): void
    {
        // Note created by admin
        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'created_by' => $this->adminUser->id,
        ]);

        // Regular user can delete it
        $response = $this->actingAs($this->user)->deleteJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('utility_notes', [
            'property_id' => $this->property->id,
            'utility_type' => 'water',
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_note(): void
    {
        $response = $this->actingAs($this->user)->deleteJson("/utilities/notes/{$this->property->id}/water");

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Note not found.');
    }

    public function test_destroy_validates_utility_type(): void
    {
        $response = $this->actingAs($this->user)->deleteJson("/utilities/notes/{$this->property->id}/invalid_type");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Invalid utility type.');
    }

    public function test_destroy_returns_404_for_nonexistent_property(): void
    {
        $response = $this->actingAs($this->user)->deleteJson('/utilities/notes/nonexistent-id/water');

        $response->assertStatus(404);
    }

    // ==================== Edge Cases ====================

    public function test_notes_are_property_and_utility_type_specific(): void
    {
        $property2 = Property::factory()->create();
        UtilityAccount::factory()->create(['utility_type' => 'electric']);

        // Create notes for different combinations
        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'water',
            'note' => 'Property 1 Water',
            'created_by' => $this->user->id,
        ]);

        UtilityNote::factory()->create([
            'property_id' => $this->property->id,
            'utility_type' => 'electric',
            'note' => 'Property 1 Electric',
            'created_by' => $this->user->id,
        ]);

        UtilityNote::factory()->create([
            'property_id' => $property2->id,
            'utility_type' => 'water',
            'note' => 'Property 2 Water',
            'created_by' => $this->user->id,
        ]);

        // Verify each combination returns correct note
        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$this->property->id}/water");
        $response->assertJsonPath('note.note', 'Property 1 Water');

        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$this->property->id}/electric");
        $response->assertJsonPath('note.note', 'Property 1 Electric');

        $response = $this->actingAs($this->user)->getJson("/utilities/notes/{$property2->id}/water");
        $response->assertJsonPath('note.note', 'Property 2 Water');
    }
}
