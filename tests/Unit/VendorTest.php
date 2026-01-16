<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorTest extends TestCase
{
    use RefreshDatabase;

    // ==================== Scope Tests ====================

    public function test_scope_active_filters_active_vendors(): void
    {
        $activeVendor = Vendor::factory()->create(['is_active' => true]);
        $inactiveVendor = Vendor::factory()->inactive()->create();

        $results = Vendor::active()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($activeVendor->id, $results->first()->id);
    }

    public function test_scope_usable_filters_usable_vendors(): void
    {
        $usableVendor = Vendor::factory()->create(['do_not_use' => false]);
        $doNotUseVendor = Vendor::factory()->doNotUse()->create();

        $results = Vendor::usable()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($usableVendor->id, $results->first()->id);
    }

    public function test_scope_canonical_filters_canonical_vendors(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $results = Vendor::canonical()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($canonicalVendor->id, $results->first()->id);
    }

    public function test_scope_duplicates_filters_duplicate_vendors(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $results = Vendor::duplicates()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($duplicateVendor->id, $results->first()->id);
    }

    // ==================== Attribute Accessor Tests ====================

    public function test_trades_array_attribute_parses_single_trade(): void
    {
        $vendor = Vendor::factory()->withTrade('Plumbing')->create();

        $this->assertEquals(['Plumbing'], $vendor->trades_array);
    }

    public function test_trades_array_attribute_parses_multiple_trades(): void
    {
        $vendor = Vendor::factory()->withTrade('Plumbing, HVAC, Electrical')->create();

        $this->assertEquals(['Plumbing', 'HVAC', 'Electrical'], $vendor->trades_array);
    }

    public function test_trades_array_attribute_trims_whitespace(): void
    {
        $vendor = Vendor::factory()->create(['vendor_trades' => '  Plumbing  ,  HVAC  ']);

        $this->assertEquals(['Plumbing', 'HVAC'], $vendor->trades_array);
    }

    public function test_trades_array_attribute_returns_empty_array_for_null(): void
    {
        $vendor = Vendor::factory()->create(['vendor_trades' => null]);

        $this->assertEquals([], $vendor->trades_array);
    }

    public function test_trades_array_attribute_returns_empty_array_for_empty_string(): void
    {
        $vendor = Vendor::factory()->create(['vendor_trades' => '']);

        $this->assertEquals([], $vendor->trades_array);
    }

    public function test_full_address_attribute_concatenates_all_parts(): void
    {
        $vendor = Vendor::factory()->create([
            'address_street' => '123 Main St',
            'address_city' => 'San Francisco',
            'address_state' => 'CA',
            'address_zip' => '94102',
        ]);

        $this->assertEquals('123 Main St, San Francisco, CA, 94102', $vendor->full_address);
    }

    public function test_full_address_attribute_handles_missing_parts(): void
    {
        $vendor = Vendor::factory()->create([
            'address_street' => '123 Main St',
            'address_city' => null,
            'address_state' => 'CA',
            'address_zip' => null,
        ]);

        $this->assertEquals('123 Main St, CA', $vendor->full_address);
    }

    public function test_full_address_attribute_returns_null_when_all_parts_null(): void
    {
        $vendor = Vendor::factory()->create([
            'address_street' => null,
            'address_city' => null,
            'address_state' => null,
            'address_zip' => null,
        ]);

        $this->assertNull($vendor->full_address);
    }

    // ==================== Insurance Expiration Tests ====================

    public function test_has_expired_insurance_returns_true_for_expired_workers_comp(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::tomorrow(),
            'auto_ins_expires' => null,
        ]);

        $this->assertTrue($vendor->hasExpiredInsurance());
    }

    public function test_has_expired_insurance_returns_true_for_expired_liability(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::tomorrow(),
            'liability_ins_expires' => Carbon::yesterday(),
            'auto_ins_expires' => null,
        ]);

        $this->assertTrue($vendor->hasExpiredInsurance());
    }

    public function test_has_expired_insurance_returns_true_for_expired_auto(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::tomorrow(),
            'liability_ins_expires' => Carbon::tomorrow(),
            'auto_ins_expires' => Carbon::yesterday(),
        ]);

        $this->assertTrue($vendor->hasExpiredInsurance());
    }

    public function test_has_expired_insurance_returns_false_when_all_valid(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::tomorrow(),
            'liability_ins_expires' => Carbon::tomorrow(),
            'auto_ins_expires' => Carbon::tomorrow(),
        ]);

        $this->assertFalse($vendor->hasExpiredInsurance());
    }

    public function test_has_expired_insurance_returns_false_when_all_null(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => null,
            'liability_ins_expires' => null,
            'auto_ins_expires' => null,
        ]);

        $this->assertFalse($vendor->hasExpiredInsurance());
    }

    public function test_has_expired_insurance_considers_today_as_not_expired(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::today(),
            'liability_ins_expires' => Carbon::today(),
            'auto_ins_expires' => Carbon::today(),
        ]);

        $this->assertFalse($vendor->hasExpiredInsurance());
    }

    // ==================== License Expiration Tests ====================

    public function test_has_expired_license_returns_true_for_expired_license(): void
    {
        $vendor = Vendor::factory()->create([
            'state_lic_expires' => Carbon::yesterday(),
        ]);

        $this->assertTrue($vendor->hasExpiredLicense());
    }

    public function test_has_expired_license_returns_false_for_valid_license(): void
    {
        $vendor = Vendor::factory()->create([
            'state_lic_expires' => Carbon::tomorrow(),
        ]);

        $this->assertFalse($vendor->hasExpiredLicense());
    }

    public function test_has_expired_license_returns_false_for_null_license(): void
    {
        $vendor = Vendor::factory()->create([
            'state_lic_expires' => null,
        ]);

        $this->assertFalse($vendor->hasExpiredLicense());
    }

    public function test_has_expired_license_considers_today_as_not_expired(): void
    {
        $vendor = Vendor::factory()->create([
            'state_lic_expires' => Carbon::today(),
        ]);

        $this->assertFalse($vendor->hasExpiredLicense());
    }

    // ==================== Canonical/Duplicate Status Tests ====================

    public function test_is_canonical_returns_true_for_canonical_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertTrue($vendor->isCanonical());
    }

    public function test_is_canonical_returns_false_for_duplicate_vendor(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $this->assertFalse($duplicateVendor->isCanonical());
    }

    public function test_is_duplicate_returns_true_for_duplicate_vendor(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $this->assertTrue($duplicateVendor->isDuplicate());
    }

    public function test_is_duplicate_returns_false_for_canonical_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertFalse($vendor->isDuplicate());
    }

    // ==================== Canonical Vendor Retrieval Tests ====================

    public function test_get_canonical_vendor_returns_self_for_canonical(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertEquals($vendor->id, $vendor->getCanonicalVendor()->id);
    }

    public function test_get_canonical_vendor_returns_canonical_for_duplicate(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $this->assertEquals($canonicalVendor->id, $duplicateVendor->getCanonicalVendor()->id);
    }

    public function test_get_effective_vendor_id_returns_own_id_for_canonical(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertEquals($vendor->id, $vendor->getEffectiveVendorId());
    }

    public function test_get_effective_vendor_id_returns_canonical_id_for_duplicate(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $this->assertEquals($canonicalVendor->id, $duplicateVendor->getEffectiveVendorId());
    }

    // ==================== Vendor Group Tests ====================

    public function test_get_all_group_vendor_ids_for_canonical_includes_all_duplicates(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicate1 = Vendor::factory()->duplicateOf($canonicalVendor)->create();
        $duplicate2 = Vendor::factory()->duplicateOf($canonicalVendor)->create();
        $unrelatedVendor = Vendor::factory()->create();

        $groupIds = $canonicalVendor->getAllGroupVendorIds();

        $this->assertCount(3, $groupIds);
        $this->assertContains($canonicalVendor->id, $groupIds);
        $this->assertContains($duplicate1->id, $groupIds);
        $this->assertContains($duplicate2->id, $groupIds);
        $this->assertNotContains($unrelatedVendor->id, $groupIds);
    }

    public function test_get_all_group_vendor_ids_for_duplicate_includes_canonical_and_siblings(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicate1 = Vendor::factory()->duplicateOf($canonicalVendor)->create();
        $duplicate2 = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $groupIds = $duplicate1->getAllGroupVendorIds();

        $this->assertCount(3, $groupIds);
        $this->assertContains($canonicalVendor->id, $groupIds);
        $this->assertContains($duplicate1->id, $groupIds);
        $this->assertContains($duplicate2->id, $groupIds);
    }

    public function test_get_all_group_vendor_ids_for_standalone_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        $groupIds = $vendor->getAllGroupVendorIds();

        $this->assertCount(1, $groupIds);
        $this->assertContains($vendor->id, $groupIds);
    }

    // ==================== Mark As Duplicate/Canonical Tests ====================

    public function test_mark_as_duplicate_of_sets_canonical_vendor_id(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $vendor = Vendor::factory()->create();

        $result = $vendor->markAsDuplicateOf($canonicalVendor);

        $this->assertTrue($result);
        $this->assertEquals($canonicalVendor->id, $vendor->fresh()->canonical_vendor_id);
    }

    public function test_mark_as_duplicate_of_handles_target_being_duplicate(): void
    {
        $trueCanonical = Vendor::factory()->create();
        $intermediateDuplicate = Vendor::factory()->duplicateOf($trueCanonical)->create();
        $newVendor = Vendor::factory()->create();

        $result = $newVendor->markAsDuplicateOf($intermediateDuplicate);

        $this->assertTrue($result);
        // Should point to the true canonical, not the intermediate duplicate
        $this->assertEquals($trueCanonical->id, $newVendor->fresh()->canonical_vendor_id);
    }

    public function test_mark_as_duplicate_of_self_returns_false(): void
    {
        $vendor = Vendor::factory()->create();

        $result = $vendor->markAsDuplicateOf($vendor);

        $this->assertFalse($result);
        $this->assertNull($vendor->fresh()->canonical_vendor_id);
    }

    public function test_mark_as_canonical_removes_canonical_vendor_id(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $result = $duplicateVendor->markAsCanonical();

        $this->assertTrue($result);
        $this->assertNull($duplicateVendor->fresh()->canonical_vendor_id);
    }

    public function test_mark_as_canonical_on_already_canonical_succeeds(): void
    {
        $vendor = Vendor::factory()->create();

        $result = $vendor->markAsCanonical();

        $this->assertTrue($result);
        $this->assertNull($vendor->fresh()->canonical_vendor_id);
    }

    // ==================== Relationship Tests ====================

    public function test_canonical_vendor_relationship(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $this->assertEquals($canonicalVendor->id, $duplicateVendor->canonicalVendor->id);
    }

    public function test_duplicate_vendors_relationship(): void
    {
        $canonicalVendor = Vendor::factory()->create();
        $duplicate1 = Vendor::factory()->duplicateOf($canonicalVendor)->create();
        $duplicate2 = Vendor::factory()->duplicateOf($canonicalVendor)->create();

        $this->assertCount(2, $canonicalVendor->duplicateVendors);
        $this->assertTrue($canonicalVendor->duplicateVendors->contains($duplicate1));
        $this->assertTrue($canonicalVendor->duplicateVendors->contains($duplicate2));
    }

    // ==================== Factory Tests ====================

    public function test_factory_creates_valid_vendor(): void
    {
        $vendor = Vendor::factory()->create();

        $this->assertNotNull($vendor->id);
        $this->assertNotNull($vendor->external_id);
        $this->assertNotNull($vendor->company_name);
        $this->assertTrue($vendor->is_active);
        $this->assertFalse($vendor->do_not_use);
    }

    public function test_factory_inactive_creates_inactive_vendor(): void
    {
        $vendor = Vendor::factory()->inactive()->create();

        $this->assertFalse($vendor->is_active);
    }

    public function test_factory_do_not_use_creates_do_not_use_vendor(): void
    {
        $vendor = Vendor::factory()->doNotUse()->create();

        $this->assertTrue($vendor->do_not_use);
    }

    public function test_factory_with_expired_insurance_creates_vendor_with_expired_insurance(): void
    {
        $vendor = Vendor::factory()->withExpiredInsurance()->create();

        $this->assertTrue($vendor->hasExpiredInsurance());
    }

    public function test_factory_with_expired_license_creates_vendor_with_expired_license(): void
    {
        $vendor = Vendor::factory()->withExpiredLicense()->create();

        $this->assertTrue($vendor->hasExpiredLicense());
    }

    // ==================== Insurance Scope Tests ====================

    public function test_scope_with_expired_insurance_filters_expired_workers_comp(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $results = Vendor::withExpiredInsurance()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($expiredVendor->id, $results->first()->id);
    }

    public function test_scope_with_expired_insurance_filters_expired_liability(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::yesterday(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $results = Vendor::withExpiredInsurance()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($expiredVendor->id, $results->first()->id);
    }

    public function test_scope_with_expired_insurance_filters_expired_auto(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::yesterday(),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $results = Vendor::withExpiredInsurance()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($expiredVendor->id, $results->first()->id);
    }

    public function test_scope_with_expiring_soon_insurance_filters_within_30_days(): void
    {
        $expiringSoonVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(15),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonths(6),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);

        $results = Vendor::withExpiringSoonInsurance()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($expiringSoonVendor->id, $results->first()->id);
    }

    public function test_scope_with_expiring_soon_insurance_respects_custom_days(): void
    {
        $expiringSoonVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(45),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonths(6),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);

        // Default 30 days should NOT include the vendor
        $results30 = Vendor::withExpiringSoonInsurance(30)->get();
        $this->assertCount(0, $results30);

        // 60 days should include the vendor
        $results60 = Vendor::withExpiringSoonInsurance(60)->get();
        $this->assertCount(1, $results60);
        $this->assertEquals($expiringSoonVendor->id, $results60->first()->id);
    }

    public function test_scope_with_expiring_soon_excludes_expired(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);

        $results = Vendor::withExpiringSoonInsurance()->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_with_current_insurance_filters_all_valid(): void
    {
        $currentVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $results = Vendor::withCurrentInsurance()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($currentVendor->id, $results->first()->id);
    }

    public function test_scope_with_current_insurance_allows_null_dates(): void
    {
        $vendorWithNulls = Vendor::factory()->create([
            'workers_comp_expires' => null,
            'liability_ins_expires' => null,
            'auto_ins_expires' => null,
        ]);
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => null,
            'auto_ins_expires' => null,
        ]);

        $results = Vendor::withCurrentInsurance()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($vendorWithNulls->id, $results->first()->id);
    }

    public function test_scope_with_insurance_status_expired(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $results = Vendor::withInsuranceStatus('expired')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($expiredVendor->id, $results->first()->id);
    }

    public function test_scope_with_insurance_status_expiring_soon(): void
    {
        $expiringSoonVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(15),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);
        $validVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonths(6),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);

        $results = Vendor::withInsuranceStatus('expiring_soon')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($expiringSoonVendor->id, $results->first()->id);
    }

    public function test_scope_with_insurance_status_current(): void
    {
        $currentVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $results = Vendor::withInsuranceStatus('current')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($currentVendor->id, $results->first()->id);
    }

    public function test_scope_with_insurance_status_invalid_returns_all(): void
    {
        $vendor1 = Vendor::factory()->create();
        $vendor2 = Vendor::factory()->create();

        $results = Vendor::withInsuranceStatus('invalid_status')->get();

        $this->assertCount(2, $results);
    }

    // ==================== Combined Scope Tests ====================

    public function test_combined_scopes_work_together(): void
    {
        $activeUsableCanonical = Vendor::factory()->create([
            'is_active' => true,
            'do_not_use' => false,
        ]);
        $inactiveVendor = Vendor::factory()->inactive()->create();
        $doNotUseVendor = Vendor::factory()->doNotUse()->create();
        $duplicateVendor = Vendor::factory()->duplicateOf($activeUsableCanonical)->create();

        $results = Vendor::active()->usable()->canonical()->get();

        $this->assertCount(1, $results);
        $this->assertEquals($activeUsableCanonical->id, $results->first()->id);
    }
}
