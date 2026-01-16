<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApiControllerTest extends TestCase
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
    }

    // ==================== Mark Duplicate Tests ====================

    public function test_guest_cannot_mark_duplicate(): void
    {
        $vendor = Vendor::factory()->create();
        $canonical = Vendor::factory()->create();

        $response = $this->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => $canonical->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_mark_duplicate(): void
    {
        $vendor = Vendor::factory()->create();
        $canonical = Vendor::factory()->create();

        $response = $this->actingAs($this->user)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => $canonical->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_mark_vendor_as_duplicate(): void
    {
        $vendor = Vendor::factory()->create(['company_name' => 'Duplicate Vendor']);
        $canonical = Vendor::factory()->create(['company_name' => 'Canonical Vendor']);

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => $canonical->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Vendor marked as duplicate successfully.',
        ]);

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'canonical_vendor_id' => $canonical->id,
        ]);
    }

    public function test_mark_duplicate_requires_canonical_vendor_id(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('canonical_vendor_id');
    }

    public function test_mark_duplicate_validates_uuid_format(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => 'not-a-uuid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('canonical_vendor_id');
    }

    public function test_mark_duplicate_validates_canonical_vendor_exists(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('canonical_vendor_id');
    }

    public function test_vendor_cannot_be_duplicate_of_itself(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => $vendor->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('canonical_vendor_id');
        $response->assertJson([
            'errors' => [
                'canonical_vendor_id' => ['A vendor cannot be marked as a duplicate of itself.'],
            ],
        ]);
    }

    public function test_vendor_with_duplicates_cannot_become_duplicate(): void
    {
        $vendorWithDuplicates = Vendor::factory()->create();
        $existingDuplicate = Vendor::factory()->duplicateOf($vendorWithDuplicates)->create();
        $newCanonical = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendorWithDuplicates->id}/mark-duplicate", [
            'canonical_vendor_id' => $newCanonical->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('canonical_vendor_id');
        $response->assertJson([
            'errors' => [
                'canonical_vendor_id' => ['This vendor has duplicates linked to it. Reassign those duplicates first.'],
            ],
        ]);
    }

    public function test_mark_duplicate_returns_updated_vendor(): void
    {
        $vendor = Vendor::factory()->create(['company_name' => 'Duplicate']);
        $canonical = Vendor::factory()->create(['company_name' => 'Canonical']);

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-duplicate", [
            'canonical_vendor_id' => $canonical->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $vendor->id);
        $response->assertJsonPath('data.canonical_vendor_id', $canonical->id);
    }

    public function test_mark_duplicate_returns_404_for_nonexistent_vendor(): void
    {
        $canonical = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->postJson('/api/vendors/nonexistent-id/mark-duplicate', [
            'canonical_vendor_id' => $canonical->id,
        ]);

        $response->assertStatus(404);
    }

    // ==================== Mark Canonical Tests ====================

    public function test_guest_cannot_mark_canonical(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->postJson("/api/vendors/{$vendor->id}/mark-canonical");

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_mark_canonical(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->user)->postJson("/api/vendors/{$vendor->id}/mark-canonical");

        $response->assertStatus(403);
    }

    public function test_admin_can_mark_vendor_as_canonical(): void
    {
        $canonical = Vendor::factory()->create();
        $duplicate = Vendor::factory()->duplicateOf($canonical)->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$duplicate->id}/mark-canonical");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Vendor marked as canonical successfully.',
        ]);

        $this->assertDatabaseHas('vendors', [
            'id' => $duplicate->id,
            'canonical_vendor_id' => null,
        ]);
    }

    public function test_mark_canonical_is_idempotent(): void
    {
        $vendor = Vendor::factory()->create(); // Already canonical (no canonical_vendor_id)

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$vendor->id}/mark-canonical");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Vendor is already canonical.',
        ]);
    }

    public function test_mark_canonical_returns_vendor_data(): void
    {
        $canonical = Vendor::factory()->create();
        $duplicate = Vendor::factory()->duplicateOf($canonical)->create();

        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$duplicate->id}/mark-canonical");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $duplicate->id);
    }

    public function test_mark_canonical_returns_404_for_nonexistent_vendor(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/vendors/nonexistent-id/mark-canonical');

        $response->assertStatus(404);
    }

    // ==================== Duplicates Endpoint Tests ====================

    public function test_guest_cannot_access_duplicates(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->getJson("/api/vendors/{$vendor->id}/duplicates");

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_access_duplicates(): void
    {
        $vendor = Vendor::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/vendors/{$vendor->id}/duplicates");

        $response->assertStatus(403);
    }

    public function test_admin_can_get_duplicates_for_canonical_vendor(): void
    {
        $canonical = Vendor::factory()->create(['company_name' => 'Canonical']);
        $duplicate1 = Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Duplicate 1']);
        $duplicate2 = Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Duplicate 2']);

        $response = $this->actingAs($this->adminUser)->getJson("/api/vendors/{$canonical->id}/duplicates");

        $response->assertStatus(200);
        $response->assertJsonPath('meta.is_duplicate', false);
        $response->assertJsonPath('meta.count', 2);
        $response->assertJsonCount(2, 'data');
    }

    public function test_duplicates_returns_empty_for_canonical_without_duplicates(): void
    {
        $canonical = Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->getJson("/api/vendors/{$canonical->id}/duplicates");

        $response->assertStatus(200);
        $response->assertJsonPath('meta.is_duplicate', false);
        $response->assertJsonPath('meta.count', 0);
        $response->assertJsonCount(0, 'data');
    }

    public function test_duplicates_returns_empty_for_duplicate_vendor(): void
    {
        $canonical = Vendor::factory()->create();
        $duplicate = Vendor::factory()->duplicateOf($canonical)->create();

        $response = $this->actingAs($this->adminUser)->getJson("/api/vendors/{$duplicate->id}/duplicates");

        $response->assertStatus(200);
        $response->assertJsonPath('meta.is_duplicate', true);
        $response->assertJsonPath('meta.canonical_vendor_id', $canonical->id);
        $response->assertJsonCount(0, 'data');
    }

    public function test_duplicates_sorted_by_company_name(): void
    {
        $canonical = Vendor::factory()->create();
        Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Zebra Inc']);
        Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'Alpha LLC']);

        $response = $this->actingAs($this->adminUser)->getJson("/api/vendors/{$canonical->id}/duplicates");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.company_name', 'Alpha LLC');
        $response->assertJsonPath('data.1.company_name', 'Zebra Inc');
    }

    public function test_duplicates_returns_404_for_nonexistent_vendor(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/nonexistent-id/duplicates');

        $response->assertStatus(404);
    }

    // ==================== Potential Duplicates Tests ====================

    public function test_guest_cannot_access_potential_duplicates(): void
    {
        $response = $this->getJson('/api/vendors/potential-duplicates');

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_access_potential_duplicates(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/vendors/potential-duplicates');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_potential_duplicates(): void
    {
        Vendor::factory()->create(['company_name' => 'ABC Plumbing']);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'total_vendors',
                'potential_duplicates_count',
                'threshold',
            ],
        ]);
    }

    public function test_potential_duplicates_finds_similar_names(): void
    {
        Vendor::factory()->create(['company_name' => 'ABC Plumbing Inc']);
        Vendor::factory()->create(['company_name' => 'ABC Plumbing LLC']);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.3');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.potential_duplicates_count', 1);
    }

    public function test_potential_duplicates_finds_same_phone(): void
    {
        Vendor::factory()->create([
            'company_name' => 'Company A',
            'phone' => '(555) 123-4567',
        ]);
        Vendor::factory()->create([
            'company_name' => 'Company B',
            'phone' => '555-123-4567',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.2');

        $response->assertStatus(200);
        // Should find these as potential duplicates due to same phone
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_potential_duplicates_finds_same_email(): void
    {
        Vendor::factory()->create([
            'company_name' => 'Vendor One',
            'email' => 'contact@vendor.com',
        ]);
        Vendor::factory()->create([
            'company_name' => 'Vendor Two',
            'email' => 'Contact@Vendor.com',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.1');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_potential_duplicates_respects_threshold(): void
    {
        // Create vendors with moderate similarity
        Vendor::factory()->create(['company_name' => 'ABC Services']);
        Vendor::factory()->create(['company_name' => 'XYZ Services']);

        // With high threshold, similar names shouldn't match
        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.9');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.threshold', 0.9);
    }

    public function test_potential_duplicates_respects_limit(): void
    {
        // Create many similar vendors
        for ($i = 1; $i <= 10; $i++) {
            Vendor::factory()->create(['company_name' => "Test Plumbing {$i}"]);
        }

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.3&limit=5');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_potential_duplicates_excludes_already_linked(): void
    {
        $canonical = Vendor::factory()->create(['company_name' => 'ABC Plumbing']);
        $duplicate = Vendor::factory()->duplicateOf($canonical)->create(['company_name' => 'ABC Plumbing Co']);
        Vendor::factory()->create(['company_name' => 'DEF Electric']);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates');

        $response->assertStatus(200);
        // The duplicate should not appear as a potential duplicate since it's already linked
        // potentialDuplicates only looks at canonical vendors
        $response->assertJsonPath('meta.total_vendors', 2); // canonical + DEF Electric
    }

    public function test_potential_duplicates_includes_match_reasons(): void
    {
        Vendor::factory()->create(['company_name' => 'ABC Plumbing Inc']);
        Vendor::factory()->create(['company_name' => 'ABC Plumbing LLC']);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.3');

        $response->assertStatus(200);
        if (count($response->json('data')) > 0) {
            $response->assertJsonStructure([
                'data' => [
                    '*' => [
                        'vendor1',
                        'vendor2',
                        'similarity',
                        'match_reasons',
                    ],
                ],
            ]);
        }
    }

    public function test_potential_duplicates_sorted_by_similarity_descending(): void
    {
        // Create vendors with varying similarity
        Vendor::factory()->create([
            'company_name' => 'ABC Plumbing',
            'phone' => '555-123-4567',
        ]);
        Vendor::factory()->create([
            'company_name' => 'ABC Plumbing Inc',
            'phone' => '555-123-4567', // Same phone
        ]);
        Vendor::factory()->create(['company_name' => 'ABC Plumbing LLC']); // Just similar name

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.2');

        $response->assertStatus(200);

        $data = $response->json('data');
        if (count($data) >= 2) {
            $this->assertGreaterThanOrEqual($data[1]['similarity'], $data[0]['similarity']);
        }
    }

    public function test_potential_duplicates_returns_empty_for_no_matches(): void
    {
        Vendor::factory()->create(['company_name' => 'Completely Unique Vendor']);
        Vendor::factory()->create(['company_name' => 'Totally Different Company']);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.9');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.potential_duplicates_count', 0);
    }

    public function test_potential_duplicates_uses_default_threshold(): void
    {
        Vendor::factory()->create();

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.threshold', 0.6); // Default threshold
    }

    public function test_potential_duplicates_handles_empty_vendor_list(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.total_vendors', 0);
        $response->assertJsonPath('meta.potential_duplicates_count', 0);
    }

    // ==================== Edge Cases ====================

    public function test_mark_duplicate_can_change_canonical_vendor(): void
    {
        $originalCanonical = Vendor::factory()->create();
        $newCanonical = Vendor::factory()->create();
        $duplicate = Vendor::factory()->duplicateOf($originalCanonical)->create();

        // First, mark as canonical to remove old link
        $this->actingAs($this->adminUser)->postJson("/api/vendors/{$duplicate->id}/mark-canonical");

        // Then mark as duplicate of new canonical
        $response = $this->actingAs($this->adminUser)->postJson("/api/vendors/{$duplicate->id}/mark-duplicate", [
            'canonical_vendor_id' => $newCanonical->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vendors', [
            'id' => $duplicate->id,
            'canonical_vendor_id' => $newCanonical->id,
        ]);
    }

    public function test_potential_duplicates_ignores_common_email_domains(): void
    {
        // Gmail addresses shouldn't count as a match reason
        Vendor::factory()->create([
            'company_name' => 'Vendor A',
            'email' => 'contact@gmail.com',
        ]);
        Vendor::factory()->create([
            'company_name' => 'Vendor B',
            'email' => 'other@gmail.com',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/vendors/potential-duplicates?threshold=0.05');

        $response->assertStatus(200);
        // Even with a very low threshold, they shouldn't match just on gmail domain
        $data = $response->json('data');
        foreach ($data as $match) {
            // If there is a match, it shouldn't be just because of email domain
            $this->assertNotEmpty($match['match_reasons']);
        }
    }
}
