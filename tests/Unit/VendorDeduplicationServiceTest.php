<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Vendor;
use App\Services\VendorDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorDeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VendorDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VendorDeduplicationService;
    }

    // ==================== normalizeString Tests ====================

    public function test_normalize_string_lowercases(): void
    {
        $result = $this->service->normalizeString('ABC PLUMBING');

        $this->assertEquals('abc plumbing', $result);
    }

    public function test_normalize_string_removes_common_suffixes(): void
    {
        $this->assertEquals('acme', $this->service->normalizeString('ACME LLC'));
        $this->assertEquals('acme', $this->service->normalizeString('Acme Inc'));
        $this->assertEquals('acme', $this->service->normalizeString('Acme Corp'));
        $this->assertEquals('acme', $this->service->normalizeString('Acme Co'));
        $this->assertEquals('acme', $this->service->normalizeString('Acme Ltd'));
        $this->assertEquals('acme', $this->service->normalizeString('Acme Company'));
    }

    public function test_normalize_string_removes_special_characters(): void
    {
        $result = $this->service->normalizeString('Acme & Sons, Inc.');

        $this->assertEquals('acme sons', $result);
    }

    public function test_normalize_string_collapses_whitespace(): void
    {
        $result = $this->service->normalizeString('Acme    Plumbing   Services');

        $this->assertEquals('acme plumbing services', $result);
    }

    public function test_normalize_string_trims(): void
    {
        $result = $this->service->normalizeString('  Acme  ');

        $this->assertEquals('acme', $result);
    }

    // ==================== calculateSimilarity Tests ====================

    public function test_calculate_similarity_exact_company_names(): void
    {
        $vendor1 = Vendor::factory()->create(['company_name' => 'Acme Plumbing']);
        $vendor2 = Vendor::factory()->create(['company_name' => 'Acme Plumbing']);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        $this->assertGreaterThanOrEqual(0.5, $similarity);
    }

    public function test_calculate_similarity_similar_company_names(): void
    {
        $vendor1 = Vendor::factory()->create(['company_name' => 'Acme Plumbing LLC']);
        $vendor2 = Vendor::factory()->create(['company_name' => 'Acme Plumbing Inc']);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        // Should be similar after normalization removes suffixes
        $this->assertGreaterThanOrEqual(0.4, $similarity);
    }

    public function test_calculate_similarity_same_phone_adds_score(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Company A',
            'phone' => '(555) 123-4567',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Company B',
            'phone' => '555-123-4567',
        ]);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        $this->assertGreaterThanOrEqual(0.25, $similarity);
    }

    public function test_calculate_similarity_short_phone_ignored(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Company A',
            'phone' => '123-4567',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Company B',
            'phone' => '123-4567',
        ]);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        // Short phone should not contribute to score
        $this->assertLessThan(0.25, $similarity);
    }

    public function test_calculate_similarity_exact_email_adds_score(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Different Company A',
            'email' => 'contact@company.com',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Different Company B',
            'email' => 'CONTACT@COMPANY.COM',
        ]);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        $this->assertGreaterThanOrEqual(0.15, $similarity);
    }

    public function test_calculate_similarity_same_company_domain_adds_score(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Different A',
            'email' => 'sales@acmeplumbing.com',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Different B',
            'email' => 'support@acmeplumbing.com',
        ]);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        $this->assertGreaterThanOrEqual(0.05, $similarity);
    }

    public function test_calculate_similarity_ignores_common_email_domains(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Company A',
            'email' => 'user1@gmail.com',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Company B',
            'email' => 'user2@gmail.com',
        ]);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        // Gmail domain should not add score
        $this->assertLessThan(0.1, $similarity);
    }

    public function test_calculate_similarity_similar_contact_names(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Company A',
            'contact_name' => 'John Smith',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Company B',
            'contact_name' => 'John Smith',
        ]);

        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        $this->assertGreaterThan(0, $similarity);
    }

    public function test_calculate_similarity_null_fields_handled(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Company A',
            'phone' => null,
            'email' => null,
            'contact_name' => null,
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Company B',
            'phone' => null,
            'email' => null,
            'contact_name' => null,
        ]);

        // Should not throw exception
        $similarity = $this->service->calculateSimilarity($vendor1, $vendor2);

        $this->assertIsFloat($similarity);
    }

    // ==================== getMatchReasons Tests ====================

    public function test_get_match_reasons_similar_company_names(): void
    {
        $vendor1 = Vendor::factory()->create(['company_name' => 'Acme Plumbing']);
        $vendor2 = Vendor::factory()->create(['company_name' => 'Acme Plumbing LLC']);

        $reasons = $this->service->getMatchReasons($vendor1, $vendor2);

        $this->assertNotEmpty($reasons);
        $this->assertTrue(
            collect($reasons)->contains(fn ($r) => str_contains($r, 'company names'))
        );
    }

    public function test_get_match_reasons_same_phone(): void
    {
        $vendor1 = Vendor::factory()->create(['phone' => '555-123-4567']);
        $vendor2 = Vendor::factory()->create(['phone' => '(555) 123-4567']);

        $reasons = $this->service->getMatchReasons($vendor1, $vendor2);

        $this->assertContains('Same phone number', $reasons);
    }

    public function test_get_match_reasons_same_email(): void
    {
        $vendor1 = Vendor::factory()->create(['email' => 'test@example.com']);
        $vendor2 = Vendor::factory()->create(['email' => 'TEST@EXAMPLE.COM']);

        $reasons = $this->service->getMatchReasons($vendor1, $vendor2);

        $this->assertContains('Same email address', $reasons);
    }

    public function test_get_match_reasons_similar_contact_names(): void
    {
        $vendor1 = Vendor::factory()->create(['contact_name' => 'John Smith']);
        $vendor2 = Vendor::factory()->create(['contact_name' => 'John Smith']);

        $reasons = $this->service->getMatchReasons($vendor1, $vendor2);

        $this->assertContains('Similar contact names', $reasons);
    }

    public function test_get_match_reasons_empty_when_no_matches(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'ABC Company',
            'phone' => '111-111-1111',
            'email' => 'abc@abc.com',
            'contact_name' => 'Alice',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'XYZ Corporation',
            'phone' => '999-999-9999',
            'email' => 'xyz@xyz.com',
            'contact_name' => 'Bob',
        ]);

        $reasons = $this->service->getMatchReasons($vendor1, $vendor2);

        $this->assertEmpty($reasons);
    }

    // ==================== findDuplicatesInCollection Tests ====================

    public function test_find_duplicates_returns_similar_vendors(): void
    {
        $vendor1 = Vendor::factory()->create(['company_name' => 'Acme Plumbing']);
        $vendor2 = Vendor::factory()->create(['company_name' => 'Acme Plumbing LLC']);
        $vendor3 = Vendor::factory()->create(['company_name' => 'Totally Different']);

        $vendors = collect([$vendor1, $vendor2, $vendor3]);
        $duplicates = $this->service->findDuplicatesInCollection($vendors, 0.3);

        $this->assertCount(1, $duplicates);
        $this->assertEquals($vendor1->id, $duplicates[0]['vendor1']->id);
        $this->assertEquals($vendor2->id, $duplicates[0]['vendor2']->id);
    }

    public function test_find_duplicates_respects_threshold(): void
    {
        $vendor1 = Vendor::factory()->create(['company_name' => 'Acme']);
        $vendor2 = Vendor::factory()->create(['company_name' => 'Acme Plus']);

        $vendors = collect([$vendor1, $vendor2]);

        // Low threshold should find match
        $lowThreshold = $this->service->findDuplicatesInCollection($vendors, 0.1);
        $this->assertNotEmpty($lowThreshold);

        // Very high threshold should not find match
        $highThreshold = $this->service->findDuplicatesInCollection($vendors, 0.99);
        $this->assertEmpty($highThreshold);
    }

    public function test_find_duplicates_respects_limit(): void
    {
        // Create 5 similar vendors that will all match each other
        $vendors = collect();
        for ($i = 1; $i <= 5; $i++) {
            $vendors->push(Vendor::factory()->create([
                'company_name' => "Acme Plumbing $i",
                'phone' => '555-123-4567',
            ]));
        }

        $duplicates = $this->service->findDuplicatesInCollection($vendors, 0.2, 3);

        $this->assertCount(3, $duplicates);
    }

    public function test_find_duplicates_sorted_by_similarity(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Acme Plumbing',
            'phone' => '555-123-4567',
            'email' => 'acme@acme.com',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Acme Plumbing LLC',
            'phone' => '555-123-4567',
            'email' => 'acme@acme.com',
        ]);
        $vendor3 = Vendor::factory()->create([
            'company_name' => 'Acme Services',
        ]);

        $vendors = collect([$vendor1, $vendor2, $vendor3]);
        $duplicates = $this->service->findDuplicatesInCollection($vendors, 0.1);

        // First pair should have higher similarity (more matching fields)
        if (count($duplicates) > 1) {
            $this->assertGreaterThanOrEqual(
                $duplicates[1]['similarity'],
                $duplicates[0]['similarity']
            );
        }
    }

    public function test_find_duplicates_includes_match_reasons(): void
    {
        $vendor1 = Vendor::factory()->create([
            'company_name' => 'Acme Plumbing',
            'phone' => '555-123-4567',
        ]);
        $vendor2 = Vendor::factory()->create([
            'company_name' => 'Acme Plumbing LLC',
            'phone' => '555-123-4567',
        ]);

        $vendors = collect([$vendor1, $vendor2]);
        $duplicates = $this->service->findDuplicatesInCollection($vendors, 0.3);

        $this->assertArrayHasKey('match_reasons', $duplicates[0]);
        $this->assertNotEmpty($duplicates[0]['match_reasons']);
    }

    public function test_find_duplicates_empty_collection(): void
    {
        $duplicates = $this->service->findDuplicatesInCollection(collect());

        $this->assertEmpty($duplicates);
    }

    public function test_find_duplicates_single_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        $duplicates = $this->service->findDuplicatesInCollection(collect([$vendor]));

        $this->assertEmpty($duplicates);
    }

    // ==================== findPotentialDuplicates Tests ====================

    public function test_find_potential_duplicates_queries_canonical_vendors(): void
    {
        $canonical1 = Vendor::factory()->create(['company_name' => 'Acme Plumbing']);
        Vendor::factory()->create(['company_name' => 'Acme Plumbing LLC']);
        $duplicate = Vendor::factory()->duplicateOf($canonical1)->create([
            'company_name' => 'Acme Plumbing',
        ]);

        $duplicates = $this->service->findPotentialDuplicates(0.3);

        // Should only compare canonical vendors, not include the duplicate
        $vendorIds = collect($duplicates)->flatMap(fn ($d) => [
            $d['vendor1']->id,
            $d['vendor2']->id,
        ])->unique();

        $this->assertNotContains($duplicate->id, $vendorIds);
    }
}
