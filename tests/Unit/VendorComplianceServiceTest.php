<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Vendor;
use App\Services\VendorComplianceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    private VendorComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VendorComplianceService;
    }

    // ==================== getInsuranceIssues Tests ====================

    public function test_get_insurance_issues_detects_expired_workers_comp(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertCount(1, $issues['expired']);
        $this->assertEquals('Workers Comp', $issues['expired'][0]['type']);
        $this->assertArrayHasKey('days_past', $issues['expired'][0]);
    }

    public function test_get_insurance_issues_detects_expired_liability(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::yesterday(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertCount(1, $issues['expired']);
        $this->assertEquals('Liability', $issues['expired'][0]['type']);
    }

    public function test_get_insurance_issues_detects_expired_auto(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::yesterday(),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertCount(1, $issues['expired']);
        $this->assertEquals('Auto', $issues['expired'][0]['type']);
    }

    public function test_get_insurance_issues_detects_expiring_soon(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(15),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertCount(1, $issues['expiring_soon']);
        $this->assertEquals('Workers Comp', $issues['expiring_soon'][0]['type']);
        $this->assertArrayHasKey('days_until', $issues['expiring_soon'][0]);
    }

    public function test_get_insurance_issues_detects_expiring_quarter(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(60),
            'liability_ins_expires' => Carbon::now()->addMonths(6),
            'auto_ins_expires' => Carbon::now()->addMonths(6),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertCount(1, $issues['expiring_quarter']);
        $this->assertEquals('Workers Comp', $issues['expiring_quarter'][0]['type']);
    }

    public function test_get_insurance_issues_detects_missing(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => null,
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertCount(1, $issues['missing']);
        $this->assertEquals('Workers Comp', $issues['missing'][0]['type']);
    }

    public function test_get_insurance_issues_no_issues_when_all_valid(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addYear(),
            'liability_ins_expires' => Carbon::now()->addYear(),
            'auto_ins_expires' => Carbon::now()->addYear(),
        ]);

        $issues = $this->service->getInsuranceIssues($vendor);

        $this->assertEmpty($issues['expired']);
        $this->assertEmpty($issues['expiring_soon']);
        $this->assertEmpty($issues['expiring_quarter']);
        $this->assertEmpty($issues['missing']);
    }

    public function test_get_insurance_issues_uses_custom_dates(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::parse('2026-01-10'),
            'liability_ins_expires' => Carbon::parse('2026-02-15'),
            'auto_ins_expires' => Carbon::parse('2026-03-20'),
        ]);

        $today = Carbon::parse('2026-01-01');
        $thirtyDays = Carbon::parse('2026-01-31');
        $ninetyDays = Carbon::parse('2026-04-01');

        $issues = $this->service->getInsuranceIssues($vendor, $today, $thirtyDays, $ninetyDays);

        $this->assertCount(1, $issues['expiring_soon']); // Workers comp (Jan 10)
        $this->assertCount(2, $issues['expiring_quarter']); // Liability (Feb 15) + Auto (Mar 20)
    }

    // ==================== getWorkersCompIssues Tests ====================

    public function test_get_workers_comp_issues_categorizes_expired(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
        ]);
        $currentVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
        ]);

        $issues = $this->service->getWorkersCompIssues(collect([$expiredVendor, $currentVendor]));

        $this->assertCount(1, $issues['expired']);
        $this->assertCount(1, $issues['current']);
        $this->assertEquals($expiredVendor->id, $issues['expired'][0]['vendor']->id);
    }

    public function test_get_workers_comp_issues_categorizes_expiring_soon(): void
    {
        $expiringSoon = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(15),
        ]);

        $issues = $this->service->getWorkersCompIssues(collect([$expiringSoon]));

        $this->assertCount(1, $issues['expiring_soon']);
        $this->assertArrayHasKey('days_until', $issues['expiring_soon'][0]);
    }

    public function test_get_workers_comp_issues_categorizes_missing(): void
    {
        $missingVendor = Vendor::factory()->create([
            'workers_comp_expires' => null,
        ]);

        $issues = $this->service->getWorkersCompIssues(collect([$missingVendor]));

        $this->assertCount(1, $issues['missing']);
        $this->assertEquals($missingVendor->id, $issues['missing'][0]->id);
    }

    public function test_get_workers_comp_issues_categorizes_current(): void
    {
        $currentVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonths(6),
        ]);

        $issues = $this->service->getWorkersCompIssues(collect([$currentVendor]));

        $this->assertCount(1, $issues['current']);
        $this->assertArrayHasKey('days_until', $issues['current'][0]);
    }

    // ==================== getInsuranceStatus Tests ====================

    public function test_get_insurance_status_returns_expired_for_expired_workers_comp(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $status = $this->service->getInsuranceStatus($vendor);

        $this->assertEquals('expired', $status['workers_comp']);
        $this->assertEquals('current', $status['liability']);
        $this->assertEquals('current', $status['auto']);
        $this->assertEquals('expired', $status['overall']);
    }

    public function test_get_insurance_status_returns_expiring_soon(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(15),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $status = $this->service->getInsuranceStatus($vendor);

        $this->assertEquals('expiring_soon', $status['workers_comp']);
        $this->assertEquals('expiring_soon', $status['overall']);
    }

    public function test_get_insurance_status_returns_missing_for_null(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => null,
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $status = $this->service->getInsuranceStatus($vendor);

        $this->assertEquals('missing', $status['workers_comp']);
        $this->assertEquals('current', $status['liability']);
        $this->assertEquals('current', $status['auto']);
        $this->assertEquals('current', $status['overall']); // Not all missing
    }

    public function test_get_insurance_status_returns_missing_overall_when_all_missing(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => null,
            'liability_ins_expires' => null,
            'auto_ins_expires' => null,
        ]);

        $status = $this->service->getInsuranceStatus($vendor);

        $this->assertEquals('missing', $status['overall']);
    }

    public function test_get_insurance_status_returns_current_when_all_valid(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addMonth(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $status = $this->service->getInsuranceStatus($vendor);

        $this->assertEquals('current', $status['workers_comp']);
        $this->assertEquals('current', $status['liability']);
        $this->assertEquals('current', $status['auto']);
        $this->assertEquals('current', $status['overall']);
    }

    public function test_get_insurance_status_prioritizes_expired_over_expiring_soon(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addDays(15),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);

        $status = $this->service->getInsuranceStatus($vendor);

        $this->assertEquals('expired', $status['overall']);
    }

    // ==================== categorizeVendorsByCompliance Tests ====================

    public function test_categorize_vendors_groups_expired(): void
    {
        $expiredVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(),
            'liability_ins_expires' => Carbon::now()->addMonth(),
            'auto_ins_expires' => Carbon::now()->addMonth(),
        ]);
        $compliantVendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addYear(),
            'liability_ins_expires' => Carbon::now()->addYear(),
            'auto_ins_expires' => Carbon::now()->addYear(),
        ]);

        $categories = $this->service->categorizeVendorsByCompliance(collect([$expiredVendor, $compliantVendor]));

        $this->assertCount(1, $categories['expired']);
        $this->assertCount(1, $categories['compliant']);
        $this->assertEquals($expiredVendor->id, $categories['expired'][0]['vendor']->id);
    }

    public function test_categorize_vendors_groups_expiring_soon(): void
    {
        $expiringSoon = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(15),
            'liability_ins_expires' => Carbon::now()->addYear(),
            'auto_ins_expires' => Carbon::now()->addYear(),
        ]);

        $categories = $this->service->categorizeVendorsByCompliance(collect([$expiringSoon]));

        $this->assertCount(1, $categories['expiring_soon']);
        $this->assertArrayHasKey('issues', $categories['expiring_soon'][0]);
    }

    public function test_categorize_vendors_groups_expiring_quarter(): void
    {
        $expiringQuarter = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::now()->addDays(60),
            'liability_ins_expires' => Carbon::now()->addYear(),
            'auto_ins_expires' => Carbon::now()->addYear(),
        ]);

        $categories = $this->service->categorizeVendorsByCompliance(collect([$expiringQuarter]));

        $this->assertCount(1, $categories['expiring_quarter']);
    }

    public function test_categorize_vendors_groups_missing_info(): void
    {
        $missingInfo = Vendor::factory()->create([
            'workers_comp_expires' => null,
            'liability_ins_expires' => Carbon::now()->addYear(),
            'auto_ins_expires' => Carbon::now()->addYear(),
        ]);

        $categories = $this->service->categorizeVendorsByCompliance(collect([$missingInfo]));

        $this->assertCount(1, $categories['missing_info']);
    }

    public function test_categorize_vendors_prioritizes_expired_over_other_categories(): void
    {
        $vendor = Vendor::factory()->create([
            'workers_comp_expires' => Carbon::yesterday(), // Expired
            'liability_ins_expires' => Carbon::now()->addDays(15), // Expiring soon
            'auto_ins_expires' => null, // Missing
        ]);

        $categories = $this->service->categorizeVendorsByCompliance(collect([$vendor]));

        $this->assertCount(1, $categories['expired']);
        $this->assertEmpty($categories['expiring_soon']);
        $this->assertEmpty($categories['missing_info']);
    }

    public function test_categorize_vendors_empty_collection(): void
    {
        $categories = $this->service->categorizeVendorsByCompliance(collect([]));

        $this->assertEmpty($categories['expired']);
        $this->assertEmpty($categories['expiring_soon']);
        $this->assertEmpty($categories['expiring_quarter']);
        $this->assertEmpty($categories['missing_info']);
        $this->assertEmpty($categories['compliant']);
    }
}
